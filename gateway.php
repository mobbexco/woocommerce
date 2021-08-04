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

    public function __construct()
    {
        $this->id = MOBBEX_WC_GATEWAY_ID;

        $this->method_title = __('Mobbex', 'mobbex-for-woocommerce');
        $this->method_description = __('Mobbex Payment Gateway redirects customers to Mobbex to enter their payment information.', 'mobbex-for-woocommerce');

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
        $this->use_wallet = ($this->get_option('wallet') === 'yes');

        // New Webhook
        $this->use_webhook_api = ($this->get_option('use_webhook_api') === 'yes');

        // Seller CUIT, is going to be use to show financial information in the product page
        $this->tax_id = $this->get_option('tax_id');

        // Enable or Disable financial information in products
        $this->financial_info_active = ($this->get_option('financial_info_active') === 'yes');

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

        $this->helper = new MobbexHelper();
        $this->error = false;
        if (empty($this->api_key) || empty($this->access_token)) {

            $this->error = true;
            MobbexGateway::notice('error', __('You need to specify an API Key and an Access Token.', 'mobbex-for-woocommerce'));

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

            // Add additional checkout fields
            if ($this->own_dni == 'yes') {
                add_filter('woocommerce_billing_fields', [$this, 'add_checkout_fields']);
                add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'display_checkout_fields_data']);
                add_action('woocommerce_after_checkout_validation', [$this, 'validate_checkout_fields']);
                add_action('woocommerce_checkout_update_order_meta', [$this, 'save_checkout_fields']);
            }
        }


    }

    public function debug($log, $message = 'debug')
    {
        mobbex_debug($message, $log);
    }

    /**
     * Define form fields of setting page
     */
    public function init_form_fields()
    {

        $this->form_fields = [

            'enabled' => [

                'title' => __('Enable/Disable', 'mobbex-for-woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable checking out with Mobbex.', 'mobbex-for-woocommerce'),
                'default' => 'yes',

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

            'test_mode' => [
                'title' => __('Enable/Disable Test Mode', 'mobbex-for-woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable Test Mode.', 'mobbex-for-woocommerce'),
                'default' => 'no',
            ],

            'button' => [

                'title' => __('Enable/Disable Button', 'mobbex-for-woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable Mobbex Button experience.', 'mobbex-for-woocommerce'),
                'default' => 'yes',//set to yes/true by default

            ],

            'wallet' => [

                'title' => __('Enable/Disable Wallet', 'mobbex-for-woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable Mobbex Wallet experience.', 'mobbex-for-woocommerce'),
                'default' => 'no',

            ],

            'financial_info_active' => [

                'title' => __('Financial Information', 'mobbex-for-woocommerce'),
                'description' => __('Show financial information in all products, Tax_id need to be set.', 'mobbex-for-woocommerce'),
                'type' => 'checkbox',
                'default' => '',

            ],

            'own_dni' => [

                'title' => __('Add DNI field', 'mobbex-for-woocommerce'),
                'description' => __('Add DNI field on checkout.', 'mobbex-for-woocommerce'),
                'type' => 'checkbox',
                'default' => '',

            ],

            'custom_dni' => [

                'title' => __('Use custom DNI field', 'mobbex-for-woocommerce'),
                'description' => __('If you ask for DNI field on checkout please provide the custom field.', 'mobbex-for-woocommerce'),
                'type' => 'text',
                'default' => '',

            ],

            /* Appearance Configuration */

            'appearance_tab' => [

                'title' => __('Appearance', 'mobbex-for-woocommerce'),
                'type'  => 'title',
                'class' => 'mbbx-tab mbbx-tab-appearance',

            ],

            'title' => [

                'title' => __('Title', 'mobbex-for-woocommerce'),
                'type' => 'text',
                'description' => __('This title will be shown on user checkout.', 'mobbex-for-woocommerce'),
                'default' => __('Pay with Mobbex', 'mobbex-for-woocommerce'),
                'desc_tip' => true,
                'class' => 'mbbx-into-appearance',

            ],

            'description' => [

                'title' => __('Description', 'mobbex-for-woocommerce'),
                'description' => __('This description will be shown on user checkout.', 'mobbex-for-woocommerce'),
                'type' => 'textarea',
                'default' => '',
                'class' => 'mbbx-into-appearance',

            ],

            'checkout_theme' => [

                'title' => __('Checkout Theme', 'mobbex-for-woocommerce'),
                'description' => __('You can customize your Checkout Theme from here.', 'mobbex-for-woocommerce'),
                'type' => 'select',
                'options' => [
                    'light' => __('Light Theme', 'mobbex-for-woocommerce'),
                    'dark' => __('Dark Theme', 'mobbex-for-woocommerce'),
                ],
                'default' => 'light',
                'class' => 'mbbx-into-appearance',

            ],

            'checkout_title' => [

                'title' => __('Checkout Title', 'mobbex-for-woocommerce'),
                'description' => __('You can customize your Checkout Title from here.', 'mobbex-for-woocommerce'),
                'type' => 'text',
                'default' => '',
                'class' => 'mbbx-into-appearance',

            ],

            'checkout_logo' => [

                'title' => __('Checkout Logo URL', 'mobbex-for-woocommerce'),
                'description' => __('Customize your Checkout Logo here. The logo URL must be HTTPS and must be only set if required. If not set the Logo set on wordpress appearence or Mobbex panel will be used. Dimensions: 250x250 pixels', 'mobbex-for-woocommerce. If URL is not set, then the logo set in wordpress or mobbex console will be use'),
                'type' => 'text',
                'default' => '',
                'class' => 'mbbx-into-appearance',

            ],

            'checkout_background_color' => [

                'title' => __('Checkout Background Color', 'mobbex-for-woocommerce'),
                'description' => __('You can customize your Checkout Background Color from here.', 'mobbex-for-woocommerce'),
                'type' => 'text',
                'default' => '#ECF2F6',
                'class' => 'colorpick mbbx-into-appearance',

            ],

            'checkout_primary_color' => [

                'title' => __('Checkout Primary Color', 'mobbex-for-woocommerce'),
                'description' => __('You can customize your Checkout Primary Color for Buttons and TextFields from here.', 'mobbex-for-woocommerce'),
                'type' => 'text',
                'class' => 'colorpick mbbx-into-appearance',
                'default' => '#6f00ff',

            ],

            /* Advanced Configuration */

            'advanced_configuration_tab' => [

                'title' => __('Advanced Configuration', 'mobbex-for-woocommerce'),
                'type'  => 'title',
                'class' => 'mbbx-tab mbbx-tab-advanced',

            ],
            
            'payment_mode' => [

                'title' => __('Enable/Disable 2-step Payment Mode', 'mobbex-for-woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable 2-step Payment Mode.', 'mobbex-for-woocommerce'),
                'default' => 'no',
                'class' => 'mbbx-into-advanced',

            ],

            'use_webhook_api' => [

                'title' => __('Use new WebHook API', 'mobbex-for-woocommerce'),
                'type' => 'checkbox',
                'label' => __('Use the WebHook by API instead of old Controller. Permalinks must be Active to use it Safely', 'mobbex-for-woocommerce'),
                'default' => 'no',
                'class' => 'mbbx-into-advanced',

            ],

            'reseller_id' => [

                'title' => __('Reseller ID', 'mobbex-for-woocommerce'),
                'description' => __('You can customize your Reseller ID from here. This field is optional and must be used only if was specified by the main seller.', 'mobbex-for-woocommerce'),
                'type' => 'text',
                'default' => '',
                'class' => 'mbbx-into-advanced',

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

        $order->update_status('pending', __('Awaiting Mobbex Webhook', 'mobbex-for-woocommerce'));

        if ($this->use_button) {
            $result = [
                'result' => 'success',
                'data' => $checkout_data,
                'return_url' => $return_url,
                'redirect' => false,
            ];

            // Make sure to use json in pay for order page
            if (!empty($_GET['pay_for_order'])) {
                wp_send_json($result); exit;
            }

            return $result;
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
        
        $shop_logo = null;
        // Get logo url from wordpress settings 
        $custom_logo_id = get_theme_mod('custom_logo');
        $logo = wp_get_attachment_image_src($custom_logo_id , 'full');

        error_log("¡Lo echaste a perder!".print_r($logo[0],true), 3, "/var/www/html/wp-content/plugins/mwoocommerce/my-errors.log");
        
        $theme = [
            "type" => $this->checkout_theme,
            "background" => $this->checkout_background_color,
            "colors" => [
                "primary" => $this->checkout_primary_color,
            ],
            'header' => [
                'name' => !empty($this->checkout_title) ? $this->checkout_title : get_bloginfo('name'),
                'logo' =>  !empty($this->checkout_logo) ? $this->checkout_logo : (!empty($logo[0]) ?  $logo[0] : $shop_logo),
            ]
        ];

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
        $dni_key = !empty($this->custom_dni) ? $this->custom_dni : '_billing_dni';

        $customer = [
            'name' => $current_user->display_name ? : $order->get_formatted_billing_full_name(),
            'email' => $current_user->user_email ? : $order->get_billing_email(),
            'phone' => get_user_meta($current_user->ID,'phone_number',true) ? : $order->get_billing_phone(),
            'uid' => $current_user->ID ? : null,
            'identification' => get_post_meta($order->get_id(), $dni_key, true),
        ];

        $checkout_body = [
            'reference' => $this->get_reference($order->get_id()),
            'description' => 'Orden #' . $order->get_id(),
            'items' => $this->get_items($order),
            'installments' => $this->get_installments($order),
            'customer' => $customer,
            'test' => $this->test_mode,
            'options' => [
                'button' => $this->use_button,
                'domain' => $domain,
                'theme' => $this->getTheme(),
                'redirect' => [
                    'success' => true,
                    'failure' => false,
                ],
                'platform' => $this->getPlatform(),
            ],
            'wallet' => ($this->use_wallet && wp_get_current_user()->ID),
            'timeout' => 5,
        ];

        // Custom data filter
        $checkout_body = apply_filters('mobbex_checkout_custom_data', $checkout_body, $order->get_id());

        // Merge not editable data
        $checkout_body = array_merge($checkout_body,[
            'total' => $order->get_total(),
            'webhook' => $this->get_api_endpoint('mobbex_webhook', $order->get_id()),
            'return_url' => $return_url,
            'intent' => $this->helper->get_payment_mode(),
        ]);

        $this->debug([
            "checkout_body" => $checkout_body,
        ]);

        // Try to get credentials from store configured using multisite options
        $store = MobbexHelper::get_store($order);
        $api_key      = !empty($store['api_key'])      ? $store['api_key']      : $this->api_key;
        $access_token = !empty($store['access_token']) ? $store['access_token'] : $this->access_token;

        // Create the Checkout
        $response = wp_remote_post(MOBBEX_CHECKOUT, [

            'headers' => [
                'cache-control' => 'no-cache',
                'content-type' => 'application/json',
                'x-api-key' => $api_key,
                'x-access-token' => $access_token,
            ],

            'body' => json_encode($checkout_body),

            'data_format' => 'body',

        ]);

        $this->debug($response);

        if (!is_wp_error($response)) {
            $response = json_decode($response['body'], true);

            if (!empty($response['data'])) {
                // Fire action and return data
                do_action('mobbex_checkout_process', $response['data'], $order->get_id());

                return $response['data'];
            }
        }

        return null;
    }

    public function get_items($order)
    {
        // Get items from order
        $line_items = $order->get_items();
        $shipping_items = $order->get_items('shipping');

        $mobbex_items = [];

        if (!empty($line_items)) {
            foreach ($line_items as $item) {
                $image = null;
                $product_id = $item->get_product_id();

                if (!empty($product_id)) {
                    $product = wc_get_product($item->get_product_id());

                    // Get product image
                    $image_id = $product->get_image_id();
                    $cover_image = wp_get_attachment_image_url($image_id, 'thumbnail');
                    $default_image = wc_placeholder_img_src('thumbnail');

                    $image = !empty($cover_image) ? $cover_image : $default_image;
                }
    
                $mobbex_items[] = [
                    'image' => $image,
                    'quantity' => $item->get_quantity(),
                    'description' => $item->get_name(),
                    'total' => $item->get_total(),
                ];
            }
        }

        if (!empty($shipping_items)) {
            foreach ($shipping_items as $item) {
                $mobbex_items[] = [
                    // TODO: Use a translate key here for "Shipping"
                    'description' => __('Shipping: ', 'mobbex-for-woocommerce') . $item->get_name(),
                    'total' => $item->get_total(),
                ];
            }
        }

        return $mobbex_items;
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

        //order webhook filter
        $_POST['data'] = apply_filters( 'mobbex_order_webhook', $_POST['data'] );
        
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

        //order webhook filter
        $postData = apply_filters( 'mobbex_order_webhook', $postData );

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
        if ($status == 2 || $status == 3 || $status == 100) {
            if (!empty($data['payment']['operation']['type']) && $data['payment']['operation']['type'] === 'payment.2-step' && $status == 3) {
                $order->update_status('authorized', __('Awaiting payment', 'mobbex-for-woocommerce'));
            } else {
                $order->update_status('on-hold', __('Awaiting payment', 'mobbex-for-woocommerce'));
            }
        } else if ($status == 4 || $status >= 200 && $status < 400) {
            // Set as completed and reduce stock
            // Set Mobbex Order ID to be able to refund.
            // TODO: implement return
            $this->add_fee_or_discount($data['payment']['total'], $order);
            $order->payment_complete($id);
        } else {
            $order->update_status('failed', __('Order failed', 'mobbex-for-woocommerce'));
        }
        
        // Set Total Paid
        $total = (float) $data['payment']['total'];
        $order->set_total($total);

        //action with the checkout data
        do_action('mobbex_webhook_process',$id,$data);

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

        if ($status > 1 && $status < 400) {
            // Redirect
            $redirect = $order->get_checkout_order_received_url();
        } else {
            // Try to restore the cart here
            $redirect = $order->get_cancel_order_url();
        }

        WC()->session->set('order_id', null);
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
        if (is_order_received_page() || (!is_cart() && !is_checkout())) {
            $this->debug([], "Not checkout page");
            return;
        }

        // if our payment gateway is disabled, we do not have to enqueue JS too
        if (!$this->isReady()) {
            $this->debug([], "Not ready");
            return;
        }

        // Exclude scripts from cache plugins minification
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
        if (!defined('DONOTMINIFY'))    define('DONOTMINIFY', true);

        $order_url = home_url('/mobbex?wc-ajax=checkout');
        $update_url = home_url('/wc-api/mobbex_checkout_update');
        $is_wallet = ($this->use_wallet && wp_get_current_user()->ID);
        $order_id = WC()->session->get('order_id');

        $this->debug($order_url);

        // let's suppose it is our payment processor JavaScript that allows to obtain a token
        wp_enqueue_script('mobbex-button', 'https://res.mobbex.com/js/embed/mobbex.embed@' . MOBBEX_EMBED_VERSION . '.js', null, MOBBEX_EMBED_VERSION, false);
        wp_enqueue_script('mobbex', 'https://res.mobbex.com/js/sdk/mobbex@1.0.0.js', null, null, false);

        // Inject our bootstrap JS to intercept the WC button press and invoke standard JS
        wp_register_script('mobbex-bootstrap', plugins_url('assets/js/mobbex.bootstrap.js', __FILE__), array('jquery'), MOBBEX_VERSION, false);

        $mobbex_data = array(
            'order_url' => $order_url,
            'update_url' => $update_url,
            'is_wallet' => $is_wallet,
            'is_pay_for_order' => !empty($_GET['pay_for_order']),
        );

        // If using wallet, create Order previously
        if ($is_wallet) {

            if (empty($order_id)) {
                // Create Order and save in session
                $checkout = WC()->checkout;
                WC()->cart->calculate_totals();
                $order_id = $checkout->create_order($_POST);

                WC()->session->set('order_id', $order_id);
            }

            // Get order
            $order = wc_get_order($order_id);

            // Create mobbex checkout
            $return_url = $this->get_api_endpoint('mobbex_return_url', $order_id);
            $checkout_data = $this->get_checkout($order, $return_url);
    
            // Set mobbex wallet data
            $mobbex_data['wallet'] = $checkout_data['wallet'];
            $mobbex_data['return_url'] = $return_url;
            $mobbex_data['transaction_uid'] = $checkout_data['id'];

        }

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

    /**
     * Retrieve selected plans that won't be showed in the payment process 
     * @return array 
     */
    public function get_installments($order)
    {
        $installments = $all_common_plans = $all_advanced_plans = [];

        // Get products and categories from order
        $products = MobbexHelper::get_product_ids($order);
        $categories = MobbexHelper::get_category_ids($order);

        $ahora = [
            'ahora_3',
            'ahora_6',
            'ahora_12',
            'ahora_18',
        ];

        foreach ($ahora as $key => $plan) {
            // Get 'ahora' plans from categories
            foreach($categories as $cat_id){
                // If category has plan selected
                if (get_term_meta($cat_id, $plan, true) === 'yes') {
                    // Add to installments
                    $installments[] = '-' . $plan;
                    unset($ahora[$key]);
                }
            }

            // Get 'ahora' plans from products
            foreach ($products as $product_id) {
                // If product has plan selected
                if (get_post_meta($product_id, $plan, true) === 'yes') {
                    // Add to installments
                    $installments[] = '-' . $plan;
                    unset($ahora[$key]);
                }
            }
        }

        foreach ($products as $product_id) {
            // Get common and advanced plans from products
            $product_common_plans   = get_post_meta($product_id, 'common_plans', true) ?: [];
            $product_advanced_plans = get_post_meta($product_id, 'advanced_plans', true) ?: [];

            // Support previus save method
            $product_common_plans   = is_string($product_common_plans)   ? unserialize($product_common_plans)   : $product_common_plans;
            $product_advanced_plans = is_string($product_advanced_plans) ? unserialize($product_advanced_plans) : $product_advanced_plans;

            // Merge into unique arrays
            $all_common_plans   = array_merge($all_common_plans, $product_common_plans);
            $all_advanced_plans = array_merge($all_advanced_plans, $product_advanced_plans);
        }

        // Common plans
        foreach ($all_common_plans as $plan) {
            // Add to installments
            $installments[] = '-' . $plan;
        }

        // Get all the advanced plans with their number of reps
        $counted_advanced_plans = array_count_values($all_advanced_plans);

        // Advanced plans
        foreach ($counted_advanced_plans as $plan => $reps) {
            // Only if the plan is active on all products
            if ($reps == count($products)) {
                // Add to installments
                $installments[] = '+uid:' . $plan;
            }
        }

        // Remove duplicated plans and return
        return array_values(array_unique($installments));
    }

    public function get_reference($order_id)
    {
        // If isset reseller id add it to reference
        $reseller_id = !empty($this->reseller_id) ? '_reseller:' . str_replace(' ', '-', trim($this->reseller_id)) : null;

        return 'wc_order_'.$order_id.'_time_'.time() . $reseller_id;
    }

    public function add_checkout_fields($fields)
    {
        $fields['billing_dni'] = array(
            'label' => __('DNI', 'woocommerce'),
            'placeholder' => _x('Ingrese su DNI', 'placeholder', 'woocommerce'),
            'required' => true,
            'clear' => false,
            'type' => 'text',
            'class' => array('my-dni'),
        );

        return $fields;
    }

    public function display_checkout_fields_data($order)
    {
        echo '<p><strong>' . __('DNI') . ':</strong> ' . get_post_meta($order->get_id(), '_billing_dni', true) . '</p>';
    }

    public function validate_checkout_fields()
    {
        if (empty($_POST['billing_dni'])) {
            wc_add_notice(__('Empty DNI field.'), 'error');
        }
    }

    public function save_checkout_fields($order_id) 
    {
        // Save DNI field
        if (!empty($_POST['billing_dni'])) {
            update_post_meta($order_id, '_billing_dni', esc_attr($_POST['billing_dni']));
        }
    }
}