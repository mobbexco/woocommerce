<?php

require_once 'includes/utils.php';

class WC_Gateway_Mobbex extends WC_Payment_Gateway
{
    public $supports = array(
        'products',
        'refunds',
    );

    /** @var MobbexHelper */
    public $helper;
    
    /** @var MobbexLogger */
    public $logger;

    public function __construct()
    {
        $this->id     = MOBBEX_WC_GATEWAY_ID;
        $this->helper = new MobbexHelper();
        $this->logger = new MobbexLogger();

        // String variables. That's used on checkout view
        $this->icon        = apply_filters('mobbex_icon', plugin_dir_url(__FILE__) . 'icon.png');
        $this->title       = $this->helper->settings['title'];
        $this->description = $this->helper->settings['description'];

        $this->method_title       = 'Mobbex';
        $this->method_description = __('Mobbex Payment Gateway redirects customers to Mobbex to enter their payment information.', 'mobbex-for-woocommerce');

        // Generate admin fields
        $this->init_form_fields();
        $this->init_settings();

        // Internal Options
        $this->use_button = ($this->get_option('button') === 'yes');
        $this->test_mode  = ($this->get_option('test_mode') === 'yes');
        $this->use_wallet = ($this->get_option('wallet') === 'yes');

        // Enable or Disable financial information in products
        $this->financial_info_active = ($this->get_option('financial_info_active') === 'yes');

        // Theme
        $this->checkout_title            = $this->get_option('checkout_title');
        $this->checkout_logo             = $this->get_option('checkout_logo');
        $this->checkout_theme            = $this->get_option('checkout_theme');
        $this->checkout_background_color = $this->get_option('checkout_background_color');
        $this->checkout_primary_color    = $this->get_option('checkout_primary_color');

        // DNI fields
        $this->custom_dni = $this->get_option('custom_dni');
        $this->own_dni    = ($this->get_option('own_dni') === 'yes');

        // Reseller ID
        $this->reseller_id = $this->get_option('reseller_id');

        // String variables
        $this->title       = $this->get_option('title');
        $this->description = $this->get_option('description');

        $this->api_key      = $this->get_option('api-key');
        $this->access_token = $this->get_option('access-token');

        // Always Required
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);

    }

    /**
     * Define form fields of setting page.
     * 
     */
    public function init_form_fields()
    {
        $this->form_fields = include 'includes/config-options.php';
    }

    public function process_admin_options()
    {
        $saved = parent::process_admin_options();

        // Both fields cannot be filled at the same time
        if ($this->get_option('own_dni') === 'yes' && $this->get_option('custom_dni') != '') {
            $this->update_option('custom_dni');
        }

        return $saved;
    }

    /**
     * Process the payment & return the checkout data to mobbex-bootstrap.js 
     * 
     * @param string $order_id
     * @return array
     * 
     */
    public function process_payment($order_id)
    {
        $this->logger->debug('Creating payment', compact('order_id'));

        if (!$this->helper->isReady())
            return ['result' => 'error'];

        $order = wc_get_order($order_id);

        // Create checkout from order
        $order_helper  = new MobbexOrderHelper($order);
        $checkout_data = $order_helper->create_checkout();

        $this->logger->debug('Checkout response', $checkout_data);

        if (!$checkout_data)
            return ['result' => 'error'];

        $order->update_status('pending', __('Awaiting Mobbex Webhook', 'mobbex-for-woocommerce'));

        $result = [
            'result'     => 'success',
            'data'       => $checkout_data,
            'return_url' => $this->helper->get_api_endpoint('mobbex_return_url', $order_id),
            'redirect'   => $this->helper->settings['button'] == 'yes' ? false : $checkout_data['url'],
        ];

        // Make sure to use json in pay for order page
        if (isset($_GET['pay_for_order']))
            wp_send_json($result) && exit;

        return $result;
    }

    public function process_refund($order_id, $amount = null, $reason = '')
    {
        $payment_id = get_post_meta($order_id, 'mobbex_payment_id', true);

        if (!$payment_id)
            return false;

        return $this->helper->api->request([
            'method' => 'POST',
            'uri'    => "operations/$payment_id/refund",
            'body'   => ['total' => floatval($amount)],
        ]);
    }
}
