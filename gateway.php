<?php
require_once 'utils.php';

class WC_Gateway_Mobbex extends WC_Payment_Gateway
{

    public $supports = array(
        'products',
        'refunds',
    );

    public function __construct()
    {
        $this->id = MOBBEX_WC_GATEWAY_ID;

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

        // New Webhook
        $this->use_webhook_api = ($this->get_option('use_webhook_api') === 'yes');

        // Theme
        $this->checkout_title = $this->get_option('checkout_title');
        $this->checkout_logo = $this->get_option('checkout_logo');
        $this->checkout_theme = $this->get_option('checkout_theme');
        $this->checkout_background_color = $this->get_option('checkout_background_color');
        $this->checkout_primary_color = $this->get_option('checkout_primary_color');

        // DNI fields
        $this->custom_dni = $this->get_option('custom_dni');
        $this->own_dni = ($this->get_option('own_dni') === 'yes');

        // Reseller ID
        $this->reseller_id = $this->get_option('reseller_id');

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

            add_action('woocommerce_api_mobbex_return_url', [$this, 'mobbex_return_url']);

            if ($this->use_webhook_api === false) {
                $this->debug([], "Registering WC Controller");

                add_action('woocommerce_api_mobbex_webhook', [$this, 'mobbex_webhook']);
            }

            // If button is enabled show it
            if ($this->use_button) {
                $this->debug([], "Adding actions for Button");

                add_action('woocommerce_after_checkout_form', [$this, 'display_mobbex_button']);

                add_action('wp_enqueue_scripts', [$this, 'payment_scripts']);
            }
        }

        if ($this->own_dni == 'yes' && !function_exists('mobbex_dni_woocommerce_billing_fields') && !function_exists('mobbex_dni_display_admin_order_meta')) {

            add_filter('woocommerce_billing_fields', 'mobbex_dni_woocommerce_billing_fields');

            function mobbex_dni_woocommerce_billing_fields($fields)
            {

                $fields['billing_dni'] = array(
                    'label' => __('DNI', 'woocommerce'), // Add custom field label
                    'placeholder' => _x('Ingrese su DNI', 'placeholder', 'woocommerce'), // Add custom field placeholder
                    'required' => true, // if field is required or not
                    'clear' => false, // add clear or not
                    'type' => 'text', // add field type
                    'class' => array('my-dni'), // add class name
                );

                return $fields;
            }

            add_action('woocommerce_admin_order_data_after_billing_address', 'mobbex_dni_display_admin_order_meta', 10, 1);

            function mobbex_dni_display_admin_order_meta($order)
            {
                echo '<p><strong>' . __('DNI') . ':</strong> ' . get_post_meta($order->get_id(), '_billing_dni', true) . '</p>';
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
                'default' => 'no',

            ],

            'use_webhook_api' => [

                'title' => __('Use new WebHook API', MOBBEX_WC_TEXT_DOMAIN),
                'type' => 'checkbox',
                'label' => __('Use the WebHook by API instead of old Controller. Permalinks must be Active to use it Safely', MOBBEX_WC_TEXT_DOMAIN),
                'default' => 'no',

            ],

            'checkout_theme' => [

                'title' => __('Checkout Theme', MOBBEX_WC_TEXT_DOMAIN),
                'description' => __('You can customize your Checkout Theme from here.', MOBBEX_WC_TEXT_DOMAIN),
                'type' => 'select',
                'options' => [
                    'light' => __('Light Theme', MOBBEX_WC_TEXT_DOMAIN),
                    'dark' => __('Dark Theme', MOBBEX_WC_TEXT_DOMAIN),
                ],
                'default' => 'light',

            ],

            'checkout_title' => [

                'title' => __('Checkout Title', MOBBEX_WC_TEXT_DOMAIN),
                'description' => __('You can customize your Checkout Title from here.', MOBBEX_WC_TEXT_DOMAIN),
                'type' => 'text',
                'default' => '',

            ],

            'checkout_logo' => [

                'title' => __('Checkout Logo URL', MOBBEX_WC_TEXT_DOMAIN),
                'description' => __('You can customize your Checkout Logo from here. The logo URL must be HTTPS and must be only set if required. If not set the Logo set on Mobbex will be used. Dimensions: 250x250 pixels', MOBBEX_WC_TEXT_DOMAIN),
                'type' => 'text',
                'default' => '',

            ],

            'checkout_background_color' => [

                'title' => __('Checkout Background Color', MOBBEX_WC_TEXT_DOMAIN),
                'description' => __('You can customize your Checkout Background Color from here.', MOBBEX_WC_TEXT_DOMAIN),
                'type' => 'text',
                'class' => 'colorpick',
                'default' => '#ECF2F6',

            ],

            'checkout_primary_color' => [

                'title' => __('Checkout Primary Color', MOBBEX_WC_TEXT_DOMAIN),
                'description' => __('You can customize your Checkout Primary Color for Buttons and TextFields from here.', MOBBEX_WC_TEXT_DOMAIN),
                'type' => 'text',
                'class' => 'colorpick',
                'default' => '#6f00ff',

            ],

            'custom_dni' => [

                'title' => __('Use custom DNI field', MOBBEX_WC_TEXT_DOMAIN),
                'description' => __('If you ask for DNI field on checkout please provide the custom field.', MOBBEX_WC_TEXT_DOMAIN),
                'type' => 'text',
                'default' => '',

            ],

            'own_dni' => [

                'title' => __('Add DNI field', MOBBEX_WC_TEXT_DOMAIN),
                'description' => __('Add DNI field on checkout.', MOBBEX_WC_TEXT_DOMAIN),
                'type' => 'checkbox',
                'default' => '',

            ],

            'reseller_id' => [

                'title' => __('Reseller ID', MOBBEX_WC_TEXT_DOMAIN),
                'description' => __('You can customize your Reseller ID from here. This field is optional and must be used only if was specified by the main seller.', MOBBEX_WC_TEXT_DOMAIN),
                'type' => 'text',
                'default' => '',

            ],

        ];

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

