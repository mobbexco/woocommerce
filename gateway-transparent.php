<?php

/**
 * Mobbex Transparent Payment Gateway
 *
 * Getaway to process payment by a form without leave site
 */
class WC_Gateway_Mobbex_Transparent extends WC_Payment_Gateway
{
    public $supports = array(
        'products',
        'refunds',
    );

    /** @var \Mobbex\WP\Checkout\Model\Config */
    public $config;

    /** @var \Mobbex\WP\Checkout\Model\Logger */
    public $logger;

    /** @var \Mobbex\WP\Checkout\Model\Helper */
    public $helper;

    /** @var string */
    public $id;

    /** @var bool */
    public $enabled;

    /** @var string */
    public $logo;

    /** @var string */
    public $title;

    /** @var string */
    public $description;

    /** @var bool */
    public $checkout_banner;

    /** @var string */
    public $method_title;

    /** @var string */
    public $method_description;

    /** @var bool */
    public $has_fields;

    public function __construct()
    {
        $this->id = MOBBEX_WC_GATEWAY_TRANSPARENT_ID;

        $this->config = new \Mobbex\WP\Checkout\Model\Config();
        $this->helper = new \Mobbex\WP\Checkout\Model\Helper();
        $this->logger = new \Mobbex\WP\Checkout\Model\Logger();

        if (!$this->is_ready()) {
            $this->logger->log('debug', '[Mobbex Transparent] Gateway > Not ready - returning early');
            $this->logger->log('debug', '[Mobbex Transparent] Gateway > Config values: ' . print_r([
                'transparent' => $this->config->transparent ?? 'NOT SET',
                'api_key' => !empty($this->config->api_key) ? 'SET' : 'NOT SET',
                'access_token' => !empty($this->config->access_token) ? 'SET' : 'NOT SET',
            ], true));
            return;
        }
        $this->logger->log('debug', '[Mobbex Transparent] Gateway > Ready and initializing');

        $this->enabled = $this->config->transparent;

        // String variables. That's used on checkout view
        $this->logo    = $this->config->transparent_logo;
        $this->title   = $this->config->transparent_title;

        $this->method_title = __('Mobbex Transparent', 'mobbex-for-woocommerce');
        $this->method_description = __('Allows payments in site without leaving the site.', 'mobbex-for-woocommerce');

        // Mostrar formulario de pago en checkout
        $this->has_fields = true;

        // Hook para guardar opciones del admin
        add_action(
            'woocommerce_update_options_payment_gateways_' . $this->id,
            ['\WC_Gateway_Mobbex', 'process_admin_options']
        );

        // Log de inicialización
        $this->logger->log('debug', '[Mobbex Transparent] Gateway > Initialized');
    }

    /**
     * Get Intent Token for actual checkout
     *
     * @return string Intent token o empty string on error
     */
    public function get_intent_token()
    {
        $session_token = WC()->session->get('mobbex_transparent_intent_token');

        if ($session_token) 
            return $session_token;

        // Get intent token from temporary checkout
        $intent_token = $this->create_intent_token();

        if (empty($intent_token)) {
            $this->logger->log(
                'error',
                '[Mobbex Transparent] Gateway > get_intent_token | Error: empty intent token'
            );
            return '';
        }

        WC()->session->set('mobbex_transparent_intent_token', $intent_token);
        return $intent_token;
    }

    /**
     * Create intent token from Mobbex API
     *
     * @return string Intent token or empty string in error
     */
    protected function create_intent_token()
    {
        $cart = WC()->cart;
        if (!$cart) {
            $this->logger->log(
                'error',
                '[Mobbex Transparent] Gateway > create_intent_token > Cart not found',
                []
            );
            return false;
        }

        try {
            $order_helper = new \Mobbex\WP\Checkout\Helper\Cart($cart);
            $response     = $order_helper->create_checkout();

            if (!$response['intent']['token'])
                throw new \Exception("[Mobbex Transparent] Gateway > create_intent_token Error: couldn't get intent token");

            return $response['intent']['token'];
        } catch (\Exception $e) {
            $this->logger->log('error', '[Mobbex Transparent] Gateway > create_intent_token > create_intent_token failed', [
                'error' => $e->getMessage()
            ]);
            return '';
        }
    }

