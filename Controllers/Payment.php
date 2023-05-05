<?php

namespace Mobbex\WP\Checkout\Controllers;

final class Payment
{
    /** @var \Mobbex\WP\Checkout\Models\Config */
    public $config;
    
    /** @var \Mobbex\WP\Checkout\Models\Logger */
    public $logger;

    /** @var \Mobbex\WP\Checkout\Models\Helper */
    public $helper;

    public function __construct()
    {
        $this->config = new \Mobbex\WP\Checkout\Models\Config();
        $this->helper = new \Mobbex\WP\Checkout\Models\Helper();
        $this->logger = new \Mobbex\WP\Checkout\Models\Logger();

        if ($this->helper->isReady())
            // Add Mobbex return url hook
            add_action('woocommerce_api_mobbex_return_url', [$this, 'mobbex_return_url']);

        // Add Mobbex Webhook hook 
        add_action('rest_api_init', function () {
            register_rest_route('mobbex/v1', '/webhook', [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'mobbex_webhook'],
                'permission_callback' => '__return_true',
            ]);
        });
    }

    /**
     * Redirect to Mobbex checkout or cart page in error case.
     * 
     */
    public function mobbex_return_url()
    {
        // Retrieves necessary data from the current URL's query string
        $status = $_GET['status'];
        $id     = $_GET['mobbex_order_id'];
        $token  = $_GET['mobbex_token'];
        $error  = false;

        if (empty($status) || empty($id) || empty($token))
            $error = "No se pudo validar la transacción. Contacte con el administrador de su sitio";

        if (!\Mobbex\Repository::validateToken($token))
            $error = "Token de seguridad inválido.";

        if ($error)
            return $this->_redirect_to_error_endpoint($error);

        // Gets order by wc order factory
        $order = wc_get_order($id);

        if ($status > 1 && $status < 400) {
            // Redirect
            $redirect = $order->get_checkout_order_received_url();
        } else {
            return $this->_redirect_to_error_endpoint('Transacción fallida. Reintente con otro método de pago.');
        }

        WC()->session->set('order_id', null);
        WC()->session->set('order_awaiting_payment', null);

        wp_safe_redirect($redirect);
    }

    /**
     * Redirects to the error route or cart
     * 
     * @param string $error_msg
     * @return array
     */
    private function _redirect_to_error_endpoint($error_msg)
    {
        if ($this->helper->settings['error_redirection'])
            // Sets error message
            $error_msg= 'Transacción Fallida. Redirigido a ruta configurada.';

        // Sets route
        $route = $this->helper->settings['error_redirection'] ? home_url('/' . $this->helper->settings['error_redirection']) : wc_get_cart_url();

        // Add error notice
        wc_add_notice($error_msg, 'error');
        // Redirect to route
        wp_redirect($route);
        
        return array('result' => 'error', 'redirect' => $route);
    }  

    /**
     * Process the Mobbex Webhook.
     * 
     * @param object $request
     * @return array
     */
    public function mobbex_webhook($request)
    {
        try {
            // Get request data
            $requestData = isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] == 'application/json' ? apply_filters('mobbex_order_webhook',  json_decode(file_get_contents('php://input'), true)) : apply_filters('mobbex_order_webhook', $request->get_params());
            $postData    = !empty($requestData['data']) ? $requestData['data'] : [];
            // Get required params from query params
            $id          = $request->get_param('mobbex_order_id');
            $token       = $request->get_param('mobbex_token');

            // Send a debug log to Simple Hystory dashboard
            $this->logger->log('debug', 'payment > mobbex_webhook | Mobbex Webhook: Formating transaction', compact('id', 'token', 'postData'));

            // Gets order data from webhook and filter it
            $webhookData = \Mobbex\WP\Checkout\Models\Helper::format_webhook_data($id, $postData);
            
            // Save transaction in mobbex table
            global $wpdb;
            $wpdb->insert($wpdb->prefix.'mobbex_transaction', $webhookData, \Mobbex\WP\Checkout\Models\Helper::db_column_format($webhookData));

            // Try to process webhook
            $result = $this->process_webhook($id, $token, $webhookData);
            
            // Retrieves information to platform
            return [
                'result'   => $result,
                'platform' => [
                    'name'      => 'woocommerce',
                    'version'   => MOBBEX_VERSION,
                    'ecommerce' => [
                        'wordpress'   => get_bloginfo('version'),
                        'woocommerce' => WC_VERSION
                    ]
                ],
            ];
        } catch (\Exception $e) {
            // Send a debug log to Simple Hystory dashboard
            $this->logger->log('error', "payment > mobbex_webhook | REST API > Error", $e->getMessage());

            return [
                "result" => false,
            ];
        }
    }

    /**
     * Process & store the data of the Mobbex webhook.
     * 
     * @param int $order_id
     * @param string $token
     * @param array $data
     * 
     * @return bool 
     */
    public function process_webhook($order_id, $token, $data)
    {
        // Get Status from webhook data
        $status = isset($data['status_code']) ? $data['status_code'] : null;
        // Get order from wc order factory 
        $order  = wc_get_order($order_id);

        // Send a debug log to Simple Hystory dashboard
        $this->logger->log('debug', 'payment > process_webhook | Mobbex Webhook: Processing data', compact('order_id', 'data'));

        if (!$status || !$order_id || !$token || !\Mobbex\Repository::validateToken($token))
            // Send a debug log to Simple Hystory dashboard
            return $this->logger->log('error', 'payment > process_webhook | Mobbex Webhook: Invalid mobbex token or empty data');

        // Catch refunds webhooks
        if ($status == 602 || $status == 605)
            return !is_wp_error($this->refund_order($data));

        // Bypass any child webhook (except refunds)
        if ($data['parent'] != 'yes')
            return (bool) $this->add_child_note($order, $data);

        // Exit if it is a expired operation and the order has already been paid
        if ($order->is_paid() && $status == 401)
            return true;

        $order->update_meta_data('mobbex_webhook', json_decode($data['data'], true));
        $order->update_meta_data('mobbex_payment_id', $data['payment_id']);

        // Get payment method name
        $source = json_decode($data['data'], true)['payment']['source'];
        $payment_method = $source['name'];

        // TODO: Check the Status and Make a better note here based on the last registered status
        $main_mobbex_note = 'ID de Operación Mobbex: ' . $data['payment_id'] . '. ';

        if (!empty($data['entity_uid'])) {
            $entity_uid = $data['entity_uid'];

            // Set coupon url
            $mobbex_order_url = str_replace(['{entity.uid}', '{payment.id}'], [$entity_uid, $data['payment_id']], MOBBEX_COUPON);

            $order->update_meta_data('mobbex_coupon_url', $mobbex_order_url);
            $order->add_order_note('URL al Cupón: ' . $mobbex_order_url);
        }

        if (!empty($source['type']) && $source['type'] == 'card') {
            // Set payment info and plans
            $mobbex_card_payment_info = $payment_method . ' ( ' . $source['number'] . ' )';
            $mobbex_card_plan = $source['installment']['description'] . '. ' . $source['installment']['count'] . ' Cuota/s' . ' de ' . $source['installment']['amount'];

            $order->update_meta_data('mobbex_card_info', $mobbex_card_payment_info);
            $order->update_meta_data('mobbex_plan', $mobbex_card_plan);

            $main_mobbex_note .= 'Pago realizado con ' . $mobbex_card_payment_info . '. ' . $mobbex_card_plan . '. ';
        } else {
            $main_mobbex_note .= 'Pago realizado con ' . $payment_method . '. ';
        }

        $order->add_order_note($main_mobbex_note);

        if (isset($data['risk_analysis']) && $data['risk_analysis'] > 0) {
            // Checks operation risk
            $order->add_order_note('El riesgo de la operación fue evaluado en: ' . $data['risk_analisys']);
            $order->update_meta_data('mobbex_risk_analysis', $data['risk_analisys']);
        }

        if (!empty($payment_method)) {
            $order->set_payment_method_title($payment_method . ' ' . __('a través de Mobbex'));
        }

        // Save in database
        $order->save();

        // Update totals
        $this->update_order_total($order, $data);

        // Change status and send email
        $this->update_order_status($order, $data);

        //action with the checkout data
        do_action('mobbex_webhook_process', $order_id, json_decode($data['data'], true));

        return true;
    }

    /**
     * Update order status using webhook formated data.
     * 
     * @param WC_Order $order
     * @param array $data
     * 
     * @return bool Update result.
     */
    public function update_order_status($order, $data)
    {
        // Gets order and order status
        $helper = new \Mobbex\WP\Checkout\Helper\Order($order);
        $status = $helper->get_status_from_code($data['status_code']);

        // Try to complete payment if status was approved
        if (in_array($data['status_code'], $helper->status_codes['approved'])) {
            // If is configured a paid status, and is not paid yet complete payment and return
            if (in_array($status, wc_get_is_paid_statuses()))
                return $order->is_paid() || $order->payment_complete($data['payment_id']);

            $order->payment_complete($data['payment_id']);
        }

        return $order->update_status($status, $data['status_message']);
    }

    /**
     * Update order total paid using webhook formatted data.
     * 
     * @param WC_Order $order
     * @param array $data
     */
    public function update_order_total($order, $data)
    {
        // Store original order total & update order with mobbex total
        $order_total = $order->get_total();
        
        if($order_total == $data['total'])
            return;

        // Update the order total
        $order->set_total($data['total']);
        $order->save();
        
        // First remove previus fees
        $order->remove_order_items('fee');

        // Add a fee item to order with the difference
        $item = new \WC_Order_Item_Fee;
        $item->set_props([
            'name'   => $data['total'] > $order_total ? 'Cargo financiero' : 'Descuento',
            'amount' => $data['total'] - $order_total,
            'total'  => $data['total'] - $order_total,
        ]);

        //Add financial items
        $order->add_item($item);

        // Recalculate totals
        $order->calculate_totals();
    }

    /**
     * Try to refund an order using webhook formatted data.
     * 
     * @param array $data
     * 
     * @return WC_Order_Refund|WP_Error
     */
    public function refund_order($data)
    {
        return wc_create_refund([
            'amount'   => $data['total'],
            'order_id' => $data['order_id'],
        ]);
    }

    /**
     * Add a note with the child transaction data to the order given.
     * 
     * @param WC_Order $order
     * @param array $data Webhook child tansaction.
     * 
     * @return int Comment id.
     */
    public function add_child_note($order, $data)
    {
        return $order->add_order_note(sprintf(
            'Transacción Hija Procesada: ID: %s. Estado: %s (%s). Total: $%s. Método: %s %s (%sx$%s). Tarjeta: %s.',
            $data['payment_id'],
            $data['status_code'],
            $data['status_message'],
            $data['total'],
            $data['source_name'],
            $data['installment_name'],
            $data['installment_count'],
            $data['installment_amount'],
            $data['source_number']
        ));
    }
}
