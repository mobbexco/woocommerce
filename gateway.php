<?php

class WC_Gateway_Mobbex extends WC_Payment_Gateway
{

    public function __construct()
    {

        $this->id = 'mobbex';

        $this->method_title = __('Mobbex', 'mobbex-for-woocommerce');
        $this->method_description = __('Mobbex Payment Gateway redirects customers to Mobbex to enter their payment information.', 'mobbex-for-woocommerce');
        $this->icon = apply_filters('mobbex_icon', plugin_dir_url(__FILE__) . 'icon.png');

        $this->init_form_fields();
        $this->init_settings();

        $this->enabled = $this->get_option('enabled');
        $this->useButton = $this->get_option('button');

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');

        $this->api_key = $this->get_option('api-key');
        $this->access_token = $this->get_option('access-token');

        $this->error = false;
        if (empty($this->api_key) || empty($this->access_token)) {

            $this->error = true;
            MobbexGateway::notice('error', __('You need to specify an API Key and an Access Token.', 'mobbex-for-woocommerce'));

        }

        // Always Required
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);

        // Only if the plugin is enabled
        if (!$this->error && $this->enabled == 'yes') {
            add_action('woocommerce_api_mobbex_webhook', [$this, 'mobbex_webhook']);
            add_action('woocommerce_api_mobbex_return_url', [$this, 'mobbex_return_url']);

            add_action('woocommerce_after_checkout_form', [$this, 'display_mobbex_button']);

            add_action('wp_enqueue_scripts', [$this, 'payment_scripts']);
        }

    }

    public function debug($log)
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log($log);
        }
    }

    public function init_form_fields()
    {

        $this->form_fields = [

            'enabled' => [

                'title' => __('Enable/Disable', 'mobbex-for-woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable checking out with Mobbex.', 'mobbex-for-woocommerce'),
                'default' => 'yes',

            ],

            'title' => [

                'title' => __('Title', 'mobbex-for-woocommerce'),
                'type' => 'text',
                'description' => __('This title will be shown on user checkout.', 'mobbex-for-woocommerce'),
                'default' => __('Pay with Mobbex', 'mobbex-for-woocommerce'),
                'desc_tip' => true,

            ],

            'description' => [

                'title' => __('Description', 'mobbex-for-woocommerce'),
                'description' => __('This description will be shown on user checkout.', 'mobbex-for-woocommerce'),
                'type' => 'textarea',
                'default' => '',

            ],

            'api-key' => [

                'title' => __('API Key', 'mobbex-for-woocommerce'),
                'description' => __('Your Mobbex API key.', 'mobbex-for-woocommerce'),
                'type' => 'text',

            ],

            'access-token' => [

                'title' => __('Access Token', 'mobbex-for-woocommerce'),
                'description' => __('Your Mobbex access token.', 'mobbex-for-woocommerce'),
                'type' => 'text',

            ],

            'button' => [

                'title' => __('Enable/Disable Button', 'mobbex-for-woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable Mobbex Button experience.', 'mobbex-for-woocommerce'),
                'default' => 'yes',

            ],

        ];

    }

    public function process_payment($order_id)
    {
        if ($this->error) {
            return ['result' => 'error'];
        }

        global $woocommerce;
        $order = new WC_Order($order_id);

        $return_url = $this->get_api_endpoint('mobbex_return_url', $order);
        $checkout_data = $this->get_checkout($order, $return_url);

        if (empty($checkout_data)) {
            return ['result' => 'error'];
        }

        $order->update_status('pending', __('Awaiting Mobbex Webhook', 'mobbex-for-woocommerce'));

        if ($this->useButton == 'yes') {
            return [
                'result' => 'success',
                'data' => $checkout_data,
                'return_url' => $return_url,
            ];
        } else {
            $return_url = $checkout_data['url'];

            return [
                'result' => 'success',
                'redirect' => $return_url,
            ];
        }

    }

    public function get_checkout($order = null, $return_url = null)
    {
        if (empty($order)) {
            die('No order was found');
        }

        // Get Domain to allow comm
        $site_url = site_url('', null);
        $this->debug(print_r($site_url, true));

        $domain = str_replace(["http://", "https://"], "", $site_url);

        $this->debug(print_r($domain, true));

        // Create the Checkout
        $response = wp_remote_post(MOBBEX_CHECKOUT, [

            'headers' => [

                'cache-control' => 'no-cache',
                'content-type' => 'application/json',
                'x-api-key' => $this->api_key,
                'x-access-token' => $this->access_token,
            ],

            'body' => json_encode([

                'total' => $order->get_total(),
                'reference' => $order->get_id(),
                'description' => 'No description',
                'items' => $this->get_items($order),
                'webhook' => $this->get_api_endpoint('mobbex_webhook', $order),
                'return_url' => $return_url,
                "options" => [
                    "button" => $this->useButton == 'yes',
                    "domain" => $domain,
                ],

            ]),

            'data_format' => 'body',

        ]);

        if (!is_wp_error($response)) {

            $response = json_decode($response['body'], true);
            $data = $response['data'];

            if ($data) {
                return $data;
            }
        }

        return null;
    }

    public function get_items($order)
    {

        $items = [];

        foreach ($order->get_items() as $item) {

            $image = get_the_post_thumbnail_url($item->get_id(), 'thumbnail');
            $default_image = wc_placeholder_img_src('thumbnail');
            $image = $image ? $image : $default_image;

            $items[] = [

                'image' => $image,
                'quantity' => $item->get_quantity(),
                'description' => $item->get_name(),
                'total' => $item->get_total(),

            ];

        }

        return $items;

    }

    public function get_api_endpoint($endpoint, $order)
    {
        $query = ['wc-api' => $endpoint];

        if ($order) {

            $query['mobbex_order_id'] = $order->get_id();
            $query['mobbex_token'] = $this->generate_token();

        }

        return add_query_arg($query, home_url('/'));
    }

    public function mobbex_webhook()
    {
        if (!$_POST['data']) {
            $this->debug('Mobbex send an invalid request body');
            die('Mobbex sent an invalid request body.');
        }

        $this->debug(print_r($_POST['data'], true));

        $status = $_POST['data']['payment']['status']['code'];
        $id = $_REQUEST['mobbex_order_id'];
        $token = $_REQUEST['mobbex_token'];

        $this->debug($status);
        $this->debug($id);
        $this->debug($token);

        if (empty($status) || empty($id) || empty($token)) {
            $this->debug('Missing status, id, or token.');
            die('Missing status, id, or token.');
        }

        if (!$this->valid_mobbex_token($token)) {
            $this->debug('Invalid mobbex token.');
            die('Invalid mobbex token.');
        }

        $order = new WC_Order($id);
        $payment_method = $_POST['data']['payment']['source']['name'];

        if (!empty($payment_method)) {
            $order->set_payment_method_title($payment_method . ' ' . __('a través de Mobbex'));
        }

        // Check status and set
        if ($status == 2 || $status == 3) {
            $order->update_status('on-hold', __('Awaiting payment', 'mobbex-for-woocommerce'));
        } else if ($status == 4 || $status >= 200 && $status < 400) {
            // Set as completed and reduce stock
            // Set Mobbex Order ID to be able to refund.
            // TODO: implement return
            $order->payment_complete($id);
        } else {
            $order->update_status('failed', __('Order failed', 'mobbex-for-woocommerce'));
        }

        die();

    }

    public function mobbex_return_url()
    {

        $status = $_REQUEST['status'];
        $id = $_REQUEST['mobbex_order_id'];
        $token = $_REQUEST['mobbex_token'];

        if (empty($status) || empty($id) || empty($token)) {
            die('Missing status, id, or token.');
        }

        if (!$this->valid_mobbex_token($token)) {
            die('Invalid mobbex token.');
        }

        $order = new WC_Order($id);

        if ($status == 0 || $status >= 400) {
            // Try to restore the cart here
            $redirect = $order->get_cancel_order_url();
        } else if ($status == 2 || $status == 3 || $status == 4 || $status >= 200 && $status < 400) {
            // Redirect
            $redirect = $order->get_checkout_order_received_url();
        }

        wp_safe_redirect($redirect);
        die();
    }

    public function valid_mobbex_token($token)
    {
        return $token == $this->generate_token();
    }

    public function generate_token()
    {
        return md5($this->api_key . '|' . $this->access_token);
    }

    public function isReady()
    {
        if ('no' === $this->enabled) {
            return false;
        }

        if (empty($this->api_key) || empty($this->access_token)) {
            return false;
        }

        return true;
    }

    public function payment_scripts()
    {
        // we need JavaScript to process a token only on cart/checkout pages, right?
        if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order'])) {
            return;
        }

        // if our payment gateway is disabled, we do not have to enqueue JS too
        if (!$this->isReady()) {
            return;
        }

        $order_url = home_url('/mobbex?wc-ajax=checkout');

        $this->debug($order_url);

        // let's suppose it is our payment processor JavaScript that allows to obtain a token
        wp_enqueue_script('mobbex-button', plugins_url('assets/js/mobbex.0.9.20.js', __FILE__), null, "0.9.20", false);

        // Inject our bootstrap JS to intercept the WC button press and invoke standard JS
        wp_register_script('mobbex-bootstrap', plugins_url('assets/js/mobbex.bootstrap.js', __FILE__), array('jquery'), "2.0.3", false);

        $mobbex_data = array(
			'order_url' => $order_url,
        );
        
        wp_localize_script('mobbex-bootstrap', 'mobbex_data', $mobbex_data);
        wp_enqueue_script('mobbex-bootstrap');
    }

    /**
     * Display Mobbex button on the cart page.
     */
    public function display_mobbex_button()
    {
        ?>
        <!-- Mobbex Button -->
        <div id="mbbx-button"></div>
        <!-- Mobbex Container -->
		<div id="mbbx-container"></div>
		<?php
}
}
