<?php

namespace Mobbex\WP\Checkout\Controller;

final class Payment
{
    /** @var \Mobbex\WP\Checkout\Model\Config */
    public $config;
    
    /** @var \Mobbex\WP\Checkout\Model\Logger */
    public $logger;

    /** @var \Mobbex\WP\Checkout\Model\Helper */
    public $helper;

    public function __construct()
    {
        $this->config = new \Mobbex\WP\Checkout\Model\Config();
        $this->helper = new \Mobbex\WP\Checkout\Model\Helper();
        $this->logger = new \Mobbex\WP\Checkout\Model\Logger();

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
        
        if (empty($status) || empty($id) || empty($token)){
            $message = "No se pudo validar la transacción. Contacte con el administrador de su sitio";
            $error   = apply_filters('mbbx_custom_message', $message, $status);
        }
        if (!\Mobbex\Repository::validateToken($token))
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
        if ($this->config->error_redirection)
            $error_msg= 'Transacción Fallida. Redirigido a ruta configurada.';

        $route = $this->config->error_redirection ? home_url('/' . $this->config->error_redirection) : wc_get_cart_url();

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
            $token    = $request->get_param('mobbex_token');
            $orderId  = $request->get_param('mobbex_order_id');
            $postData = apply_filters('mobbex_order_webhook', json_decode(file_get_contents('php://input') ?: '', true));

            if (!$token || !\Mobbex\Repository::validateToken($token))
                throw new \Exception('Invalid token provided.');

            if (empty($postData['data']) || empty($postData['type']))
                throw new \Exception('Empty data or type provided.');

            $this->logger->log('debug', "Processing webhook for order $orderId", $postData);

            switch ($postData['type']) {
                case 'checkout':
                    $result = $this->process_checkout_webhook($orderId, $postData);
                    break;

                case 'checkout:expired':
                    $result = $this->process_checkout_webhook($orderId, $postData);
                    break;

                default:
                    $result = apply_filters('mobbexExternalWebhookProcess', 'Unsupported webhook type', $postData, $request);
                    break;
            }
        } catch (\Exception $e) {
            http_response_code(500);

            $orderId = isset($orderId) ? $orderId : 0;
            $result  = "Error processing webhook for order $orderId: " . $e->getMessage();
        } finally {
            $this->logger->log(
                isset($e) ? 'error' : 'debug', "Webhook processed for order $orderId: $result"
            );
        }

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
    }

    /**
     * Process & store the data of the Mobbex webhook.
     * 
     * @param int $order_id
     * @param string $token
     * @param array $data
     * 
     * @return string
     */
    public function process_checkout_webhook($order_id, $postData)
    {
        global $wpdb;

        $data   = \Mobbex\WP\Checkout\Model\Helper::format_webhook_data($order_id, $postData['data']);
        $status = isset($data['status_code']) ? $data['status_code'] : null;
        $order  = wc_get_order($order_id);

        if (!$status || !$order_id)
            throw new \Exception('Invalid data received.');

        if ($this->config->process_webhook_retries != 'yes' && $this->is_request_duplicated($data))
            return 'Duplicated Request Detected';

        // Avoid 3xx status codes
        if ($status > 299 && $status < 400)
            return 'Ignored 3xx status code';

        // Save transaction
        $wpdb->insert($wpdb->prefix.'mobbex_transaction', $data, \Mobbex\WP\Checkout\Model\Helper::db_column_format($data));
        $data['id'] = $wpdb->insert_id;

        // Catch refunds webhooks
        if ($status == 602 || $status == 605) {
            $refundRes = $this->refund_order($data);

            if (is_wp_error($refundRes))
                throw new \Exception("Error processing refund: " . $refundRes->get_error_message());

            return 'Refund processed successfully';
        }

        // Bypass any child webhook (except refunds)
        if ($data['parent'] != 'yes')
            return $this->add_child_note($order, $data) ? 'Child note added' : 'Failed to add child note';

        // Exit if it is a expired operation and the order has already been paid
        if ($order->is_paid() && $status == 401)
            return 'Operation expired and order already paid';

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

        if (isset($data['risk_analysis']) && $data['risk_analysis'] > 0) {
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
        do_action('mobbex_webhook_process', $order_id, $postData['data']);

        return 'Webhook processed successfully';
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
        $helper = new \Mobbex\WP\Checkout\Helper\Order($order);
        $status = $helper->get_status_from_code($data['status_code']);
        
        // Try to complete payment if status was approved
        if (in_array($data['status_code'], $helper->status_codes['approved'])) {
            // If is configured a paid status, and is not paid yet complete payment and return
            if (in_array($status, $this->get_paid_statuses()))
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
        if ($order->get_total() == $data['total'])
            return;

        // Remove previus fees and recalculate totals to get the original order total (do not change the order)
        foreach ($order->get_items('fee') as $item_id => $fee_item) {
            if (in_array($fee_item->get_name(), ['Cargo financiero', 'Descuento financiero']))
                $order->remove_item($item_id);
        }
        $order->calculate_totals();

        $order_total = $order->get_total();

        // Create a fee item with the diff amount
        $item = new \WC_Order_Item_Fee;
        $item->set_props([
            'name'   => $data['total'] > $order_total ? 'Cargo financiero' : 'Descuento financiero',
            'amount' => $data['total'] - $order_total,
            'total'  => $data['total'] - $order_total,
        ]);

        $order->add_item($item);
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

    /**
     * Check if it is a duplicated request locking process execution.
     * 
     * @param array $transaction Transaction data with the insert id in `id` position.
     * 
     * @return bool True if is duplicated.
     */
    public function is_request_duplicated($transaction)
    {
        return $this->sleep_process(
            $transaction['id'],
            50000, // 50 ms
            10000, //10 ms
            function() use($transaction) {
                return !empty($this->get_duplicated_transactions($transaction));
            }
        );
    }

    /**
     * Sleep the execution until the callback condition is met or the time runs out.
     * 
     * @param int $max_time Max sleep time in microseconds.
     * @param int $interval Interval in microseconds to awake and test condition.
     * @param callable $condition The condition to check each cicle.
     * 
     * @return bool Last condition callback result.
     */
    public function sleep_process($id, $max_time, $interval, $condition)
    {
        $codition_result = $condition();

        while ($max_time > 0 && !$codition_result) {
            usleep($interval);
            $max_time -= $interval;
            $codition_result = $condition();
        }

        return $codition_result;
    }

    /**
     * Retrieve all duplicated transactions from db.
     * 
     * @param array $transaction Transaction data.
     * 
     * @return array A list of rows.
     */
    public function get_duplicated_transactions($transaction)
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT `id` FROM `{$wpdb->prefix}mobbex_transaction` WHERE `id` < %d AND `data` = %s LIMIT 1",
            $transaction['id'],
            $transaction['data']
        )) ?: [];
    }

    /**
     * Get configured statuses as paid statuses.
     * 
     * @return array
     */
    public function get_paid_statuses() {
        //Add the mobbex paid statuses
        $paid_statuses= !empty($this->config->paid_statuses) ? $this->config->paid_statuses : [];

        $mobbex_paid_statuses = array_map(function ($status) {
            return str_replace('wc-', '', $status);
        }, $paid_statuses);

        //return statuses
        return array_unique(array_merge(wc_get_is_paid_statuses(), $mobbex_paid_statuses));
    }
}
