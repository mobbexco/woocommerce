<?php

require_once 'utils.php';

class WC_Gateway_Mobbex extends WC_Payment_Gateway
{

    public function __construct()
    {

        $this->id = 'mobbex';

        $this->method_title = __('Mobbex', MOBBEX_WC_TEXT_DOMAIN);
        $this->method_description = __('Mobbex Payment Gateway redirects customers to Mobbex to enter their payment information.', MOBBEX_WC_TEXT_DOMAIN);

        // Icon
        $this->icon = apply_filters('mobbex_icon', plugin_dir_url(__FILE__) . 'icon.png');

        // Generate admin fields
        $this->init_form_fields();
        $this->init_settings();

        // Get variables
        $this->enabled = $this->get_option('enabled');

        // Internal Options
        $this->use_button = ($this->get_option('button') === 'yes');
        $this->test_mode = ($this->get_option('test_mode') === 'yes');

        // String variables
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');

        $this->api_key = $this->get_option('api-key');
        $this->access_token = $this->get_option('access-token');

        $this->error = false;
        if (empty($this->api_key) || empty($this->access_token)) {

            $this->error = true;
            MobbexGateway::notice('error', __('You need to specify an API Key and an Access Token.', MOBBEX_WC_TEXT_DOMAIN));

        }

        // Always Required
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);

        $this->debug(array(
            "enabled" => $this->enabled,
            "use_button" => $this->use_button,
            "test_mode" => $this->test_mode,
            "title" => $this->title,
            "description" => $this->description,
            "api_key" => $this->api_key,
            "access_token" => $this->access_token,
            "error" => $this->error,
        ), "Settings");

