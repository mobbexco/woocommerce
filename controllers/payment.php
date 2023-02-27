<?php

namespace Mobbex\Controller;

final class Payment
{
    /** @var \Mobbex\WP\Checkout\Includes\Config */
    public $config;
    
    /** @var \MobbexLogger */
    public $logger;

    /** @var \MobbexHelper */
    public $helper;

    public function __construct()
    {
        $this->config = new \Mobbex\WP\Checkout\Includes\Config();
        $this->helper = new \MobbexHelper();
        $this->logger = new \MobbexLogger();

        if ($this->helper->isReady())
            add_action('woocommerce_api_mobbex_return_url', [$this, 'mobbex_return_url']);

        //Add Mobbex Webhook hook 
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
        $status = $_GET['status'];
        $id     = $_GET['mobbex_order_id'];
        $token  = $_GET['mobbex_token'];
        $error  = false;

        if (empty($status) || empty($id) || empty($token))
            $error = "No se pudo validar la transacción. Contacte con el administrador de su sitio";

        if (!$this->helper->valid_mobbex_token($token))
            $error = "Token de seguridad inválido.";

        if ($error)
            return $this->_redirect_to_error_endpoint($error);

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
            $error_msg= 'Transacción Fallida. Redirigido a ruta configurada.';

        $route = $this->helper->settings['error_redirection'] ? home_url('/' . $this->helper->settings['error_redirection']) : wc_get_cart_url();

        wc_add_notice($error_msg, 'error');
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
            $this->logger->debug("REST API > Request", $request->get_params());
            
            $requestData = isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] == 'application/json' ? apply_filters('mobbex_order_webhook',  json_decode(file_get_contents('php://input'), true)) : apply_filters('mobbex_order_webhook', $request->get_params());
            $postData    = !empty($requestData['data']) ? $requestData['data'] : [];
            $id          = $request->get_param('mobbex_order_id');
            $token       = $request->get_param('mobbex_token');

            $this->logger->debug('Mobbex Webhook: Formating transaction', compact('id', 'token', 'postData'));

            //order webhook filter
            $webhookData = \MobbexHelper::format_webhook_data($id, $postData);
            
            // Save transaction
            global $wpdb;
            $wpdb->insert($wpdb->prefix.'mobbex_transaction', $webhookData, \MobbexHelper::db_column_format($webhookData));

            // Try to process webhook
            $result = $this->process_webhook($id, $token, $webhookData);
            
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
            $this->logger->debug("REST API > Error", $e->getMessage());

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
        $status = isset($data['status_code']) ? $data['status_code'] : null;
        $order  = wc_get_order($order_id);

        $this->logger->debug('Mobbex Webhook: Processing data');

        if (!$status || !$order_id || !$token || !$this->helper->valid_mobbex_token($token))
            return $this->logger->debug('Mobbex Webhook: Invalid mobbex token or empty data');

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

        $source = json_decode($data['data'], true)['payment']['source'];
        $payment_method = $source['name'];

        // TODO: Check the Status and Make a better note here based on the last registered status
        $main_mobbex_note = 'ID de Operación Mobbex: ' . $data['payment_id'] . '. ';

        if (!empty($data['entity_uid'])) {
            $entity_uid = $data['entity_uid'];

            $mobbex_order_url = str_replace(['{entity.uid}', '{payment.id}'], [$entity_uid, $data['payment_id']], MOBBEX_COUPON);

            $order->update_meta_data('mobbex_coupon_url', $mobbex_order_url);
            $order->add_order_note('URL al Cupón: ' . $mobbex_order_url);
        }

        if (!empty($source['type']) && $source['type'] == 'card') {
            $mobbex_card_payment_info = $payment_method . ' ( ' . $source['number'] . ' )';
            $mobbex_card_plan = $source['installment']['description'] . '. ' . $source['installment']['count'] . ' Cuota/s' . ' de ' . $source['installment']['amount'];

            $order->update_meta_data('mobbex_card_info', $mobbex_card_payment_info);
            $order->update_meta_data('mobbex_plan', $mobbex_card_plan);

            $main_mobbex_note .= 'Pago realizado con ' . $mobbex_card_payment_info . '. ' . $mobbex_card_plan . '. ';
        } else {
            $main_mobbex_note .= 'Pago realizado con ' . $payment_method . '. ';
        }

        $order->add_order_note($main_mobbex_note);

        if (isset($data['risk_analysis']) && $data['risk_analisys'] > 0) {
            $order->add_order_note('El riesgo de la operación fue evaluado en: ' . $data['risk_analisys']);
            $order->update_meta_data('mobbex_risk_analysis', $data['risk_analisys']);
        }

        if (!empty($payment_method)) {
            $order->set_payment_method_title($payment_method . ' ' . __('a través de Mobbex'));
        }

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
     * Update order status using webhook formatted data.
     * 
     * @param WC_Order $order
     * @param array $data
     * 
     * @return bool Update result.
     */
    public function update_order_status($order, $data)
    {
        $helper = new \MobbexOrderHelper($order);

        // Complete payment if status was approved and the order is not paid
        if (in_array($data['status_code'], $helper->status_codes['approved']))
            return $order->is_paid() || $order->payment_complete($data['payment_id']);

        return $order->update_status(
            $helper->get_status_from_code($data['status_code']),
            $data['status_message']
        );
    }

    /**
     * Update order total paid using webhook formatted data.
     * 
     * @param WC_Order $order
     * @param array $data
     */
    public function update_order_total($order, $data)
    {
        if ($order->get_total() == $data['total'])
            return;

        // First remove previus fees
        $order->remove_order_items('fee');

        // Add a fee item to order with the difference
        $item = new \WC_Order_Item_Fee;
        $item->set_props([
            'name'   => $data['total'] > $order->get_total() ? 'Cargo financiero' : 'Descuento',
            'amount' => $data['total'] - $order->get_total(),
            'total'  => $data['total'] - $order->get_total(),
        ]);
        $order->add_item($item);

        // Recalculate totals
        $order->calculate_totals();
        $order->set_total($data['total']);
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
