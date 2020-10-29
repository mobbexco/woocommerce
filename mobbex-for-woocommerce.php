<?php
/*
Plugin Name:  Mobbex for Woocommerce
Description:  A small plugin that provides Woocommerce <-> Mobbex integration.
Version:      3.0.0
WC tested up to: 4.2.2
Author: mobbex.com
Author URI: https://mobbex.com/
Copyright: 2020 mobbex.com
 */

require_once 'utils.php';

class MobbexGateway
{

    /**
     * Errors Array
     */
    static $errors = [];

    /**
     * Mobbex URL.
     */
    public static $site_url = "https://www.mobbex.com";

    /**
     * Gateway documentation URL.
     */
    public static $doc_url = "https://mobbex.dev";

    /**
     * Github URLs
     */
    public static $github_url = "https://github.com/mobbexco/woocommerce";
    public static $github_issues_url = "https://github.com/mobbexco/woocommerce/issues";

    public function init()
    {

        MobbexGateway::load_textdomain();
        MobbexGateway::check_dependencies();

        if (count(MobbexGateway::$errors)) {

            foreach (MobbexGateway::$errors as $error) {
                MobbexGateway::notice('error', $error);
            }

            return;
        }

        MobbexGateway::load_gateway();
        MobbexGateway::add_gateway();

        // Add some useful things
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_action_links']);
        add_filter('plugin_row_meta', [$this, 'plugin_row_meta'], 10, 2);

        // Mobbex product management tab
        add_filter('woocommerce_product_data_tabs', [$this, 'mobbex_product_settings_tabs']);
        add_action('woocommerce_product_data_panels', [$this, 'mobbex_product_panels']);
        add_action('woocommerce_process_product_meta', [$this, 'mobbex_product_save']);
        add_action('admin_head', [$this, 'mobbex_icon']);

        // Checkout update actions
        add_action('woocommerce_api_mobbex_checkout_update', [$this, 'mobbex_checkout_update']);
        add_action('woocommerce_cart_emptied', function(){WC()->session->set('order_id', null);});
        add_action('woocommerce_add_to_cart', function(){WC()->session->set('order_id', null);});
        
        add_action('rest_api_init', function () {
            register_rest_route('mobbex/v1', '/webhook', [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'mobbex_webhook_api'],
            ]);
        });
    }

    /**
     * Check dependencies.
     *
     * @throws Exception
     */
    public static function check_dependencies()
    {
        if (!class_exists('WooCommerce')) {
            MobbexGateway::$errors[] = __('WooCommerce needs to be installed and activated.', MOBBEX_WC_TEXT_DOMAIN);
        }

        if (!function_exists('WC')) {
            MobbexGateway::$errors[] = __('Mobbex requires WooCommerce to be activated', MOBBEX_WC_TEXT_DOMAIN);
        }

        if (!is_ssl()) {
            MobbexGateway::$errors[] = __('Your site needs to be served via HTTPS to comunicate securely with Mobbex.', MOBBEX_WC_TEXT_DOMAIN);
        }

        if (version_compare(WC_VERSION, '2.6', '<')) {
            MobbexGateway::$errors[] = __('Mobbex requires WooCommerce version 2.6 or greater', MOBBEX_WC_TEXT_DOMAIN);
        }

        if (!function_exists('curl_init')) {
            MobbexGateway::$errors[] = __('Mobbex requires the cURL PHP extension to be installed on your server', MOBBEX_WC_TEXT_DOMAIN);
        }

        if (!function_exists('json_decode')) {
            MobbexGateway::$errors[] = __('Mobbex requires the JSON PHP extension to be installed on your server', MOBBEX_WC_TEXT_DOMAIN);
        }

        $openssl_warning = __('Mobbex requires OpenSSL >= 1.0.1 to be installed on your server', MOBBEX_WC_TEXT_DOMAIN);
        if (!defined('OPENSSL_VERSION_TEXT')) {
            MobbexGateway::$errors[] = $openssl_warning;
        }

        preg_match('/^(?:Libre|Open)SSL ([\d.]+)/', OPENSSL_VERSION_TEXT, $matches);
        if (empty($matches[1])) {
            MobbexGateway::$errors[] = $openssl_warning;
        }

        if (!version_compare($matches[1], '1.0.1', '>=')) {
            MobbexGateway::$errors[] = $openssl_warning;
        }

        require 'plugin-update-checker/plugin-update-checker.php';
        $myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
            'https://github.com/mobbexco/woocommerce/',
            __FILE__,
            'mobbex-plugin-update-checker'
        );
        $myUpdateChecker->getVcsApi()->enableReleaseAssets();
    }

    public function add_action_links($links)
    {
        $plugin_links = [
            '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=mobbex') . '">' . __('Settings', MOBBEX_WC_TEXT_DOMAIN) . '</a>',
        ];

        $links = array_merge($plugin_links, $links);

        return $links;
    }

    /**
     * Plugin row meta links
     *
     * @access public
     * @param  array $input already defined meta links
     * @param  string $file plugin file path and name being processed
     * @return array $input
     */
    public function plugin_row_meta($links, $file)
    {
        if (strpos($file, plugin_basename(__FILE__)) !== false) {
            $plugin_links = [
                '<a href="' . esc_url(MobbexGateway::$site_url) . '" target="_blank">' . __('Website', 'woocommerce-mobbex-gateway') . '</a>',
                '<a href="' . esc_url(MobbexGateway::$doc_url) . '" target="_blank">' . __('Documentation', 'woocommerce-mobbex-gateway') . '</a>',
                '<a href="' . esc_url(MobbexGateway::$github_url) . '" target="_blank">' . __('Contribute', 'woocommerce-mobbex-gateway') . '</a>',
                '<a href="' . esc_url(MobbexGateway::$github_issues_url) . '" target="_blank">' . __('Report Issues', 'woocommerce-mobbex-gateway') . '</a>',
            ];

            $links = array_merge($links, $plugin_links);
        }

        return $links;
    }

    public function mobbex_webhook_api($request)
    {
        try {
            mobbex_debug("REST API > Request", $request->get_params());

            $mobbexGateway = WC()->payment_gateways->payment_gateways()[MOBBEX_WC_GATEWAY_ID];

            return $mobbexGateway->mobbex_webhook_api($request);
        } catch (Exception $e) {
            mobbex_debug("REST API > Error", $e);

            return [
                "result" => false,
            ];
        }
    }

    public static function load_textdomain()
    {

        load_plugin_textdomain(MOBBEX_WC_TEXT_DOMAIN, false, dirname(plugin_basename(__FILE__)) . '/languages/');

    }

    public static function load_gateway()
    {

        require_once plugin_dir_path(__FILE__) . 'gateway.php';

    }

    public static function add_gateway()
    {

        add_filter('woocommerce_payment_gateways', function ($methods) {

            $methods[] = MOBBEX_WC_GATEWAY;
            return $methods;

        });

    }

    public static function notice($type, $msg)
    {

        add_action('admin_notices', function () use ($type, $msg) {
            $class = esc_attr("notice notice-$type");
            $msg = esc_html($msg);

            ob_start();

            ?>

            <div class="<?=$class?>">
                <h2>Mobbex for Woocommerce</h2>
                <p><?=$msg?></p>
            </div>

            <?php

            echo ob_get_clean();
        });

    }

    public function mobbex_product_settings_tabs($tabs)
    {
    
        $tabs['mobbex'] = array(
            'label'    => 'Mobbex',
            'target'   => 'mobbex_product_data',
            'priority' => 21,
        );
        return $tabs;
    
    }
    
    public function mobbex_product_panels()
    {
        $product = wc_get_product(get_the_ID());
    
        echo '<div id="mobbex_product_data" class="panel woocommerce_options_panel hidden">';
        echo '<h2><b>' . __('Choose the plans you want NOT to appear during the purchase', MOBBEX_WC_TEXT_DOMAIN) . ':</b></h2>';
    
        $ahora = array(
            'ahora_3'  => 'Ahora 3',
            'ahora_6'  => 'Ahora 6',
            'ahora_12' => 'Ahora 12',
            'ahora_18' => 'Ahora 18',
        );

        foreach ($ahora as $key => $value) {

            $checkbox_data = array(
                'id'      => $key,
                'value'   => get_post_meta(get_the_ID(), $key, true),
                'label'   => $value,
            );
            
            if (get_post_meta(get_the_ID(), $key, true) === 'yes') {
                $checkbox_data['custom_attributes'] = 'checked';
            }

            woocommerce_wp_checkbox($checkbox_data);
        }
    
        echo '</div>';
    
    }

    public function mobbex_product_save($post_id) 
    {
        $product = wc_get_product($post_id);

        $ahora = array(
            'ahora_3'  => false,
            'ahora_6'  => false,
            'ahora_12' => false,
            'ahora_18' => false,
        );

        foreach ($ahora as $key => $value) {
            if (isset($_POST[$key]) && $_POST[$key] === 'yes') {
                $value = 'yes';
            }
            
            $product->update_meta_data($key, $value);
        }

        $product->save();
    }

    public function mobbex_icon()
    {
        echo '<style>
        #woocommerce-product-data ul.wc-tabs li.mobbex_options.mobbex_tab a:before{
            color: #7000ff;
            content: "\f153";
        }
        </style>';
    }

    public function mobbex_checkout_update()
    {
        // Get Checkout and Order Id  
        $checkout = WC()->checkout;
        $order_id = WC()->session->get('order_id');
        WC()->cart->calculate_totals();

        // Get Order info if exists
        if (!$order_id) {
            return false;
        }
        $order = wc_get_order($order_id);

        // If form data is sent 
        if (!empty($_REQUEST['payment_method'])) {

            // Get billing and shipping data from Request
            $billing = [];
            $shipping = [];
            foreach ($_REQUEST as $key => $value) {

                if (strpos($key, 'billing_') === 0) {
                    $new_key = str_replace('billing_', '',$key);
                    $billing[$new_key] = $value;
                } elseif (strpos($key, 'shipping_') === 0) {

                    $new_key = str_replace('billing_', '',$key);
                    $shipping[$new_key] = $value;
                }

            }

            // Save data to Order
            $order->set_payment_method($_REQUEST['payment_method']);
            $order->set_address($billing, 'billing');
            $order->set_address($shipping, 'shipping');
            echo ($order->save());
            exit;
        } else {
            
            // Renew Order Items
            $order->remove_order_items();
            $order->set_cart_hash(WC()->cart->get_cart_hash());
            $checkout->set_data_from_cart($order);
    
            // Save Order
            $order->save();
    
            $mobbexGateway = WC()->payment_gateways->payment_gateways()[MOBBEX_WC_GATEWAY_ID];
    
            echo json_encode($mobbexGateway->process_payment($order_id));
            exit;
        }

    }

}

$mobbexGateway = new MobbexGateway;
add_action('plugins_loaded', [ & $mobbexGateway, 'init']);