        // Only if the plugin is enabled
        if (!$this->error && $this->enabled === 'yes') {
            $this->debug([], "Adding actions");

            add_action('woocommerce_api_mobbex_webhook', [$this, 'mobbex_webhook']);
            add_action('woocommerce_api_mobbex_return_url', [$this, 'mobbex_return_url']);

            // If button is enabled show it
            if (false !== $this->use_button) {
                $this->debug([], "Adding actions for Button");

                add_action('woocommerce_after_checkout_form', [$this, 'display_mobbex_button']);

                add_action('wp_enqueue_scripts', [$this, 'payment_scripts']);
            }
        }

    }

    public function debug($log, $message = 'debug')
    {
        mobbex_debug($message, $log);
    }

    public function init_form_fields()
    {

        $this->form_fields = [

            'enabled' => [

                'title' => __('Enable/Disable', MOBBEX_WC_TEXT_DOMAIN),
                'type' => 'checkbox',
                'label' => __('Enable checking out with Mobbex.', MOBBEX_WC_TEXT_DOMAIN),
                'default' => 'yes',

            ],

            'title' => [

                'title' => __('Title', MOBBEX_WC_TEXT_DOMAIN),
                'type' => 'text',
                'description' => __('This title will be shown on user checkout.', MOBBEX_WC_TEXT_DOMAIN),
                'default' => __('Pay with Mobbex', MOBBEX_WC_TEXT_DOMAIN),
                'desc_tip' => true,

            ],

            'description' => [

                'title' => __('Description', MOBBEX_WC_TEXT_DOMAIN),
                'description' => __('This description will be shown on user checkout.', MOBBEX_WC_TEXT_DOMAIN),
                'type' => 'textarea',
                'default' => '',

            ],

            'api-key' => [

                'title' => __('API Key', MOBBEX_WC_TEXT_DOMAIN),
                'description' => __('Your Mobbex API key.', MOBBEX_WC_TEXT_DOMAIN),
                'type' => 'text',

            ],

            'access-token' => [

                'title' => __('Access Token', MOBBEX_WC_TEXT_DOMAIN),
                'description' => __('Your Mobbex access token.', MOBBEX_WC_TEXT_DOMAIN),
                'type' => 'text',

            ],

            'test_mode' => [

                'title' => __('Enable/Disable Test Mode', MOBBEX_WC_TEXT_DOMAIN),
                'type' => 'checkbox',
                'label' => __('Enable Test Mode.', MOBBEX_WC_TEXT_DOMAIN),
                'default' => 'no',

            ],

            'button' => [

                'title' => __('Enable/Disable Button', MOBBEX_WC_TEXT_DOMAIN),
                'type' => 'checkbox',
                'label' => __('Enable Mobbex Button experience.', MOBBEX_WC_TEXT_DOMAIN),
                'default' => 'yes',

            ],

        ];

    }

    public function process_admin_options()
    {
        $saved = parent::process_admin_options();

        return $saved;
    }

    public function process_payment($order_id)
    {
        $this->debug([], "Creating payment");

        if ($this->error) {
            return ['result' => 'error'];
        }

        global $woocommerce;
        $order = new WC_Order($order_id);

        $this->debug([
            "order_id" => $order_id,
        ], "Creating for Order");

        $return_url = $this->get_api_endpoint('mobbex_return_url', $order_id);
        $checkout_data = $this->get_checkout($order, $return_url);

        $this->debug([
            "return_url" => $return_url,
            "checkout_data" => $checkout_data,
        ], "Creating payment");

        if (empty($checkout_data)) {
            return ['result' => 'error'];
        }

        $order->update_status('pending', __('Awaiting Mobbex Webhook', MOBBEX_WC_TEXT_DOMAIN));

        if (false === $this->use_button) {
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
        $this->debug([
            "order" => $order,
            "return_url" => $return_url,
        ]);

        if (empty($order)) {
            die('No order was found');
        }

        // Get Domain to allow comm
        $site_url = site_url('', null);
        $this->debug($site_url);

        $domain = str_replace(["http://", "https://"], "", $site_url);

        $this->debug($domain);

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
                'webhook' => $this->get_api_endpoint('mobbex_webhook', $order->get_id()),
                'return_url' => $return_url,
                'test' => $this->test_mode,
                "options" => [
                    "button" => $this->use_button,
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

    public function get_api_endpoint($endpoint, $order_id)
    {
        $query = [
            'wc-api' => $endpoint,
            'mobbex_token' => $this->generate_token(),
        ];

        if ($order_id) {
            $query['mobbex_order_id'] = $order_id;
        }

        return add_query_arg($query, home_url('/'));
    }

    public function mobbex_webhook()
    {
        if (!$_POST['data']) {
            $this->debug('Mobbex send an invalid request body');
            die('Mobbex sent an invalid request body.');
        }

        $this->debug($_POST['data']);

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
            $order->update_status('on-hold', __('Awaiting payment', MOBBEX_WC_TEXT_DOMAIN));
        } else if ($status == 4 || $status >= 200 && $status < 400) {
            // Set as completed and reduce stock
            // Set Mobbex Order ID to be able to refund.
            // TODO: implement return
            $order->payment_complete($id);
        } else {
            $order->update_status('failed', __('Order failed', MOBBEX_WC_TEXT_DOMAIN));
        }

        die();

    }

    public function mobbex_return_url()
    {

        $status = $_GET['status'];
        $id = $_GET['mobbex_order_id'];
        $token = $_GET['mobbex_token'];

        $error = false;
        if (empty($status) || empty($id) || empty($token)) {
            $error = "No se pudo validar la transacción. Contacte con el administrador de su sitio";
        }

        if (!$this->valid_mobbex_token($token)) {
            $error = "Token de seguridad inválido.";
        }

        if (false !== $error) {
            return $this->_redirect_to_cart_with_error($error);
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
        if ($this->enabled === 'yes') {
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

        // If button is Disabled do not add the button
        if (false === $this->use_button) {
            return;
        }

        $order_url = home_url('/mobbex?wc-ajax=checkout');

        $this->debug($order_url);

        // let's suppose it is our payment processor JavaScript that allows to obtain a token
        wp_enqueue_script('mobbex-button', plugins_url('assets/js/mobbex.0.9.21.js', __FILE__), null, "0.9.22", false);

        // Inject our bootstrap JS to intercept the WC button press and invoke standard JS
        wp_register_script('mobbex-bootstrap', plugins_url('assets/js/mobbex.bootstrap.js', __FILE__), array('jquery'), "2.1.0", false);

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
        if (false === $this->use_button) {
            return;
        }

        ob_start();

        echo '
        <!-- Mobbex Button -->
        <div id="mbbx-button"></div>
        <!-- Mobbex Container -->
        <div id="mbbx-container"></div>
        ';

        return ob_get_clean();
    }

    private function _redirect_to_cart_with_error($error_msg)
    {
        wc_add_notice($error_msg, 'error');
        wp_redirect(wc_get_cart_url());

        return array('result' => 'error', 'redirect' => wc_get_cart_url());
    }
}