    /**
     * Override Gateway function to process transparent payment
     * Payment data is received from transparent form after order placing
     * 
     * @param string $order_id
     * @return array
     */
    public function process_payment($order_id)
    {
        try {
            $this->logger->log(
                'debug', 
                '[Mobbex Transparent] Gateway > process_payment for order #' . $order_id
            );
            $order = wc_get_order($order_id);
    
            $request_data = json_decode(file_get_contents('php://input'), true);
            $block_data   = $request_data['payment_data'] ?? [];

            $card = $this->normalize_block_data($block_data);
            $this->validate_body($card);
    
            // Create checkout from order
            $order_helper = new \Mobbex\WP\Checkout\Helper\Order($order);
            $checkout     = $order_helper->create_checkout();

            $this->logger->log(
                'debug', 
                '[Mobbex Transparent] Gateway > process_payment | Checkout response', 
                $checkout
            );
            if (!$checkout || !isset($checkout['intent']['token']))
                throw new \Mobbex\Exception('Error on token creation');
    
            $order->update_status(
                'pending', 
                __('Awaiting Mobbex Card Tokenization', 'mobbex-for-woocommerce')
            );

            $token = $this->tokenize_card(
                $checkout['intent']['token'],
                $card['number'],
                $card['expiry'],
                $card['cvv'],
                $card['name'],
                $card['identification']
            );
            if (empty($token))
                throw new \Mobbex\Exception(
                    '[Mobbex Transparent] Gateway > process_payment | Error on token creation'
                );

            $res = $this->process(
                $checkout['intent']['token'],
                $token,
                $card['installments']
            );

            if (empty($res) || empty($res['status']['code']))
                throw new \Mobbex\Exception(
                    '[Mobbex Transparent] Gateway > Error on operation process. Empty response', 0, $res
                );

            if (!in_array($res['status']['code'], ['3', '100', '200']))
                throw new \Mobbex\Exception(
                    '[Mobbex Transparent] Gateway > Operation process with error code', 0, $res
                );

            $redirect = $this->helper->get_api_endpoint(
                'mobbex_return_url', 
                $order_id, 
                $res['status']['code']
            );

            $result = [
                'result'      => 'success',
                'data'        => $checkout,
                'redirect'    => $redirect,
                'checkout_id' => $checkout['id'],
            ];
    
            // Make sure to use json in pay for order page
            if (isset($_GET['pay_for_order']))
                wp_send_json($result) && exit;
    
            return $result;
        } catch (\Exception $e) {
            $this->logger->log('error', 'Transparent Gateway > process_payment', $e->getMessage());
            return new \WP_Error('error', $e->getMessage());
        }
    }

    /**
     * Process transparent payment via Mobbex API
     *
     * @param string $intent_token
     * @param string $card_token
     * @param string $installment
     *
     * @return array|false Payment result or false on error
     */
    private function process($intent_token, $card_token, $installment)
    {
        $response = \Mobbex\Api::request([
            'method' => 'POST',
            'uri'    => "operations/$intent_token",
            'body'   => [
                'source'      => $card_token,
                'installment' => $installment,
            ]
        ]);

        if (empty($response))
            throw new \Mobbex\Exception(
                'Error on operation process. Empty response',
                 0,
                 $response
            );

        return $response;
    }


