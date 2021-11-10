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
        $this->id     = MOBBEX_WC_GATEWAY_ID;
        $this->helper = new MobbexHelper();

        // String variables. That's used on checkout view
        $this->icon        = apply_filters('mobbex_icon', plugin_dir_url(__FILE__) . 'icon.png');
        $this->title       = $this->helper->settings['title'];
        $this->description = $this->helper->settings['description'];

        $this->method_title       = 'Mobbex';
        $this->method_description = __('Mobbex Payment Gateway redirects customers to Mobbex to enter their payment information.', 'mobbex-for-woocommerce');

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
        $this->error = false;

        if (!$this->helper->settings['api-key'] || !$this->helper->settings['access-token'])
            $this->error = !MobbexGateway::notice('error', __('You need to specify an API Key and an Access Token.', 'mobbex-for-woocommerce'));

        // Always Required
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);

        // Only if the plugin is enabled
        if (!$this->error && $this->enabled === 'yes') {
            add_action('woocommerce_api_mobbex_return_url', [$this, 'mobbex_return_url']);

            if ($this->helper->settings['use_webhook_api'] != 'yes')
                add_action('woocommerce_api_mobbex_webhook', [$this, 'mobbex_webhook']);

            // Add additional checkout fields
            if ($this->helper->settings['own_dni'] == 'yes')
                add_filter('woocommerce_billing_fields', [$this, 'add_checkout_fields']);

            // Display fields on admin panel and try to save it
            add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'display_checkout_fields_data']);
            add_action('woocommerce_after_checkout_validation', [$this, 'validate_checkout_fields']);
            add_action('woocommerce_checkout_update_order_meta', [$this, 'save_checkout_fields']);
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
                'default' => 'yes',

            ],

            'wallet' => [

                'title' => __('Enable/Disable Wallet', 'mobbex-for-woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable Mobbex Wallet experience.', 'mobbex-for-woocommerce'),
                'default' => 'no',

            ],

            'financial_info_active' => [

                'title' => __('Financial Information', 'mobbex-for-woocommerce'),
                'description' => __('Show financial information in all products.', 'mobbex-for-woocommerce'),
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
                'description' => __('You can customize your Checkout Logo from here. The logo URL must be HTTPS and must be only set if required. If not set the Logo set on Mobbex will be used. Dimensions: 250x250 pixels', 'mobbex-for-woocommerce'),
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

            'financial_widget_theme' => [

                'title' => __('Financial Widget Theme', 'mobbex-for-woocommerce'),
                'description' => __('You can customize your Financial Widget Theme from here.', 'mobbex-for-woocommerce'),
                'type' => 'select',
                'options' => [
                    'light' => __('Light Theme', 'mobbex-for-woocommerce'),
                    'dark' => __('Dark Theme', 'mobbex-for-woocommerce'),
                ],
                'default' => 'light',
                'class' => 'mbbx-into-appearance',

            ],

            'financial_widget_button_color' => [

                'title' => __('Financial Widget Button Color', 'mobbex-for-woocommerce'),
                'description' => __('You can customize your financial widget button color from here.', 'mobbex-for-woocommerce'),
                'type' => 'text',
                'default' => null,
                'class' => 'colorpick mbbx-into-appearance'

            ],

            'financial_widget_button_font_color' => [

                'title' => __('Financial Widget Button Font Color', 'mobbex-for-woocommerce'),
                'description' => __('You can customize your financial widget button font color from here.', 'mobbex-for-woocommerce'),
                'type' => 'text',
                'default' => null,
                'class' => 'colorpick mbbx-into-appearance'

            ],

            'financial_widget_button_font_size' => [

                'title' => __('Financial Widget Button Font Size', 'mobbex-for-woocommerce'),
                'description' => __('You can customize your financial widget button font size from here.', 'mobbex-for-woocommerce'),
                'type' => 'text',
                'default' => null,
                'class' => 'mbbx-into-appearance'

            ],

            'financial_widget_button_Padding' => [

                'title' => __('Financial Widget Button Padding', 'mobbex-for-woocommerce'),
                'description' => __('You can customize your financial widget button padding from here.', 'mobbex-for-woocommerce'),
                'type' => 'text',
                'default' => null,
                'class' => 'mbbx-into-appearance'

            ],
            
            /* Advanced Configuration */

            'advanced_configuration_tab' => [

                'title' => __('Advanced Configuration', 'mobbex-for-woocommerce'),
                'type'  => 'title',
                'class' => 'mbbx-tab mbbx-tab-advanced',

            ],

            'multicard' => [

                'title' => __('Enable/Disable Multicard', 'mobbex-for-woocommerce'),
                'type' => 'checkbox',
                'label' => __('Allow to pay the operation with multiple cards (incompatible with marketplace).', 'mobbex-for-woocommerce'), // Permite abonar la operación con múltiples tarjetas
                'default' => 'no',
                'class' => 'mbbx-into-advanced',

            ],

            'multivendor' => [

                'title' => __('Enable/Disable Multivendor', 'mobbex-for-woocommerce'),
                'type' => 'select',
                'label' => __('Allow to pay the operation with multiple vendor (incompatible with multicard).', 'mobbex-for-woocommerce'), // Permite abonar la operación con múltiples tarjetas
                'options' => [
                    'no' => __('Disable', 'mobbex-for-woocommerce'),
                    'active' => __('Active', 'mobbex-for-woocommerce'),
                    'unified' => __('Unified', 'mobbex-for-woocommerce'),
                ],
                'default' => 'no',
                'class' => 'mbbx-into-advanced',

            ],

            'payment_mode' => [

                'title' => __('Enable/Disable 2-step Payment Mode', 'mobbex-for-woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable 2-step Payment Mode.', 'mobbex-for-woocommerce'),
                'default' => 'no',
                'class' => 'mbbx-into-advanced',

            ],

            '2_step_processing_mail' => [
                'title'         => __('Mail de pedido procesado', 'mobbex-for-woocommerce'),
                'description'   => __('Para uso de operatoria en 2 pasos', 'mobbex-for-woocommerce'),
                'type'          => 'select',
                'default'       => 'capture',
                'class'         => 'mbbx-into-advanced',
                'options'       => [
                    'authorize' => __('Al autorizar un pago', 'mobbex-for-woocommerce'),
                    'capture'   => __('Al capturar un pago', 'mobbex-for-woocommerce'),
                ],
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

            'debug_mode' => [
                'title'   => __('Modo Debug', 'mobbex-for-woocommerce'),
                'label'   => __('Activar Modo Debug.', 'mobbex-for-woocommerce'),
                'class'   => 'mbbx-into-advanced',
                'type'    => 'checkbox',
                'default' => 'no',
            ],

            'unified_mode' => [
                'title'   => __('Modo unificado', 'mobbex-for-woocommerce'),
                'label'   => __('Deshabilita la subdivisión de los métodos de pago en la página de finalización de la compra. Las opciones se verán en el checkout.', 'mobbex-for-woocommerce'),
                'class'   => 'mbbx-into-advanced',
                'type'    => 'checkbox',
                'default' => 'no',
            ],

            'disable_template' => [
                'title'   => __('Deshabilitar plantilla', 'mobbex-for-woocommerce'),
                'label'   => __('Deshabilitar plantilla para el mostrado de los métodos de pago.', 'mobbex-for-woocommerce'),
                'class'   => 'mbbx-into-advanced',
                'type'    => 'checkbox',
                'default' => 'no',
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
        $this->helper->debug('Creating payment', compact('order_id'));

        if (!$this->helper->isReady())
            return ['result' => 'error'];

        $order = wc_get_order($order_id);

        // Create checkout from order
        $order_helper  = new MobbexOrderHelper($order);
        $checkout_data = $order_helper->create_checkout();

        $this->helper->debug('Checkout response', $checkout_data);

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

    public function mobbex_webhook()
    {
        if (!$_POST['data']) {
            $this->debug('Mobbex send an invalid request body');
            die('Mobbex sent an invalid request body.');
        }

        $id = $_REQUEST['mobbex_order_id'];
        $token = $_REQUEST['mobbex_token'];

        //order webhook filter
        $_POST['data'] = apply_filters( 'mobbex_order_webhook', $_POST['data'] );

        $webhookData = MobbexHelper::format_webhook_data($id, $_POST['data'], ($this->helper->multicard === 'yes'), ($this->helper->multivendor === 'yes'));

        //Save de webhook in the database
        global $wpdb;
        $wpdb->insert( $wpdb->prefix.'mobbex_transaction', $webhookData, MobbexHelper::db_column_format($webhookData));

        //Prcess the webbhook data
        if($webhookData['parent'] === 'yes')
            $this->process_webhook($id, $token, json_decode($_POST['data']));

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
        $webhookData = MobbexHelper::format_webhook_data($id, $postData['data'], ($this->helper->multicard === 'yes'), ($this->helper->multivendor !== 'no'));
       
        //Save de webhook in the database
        global $wpdb;
        $wpdb->insert( $wpdb->prefix.'mobbex_transaction', $webhookData, MobbexHelper::db_column_format($webhookData));

        //Prcess the webbhook data
        if($webhookData['parent'] === 'yes')
            $this->process_webhook($id, $token, $postData);

        return [
            'result'   => $res,
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

        if (!$this->helper->valid_mobbex_token($token)) {
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
        } else if ($status == 602 || $status == 605) {
            $order->update_status('refunded', __('Payment refunded', 'mobbex-for-woocommerce'));
        } else if ($status == 4 || $status >= 200 && $status < 400) {
            // Set as completed and reduce stock
            // Set Mobbex Order ID to be able to refund.
            // TODO: implement return
            $this->helper->update_order_total($order, $data['payment']['total']);
            $order->payment_complete($id);
        } else {
            $order->update_status('failed', __('Payment failed', 'mobbex-for-woocommerce'));
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

        if (!$this->helper->valid_mobbex_token($token)) {
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
        WC()->session->set('order_awaiting_payment', null);

        wp_safe_redirect($redirect);
    }

    private function _redirect_to_cart_with_error($error_msg)
    {
        wc_add_notice($error_msg, 'error');
        wp_redirect(wc_get_cart_url());

        return array('result' => 'error', 'redirect' => wc_get_cart_url());
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

        $result = json_decode($response['body'], true);

        if ($result['result'])
            return true;

        return false;
    }

    public function add_checkout_fields($fields)
    {
        $cutomer_id = WC()->cart->get_customer()->get_id();

        $fields['billing_dni'] = [
            'type'        => 'text',
            'required'    => true,
            'clear'       => false,
            'label'       => 'DNI',
            'placeholder' => 'Ingrese su DNI',
            'default'     => WC()->session->get('mbbx_billing_dni') ?: get_user_meta($cutomer_id, 'billing_dni', true),
        ];

        return $fields;
    }

    public function display_checkout_fields_data($order)
    {
        ?>
        <p>
            <strong>DNI:</strong>
            <?= get_post_meta($order->get_id(), 'billing_dni', true) ?: get_user_meta($order->get_customer_id(), 'billing_dni', true) ?>
        </p>
        <?php
    }

    public function validate_checkout_fields()
    {
        // Get dni field key
        $own_dni = $this->helper->settings['own_dni'] == 'yes' ? 'billing_dni' : false;
        $dni     = $this->helper->settings['custom_dni'] ? $this->helper->settings['custom_dni'] : $own_dni;

        // Exit if field is not configured
        if (!$dni)
            return;

        if (empty($_POST[$dni]))
            return wc_add_notice('Complete el campo DNI', 'error');

        WC()->session->set('mbbx_billing_dni', $_POST[$dni]);
    }

    public function save_checkout_fields($order_id)
    {
        $customer_id = wc_get_order($order_id)->get_customer_id();

        // Get dni field key
        $own_dni = $this->helper->settings['own_dni'] == 'yes' ? 'billing_dni' : false;
        $dni     = $this->helper->settings['custom_dni'] ? $this->helper->settings['custom_dni'] : $own_dni;

        // Exit if field is not configured
        if (!$dni)
            return;

        // Try to save by customer to show in future purchases
        if ($customer_id)
            update_user_meta($customer_id, 'billing_dni', $_POST[$dni]);

        // Save by order
        update_post_meta($order_id, 'billing_dni', $_POST[$dni]);
    }
}