    public function process_payment($order_id)
    {
        $this->debug([], "Creating payment");

        if ($this->error) {
            return ['result' => 'error'];
        }

        global $woocommerce;
        $order = wc_get_order($order_id);

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

        if ($this->use_button) {
            $this->debug([
                'result' => 'success',
                'data' => $checkout_data,
                'return_url' => $return_url,
            ], "Reply for button");

            return [
                'result' => 'success',
                'data' => $checkout_data,
                'return_url' => $return_url,
                'redirect' => false,
            ];
        } else {
            $return_url = $checkout_data['url'];

            return [
                'result' => 'success',
                'redirect' => $return_url,
            ];
        }

    }

    public function getPlatform()
    {
        return [
            "name" => "woocommerce",
            "version" => MOBBEX_VERSION,
        ];
    }

    public function getTheme()
    {
        $theme = [
            "type" => $this->checkout_theme,
            "background" => $this->checkout_background_color,
            "colors" => [
                "primary" => $this->checkout_primary_color,
            ],
        ];

        if ($this->checkout_title !== '' || $this->checkout_logo !== '') {
            $theme = array_merge($theme, [
                "header" => [],
            ]);
        }

        if ($this->checkout_title !== '') {
            $theme['header'] = array_merge($theme['header'], [
                "name" => $this->checkout_title,
            ]);
        }

        if ($this->checkout_logo !== '') {
            $theme['header'] = array_merge($theme['header'], [
                "logo" => $this->checkout_logo,
            ]);
        }

        $this->debug([
            "theme" => $theme,
        ]);

        return $theme;
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
        
        // Get Customer data
        $current_user = wp_get_current_user();
        $customer = array(
            "name" => $current_user->display_name ? : $order->get_formatted_billing_full_name(),
            "email" => $current_user->user_email ? : $order->get_billing_email(),
            'phone' => get_user_meta($current_user->ID,'phone_number',true) ? : $order->get_billing_phone(),
            "uid" => $current_user->ID ? : null,
        );
        if (!empty($this->custom_dni)) {
            $customer['dni'] = get_post_meta($order->get_id(), $this->custom_dni, true);
        } else {
            $customer['dni'] = get_post_meta($order->get_id(), '_billing_dni', true);
        }
        
        // Get Reference
        $reference = $order->get_id();
        if (isset($this->reseller_id) && $this->reseller_id !== '') {
            $reference = $this->reseller_id . "-" . $reference;
        }

        $checkout_body = [
            'total' => $order->get_total(),
            'reference' => $reference,
            'description' => 'Orden #' . $order->get_id(),
            'items' => $this->get_items($order),
            'webhook' => $this->get_api_endpoint('mobbex_webhook', $order->get_id()),
            'return_url' => $return_url,
            'test' => $this->test_mode,
            "options" => [
                "button" => $this->use_button,
                "domain" => $domain,
                "theme" => $this->getTheme(),
                "redirect" => array(
                    "success" => true,
                    "failure" => false,
                ),
                "platform" => $this->getPlatform(),
            ],
            'customer' => $customer,
            'timeout' => 5,
        ];

        $this->debug([
            "checkout_body" => $checkout_body,
        ]);
        
        // Installment filter
        if (!empty($this->get_installments($order))) {
            $checkout_body['installments'] = $this->get_installments($order);
        }

        if (defined('MOBBEX_CHECKOUT_INTENT')) {
            $checkout_body['intent'] = MOBBEX_CHECKOUT_INTENT;
        }

        // Create the Checkout
        $response = wp_remote_post(MOBBEX_CHECKOUT, [

            'headers' => [

                'cache-control' => 'no-cache',
                'content-type' => 'application/json',
                'x-api-key' => $this->api_key,
                'x-access-token' => $this->access_token,
            ],

            'body' => json_encode($checkout_body),

            'data_format' => 'body',

        ]);

        $this->debug($response);

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

            $product = wc_get_product($item->get_product_id());
            $image_id = $product->get_image_id();
            $image = wp_get_attachment_image_url($image_id, 'thumbnail');
            $default_image = wc_placeholder_img_src('thumbnail');
            $image = $image ? $image : $default_image;

            $items[] = [

                'image' => $image,
                'quantity' => $item->get_quantity(),
                'description' => $item->get_name(),
                'total' => $item->get_total(),

            ];

        }