    /**
     * Override Gateway process refund.
     * Process refund using Mobbex Gateway method
     *
     * @param int $order_id ID de la orden
     * @param float $amount Monto a reembolsar
     * @param string $reason Razón del reembolso
     * 
     * @return bool|\WP_Error
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        $mobbex_gateway = new \WC_Gateway_Mobbex();
        try {
            if (method_exists($mobbex_gateway, 'process_refund'))
                return $mobbex_gateway->process_refund($order_id, $amount, $reason);

            throw new \Mobbex\Exception(
                "[Mobbex Transparent] Gateway > process_refund Error: method not found"
            );
        } catch (\Exception $e) {
            $this->logger->log('error', 'Transparent Gateway > process_refund', $e->getMessage());
            return new \WP_Error('error', $e->getMessage());
        }
    }

    /**
     * Validate request body
     * 
     * @param array $body Request body
     * 
     * @throws \Exception
     */
    private function validate_body($body)
    {
        if (empty($body) || !is_array($body))
            throw new \Exception('Invalid request body.');

        $card = $this->normalize_block_data($body);

        $cvv            = isset($card['cvv']) ? $card['cvv'] : null;
        $name           = isset($card['name']) ? $card['name'] : null;
        $number         = isset($card['number']) ? $card['number'] : null;
        $expiry         = isset($card['expiry']) ? $card['expiry'] : null;
        $installments   = isset($card['installments']) ? $card['installments'] : null;
        $identification = isset($card['identification']) ? $card['identification'] : null;

        // Falsy check
        if (!$number || !$expiry || !$cvv || !$name || !$identification || !$installments)
            throw new \Exception('Missing required fields.');

        // Type check
        if (!is_string($number) || !is_string($expiry) || !is_string($cvv) || !is_string($name) || !is_string($identification) || !is_string($installments))
            throw new \Exception('All fields must be strings.');

        // Card number
        if (strlen($number) < 15 || strlen($number) > 19)
            throw new \Exception('Number must be at least 15 and not more than 19 characters long.');

        if (!preg_match('/^[0-9]+$/', $number))
            throw new \Exception('Number must contain only numbers.');

        // Card expiry
        if (strlen($expiry) < 4 || strlen($expiry) > 5)
            throw new \Exception('Expiry must be at least 4 and not more than 5 characters long.');

        if (!preg_match('/^(0[1-9]|1[0-2])\/?([0-9]{2})$/', $expiry))
            throw new \Exception('Expiry must be in MM/YY format.');

        // Card CVV
        if (strlen($cvv) < 3 || strlen($cvv) > 4)
            throw new \Exception('CVV must be at least 3 and not more than 4 characters long.');

        if (!preg_match('/^[0-9]+$/', $cvv))
            throw new \Exception('CVV must contain only numbers.');

        // Holder name
        if (strlen($name) < 3 || strlen($name) > 50)
            throw new \Exception('Name must be at least 3 and not more than 50 characters long.');

        if (strlen($identification) < 5 || strlen($identification) > 9)
            throw new \Exception('Identification must be at least 5 and not more than 9 characters long.');

        if (!preg_match('/^[0-9]+$/', $identification))
            throw new \Exception('Identification must contain only numbers.');
    }

    /** 
    * Normalize WooCommerce Blocks response data structure
    *
    * @param array $data card data
    *
    * @return array $data formated card data
    */
    private function normalize_block_data($data)
    {
        if (isset($data[0]['key']))
            return array_column($data, 'value', 'key');

        return $data;
    }
    
    /**
     * Tokenize card number with Mobbex API.
     * 
     * @param string $it Checkout token
     * @param string $number Card number
     * @param string $expiry Card expiry in MM/YY format
     * @param string $cvv Card CVV
     * @param string $name Card holder name
     * @param string $identification Card holder identification
     * 
     * @return array Token response data.
     * 
     * @throws \Exception
     */
    private function tokenize_card($intent_token, $number, $expiry, $cvv, $name, $identification)
    {
        $response = \Mobbex\Api::request([
            'method' => 'POST',
            'uri'    => "sources/token/$intent_token",
            'raw'    => true,
            'body'   => [
                'source' => [
                    'card' => [
                        'number' => $number,
                        'identification' => $identification,
                        'cvv' => $cvv,
                        'name' => $name,
                        'month' => explode('/', $expiry)[0],
                        'year' => explode('/', $expiry)[1],
                    ],
                ],
            ]
        ]);

        if (empty($response['data']))
            throw new \Mobbex\Exception(sprintf(
                'Mobbex request error #%s: %s %s',
                isset($response['code']) ? $response['code'] : 'NOCODE',
                isset($response['error']) ? $response['error'] : 'NOERROR',
                isset($response['status_message']) ? $response['status_message'] : 'NOMESSAGE'
            ));

        return $response['data']['token'];
    }

    /**
     * Evaluate configuration to check if Mobbex Transparent Gateway is ready
     * 
     * @return bool
     */
    private function is_ready()
    {
        return (
            $this->config->transparent === 'yes' 
            && !empty($this->config->api_key) 
            && !empty($this->config->access_token)
        );
    }
}