        foreach ($order->get_items('shipping') as $item) {

            $items[] = [
                // TODO: Use a translate key here for "Shipping"
                'description' => 'Envío: ' . $item->get_name(),
                'total' => $item->get_total(),

            ];

        }

        return $items;

    }

    public function get_api_endpoint($endpoint, $order_id)
    {
        $query = [
            'mobbex_token' => $this->generate_token(),
            'platform' => "woocommerce",
            "version" => MOBBEX_VERSION,
        ];

        if ($order_id) {
            $query['mobbex_order_id'] = $order_id;
        }

        if ($endpoint === 'mobbex_webhook' && $this->use_webhook_api) {
            return add_query_arg($query, get_rest_url(null, 'mobbex/v1/webhook'));
        } else {
            $query['wc-api'] = $endpoint;
        }

        return add_query_arg($query, home_url('/'));
    }

    public function mobbex_webhook()
    {
        if (!$_POST['data']) {
            $this->debug('Mobbex send an invalid request body');
            die('Mobbex sent an invalid request body.');
        }

        $this->debug($postData, "Mobbex IPN > Post Data");
        $this->debug([
            "id" => $id,
            "token" => $token,
        ], "Mobbex IPN > Params");

        $id = $_REQUEST['mobbex_order_id'];
        $token = $_REQUEST['mobbex_token'];

        $this->process_webhook($id, $token, $_POST['data']);

        echo "WebHook OK: Mobbex for WooCommerce v" . MOBBEX_VERSION;

        die();
    }

    public function mobbex_webhook_api($request)
    {
        $postData = $request->get_params();
        $id = $request->get_param('mobbex_order_id');
        $token = $request->get_param('mobbex_token');

        $this->debug($postData, "Mobbex API > Post Data");
        $this->debug([
            "id" => $id,
            "token" => $token,
        ], "Mobbex API > Params");

        $res = $this->process_webhook($id, $token, $postData['data']);

        return [
            "result" => $res,
            "platform" => $this->getPlatform(),
        ];
    }

    public function process_webhook($id, $token, $data)
    {
        $status = $data['payment']['status']['code'];

        $this->debug([
            "id" => $id,
            "token" => $token,
            "status" => $status,
        ], "Mobbex API > Process Data");

        if (empty($status) || empty($id) || empty($token)) {
            $this->debug([], 'Missing status, id, or token.');

            return false;
        }

        if (!$this->valid_mobbex_token($token)) {
            $this->debug([], 'Invalid mobbex token.');

            return false;
        }

        $order = wc_get_order($id);
        $order->update_meta_data('mobbex_webhook', $_POST);

        $mobbex_risk_analysis = $_POST['data']['payment']['riskAnalysis']['level'];

        $order->update_meta_data('mobbex_payment_id', $_POST['data']['payment']['id']);

        $source = $_POST['data']['payment']['source'];
        $payment_method = $source['name'];

        // TODO: Check the Status and Make a better note here based on the last registered status
        $main_mobbex_note = 'ID de Operación Mobbex: ' . $_POST['data']['payment']['id'] . '. ';
        if (!empty($_POST['data']['entity']['uid'])) {
            $entity_uid = $_POST['data']['entity']['uid'];

            $mobbex_order_url = str_replace(['{entity.uid}', '{payment.id}'], [$entity_uid, $_POST['data']['payment']['id']], MOBBEX_COUPON);

            $order->update_meta_data('mobbex_coupon_url', $mobbex_order_url);

            $order->add_order_note('URL al Cupón: ' . $mobbex_order_url);
        }

        if ($source['type'] == 'card') {
            $mobbex_card_payment_info = $payment_method . ' ( ' . $source['number'] . ' )';
            $mobbex_card_plan = $source['installment']['description'] . '. ' . $source['installment']['count'] . ' Cuota/s' . ' de ' . $source['installment']['amount'];

            $order->update_meta_data('mobbex_card_info', $mobbex_card_payment_info);
            $order->update_meta_data('mobbex_plan', $mobbex_card_plan);

            $main_mobbex_note .= 'Pago realizado con ' . $mobbex_card_payment_info . '. ' . $mobbex_card_plan . '. ';
        } else {
            $main_mobbex_note .= 'Pago realizado con ' . $payment_method . '. ';
        }

        $order->add_order_note($main_mobbex_note);

        if ($mobbex_risk_analysis > 0) {
            $order->add_order_note('El riesgo de la operación fue evaluado en: ' . $mobbex_risk_analysis);
            $order->update_meta_data('mobbex_risk_analysis', $mobbex_risk_analysis);
        }

        if (!empty($payment_method)) {
            $order->set_payment_method_title($payment_method . ' ' . __('a través de Mobbex'));
        }

        $order->save();

        // Check status and set
        if ($status == 2 || $status == 3) {
            $order->update_status('on-hold', __('Awaiting payment', MOBBEX_WC_TEXT_DOMAIN));
        } else if ($status == 4 || $status >= 200 && $status < 400) {
            // Set as completed and reduce stock
            // Set Mobbex Order ID to be able to refund.
            // TODO: implement return
            $this->add_fee_or_discount($data['payment']['total'], $order);
            $order->payment_complete($id);
        } else {
            $order->update_status('failed', __('Order failed', MOBBEX_WC_TEXT_DOMAIN));
        }

        // Set Total Paid
        $total = (float) $data['payment']['total'];
        $order->set_total($total);

        return true;
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

        $order = wc_get_order($id);

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
        if ($this->enabled !== 'yes') {
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
            $this->debug([], "Not checkout page");
            return;
        }

        // if our payment gateway is disabled, we do not have to enqueue JS too
        if (!$this->isReady()) {
            $this->debug([], "Not ready");
            return;
        }

        $order_url = home_url('/mobbex?wc-ajax=checkout');

        $this->debug($order_url);

        // let's suppose it is our payment processor JavaScript that allows to obtain a token
        wp_enqueue_script('mobbex-button', 'https://res.mobbex.com/js/embed/mobbex.embed@' . MOBBEX_EMBED_VERSION . '.js', null, MOBBEX_EMBED_VERSION, false);

        // Inject our bootstrap JS to intercept the WC button press and invoke standard JS
        wp_register_script('mobbex-bootstrap', plugins_url('assets/js/mobbex.bootstrap.js', __FILE__), array('jquery'), MOBBEX_VERSION, false);

        $mobbex_data = array(
            'order_url' => $order_url,
        );

        wp_localize_script('mobbex-bootstrap', 'mobbex_data', $mobbex_data);
        wp_enqueue_script('mobbex-bootstrap');
    }

    /**
     * Display Mobbex button on the cart page.
     */
    public function display_mobbex_button($checkout)
    {
        ?>
        <!-- Mobbex Button -->
        <div id="mbbx-button"></div>
        <?php
    }

    private function _redirect_to_cart_with_error($error_msg)
    {
        wc_add_notice($error_msg, 'error');
        wp_redirect(wc_get_cart_url());

        return array('result' => 'error', 'redirect' => wc_get_cart_url());
    }

    private function add_fee_or_discount($total, $order)
    {
        if (version_compare(WC_VERSION, '2.6', '>') && version_compare(WC_VERSION, '3.2', '<') && $total < $order->get_total()) {
            // In these versions discounts can only be applied using coupons
            $current_user = wp_get_current_user();
            $coupon_code = $current_user->ID . $order->get_id();

            $coupon = array(
                'post_title' => $coupon_code,
                'post_content' => '',
                'post_status' => 'publish',
                'post_author' => 1,
                'post_type' => 'shop_coupon',
            );

            $new_coupon_id = wp_insert_post($coupon);

            update_post_meta($new_coupon_id, 'discount_type', 'fixed_cart');
            update_post_meta($new_coupon_id, 'coupon_amount', $order->get_total() - $total);
            update_post_meta($new_coupon_id, 'individual_use', 'no');
            update_post_meta($new_coupon_id, 'product_ids', '');
            update_post_meta($new_coupon_id, 'exclude_product_ids', '');
            update_post_meta($new_coupon_id, 'usage_limit', '1');
            update_post_meta($new_coupon_id, 'expiry_date', '');
            update_post_meta($new_coupon_id, 'apply_before_tax', 'yes');
            update_post_meta($new_coupon_id, 'free_shipping', 'no');

            $order->apply_coupon($coupon_code);
            $order->recalculate_coupons();

        } elseif ($total != $order->get_total()) {

            $item_fee = new WC_Order_Item_Fee();

            $item_fee->set_name($total > $order->get_total() ? "Processing Fee" : "Discount");
            $item_fee->set_amount($total - $order->get_total());
            $item_fee->set_total($total - $order->get_total());

            $order->add_item($item_fee);

        }

        $order->calculate_totals();

    }

    public function process_refund($order_id, $amount = null, $reason = '')
    {

        $payment_id = get_post_meta($order_id, 'mobbex_payment_id', true);
        if (!$payment_id) {
            return false;
        }

        $response = wp_remote_post(str_replace('{ID}', $payment_id, MOBBEX_REFUND), [

            'headers' => [

                'cache-control' => 'no-cache',
                'content-type' => 'application/json',
                'x-api-key' => $this->api_key,
                'x-access-token' => $this->access_token,
            ],

            'body' => json_encode(['total' => floatval($amount)]),

            'data_format' => 'body',

        ]);

        $result = json_decode($response['body']);

        if ($result->result) {
            return true;
        } else {
            return new WP_Error('mobbex_refund_error', __('Refund Error: Sorry! This is not a refundable transaction.', 'mobbex-gateway'));
        }

    }

    public function get_installments($order)
    {

        $installments = [];

        $ahora = array(
            'ahora_3'  => 'Ahora 3',
            'ahora_6'  => 'Ahora 6',
            'ahora_12' => 'Ahora 12',
            'ahora_18' => 'Ahora 18',
        );

        foreach ($order->get_items() as $item) {

            foreach ($ahora as $key => $value) {
                
                if (get_post_meta($item->get_product_id(), $key, true) === 'yes') {
                    $installments[] = '-' . $key;
                    unset($ahora[$key]);
                }
    
            }

        }

        return $installments;

    }

}
