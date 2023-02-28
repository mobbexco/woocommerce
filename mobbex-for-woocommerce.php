<?php
/*
Plugin Name:  Mobbex for Woocommerce
Description:  A small plugin that provides Woocommerce <-> Mobbex integration.
Version:      3.12.0
WC tested up to: 4.6.1
Author: mobbex.com
Author URI: https://mobbex.com/
Copyright: 2020 mobbex.com
 */

require_once 'includes/utils.php';
require_once 'includes/config.php';
require_once 'includes/helper.php';
require_once 'includes/logger.php';
require_once 'includes/class-api.php';
require_once 'includes/class-checkout.php';
require_once 'includes/class-exception.php';
require_once 'includes/admin/order.php';
require_once 'includes/admin/product.php';
require_once 'includes/helper/class-order-helper.php';
require_once 'includes/helper/class-cart-helper.php';
require_once 'controllers/payment.php';
require_once 'controllers/checkout.php';

class MobbexGateway
{
    /** @var \Mobbex\WP\Checkout\Includes\Config */
    public static $config;

    /** @var MobbexHelper */
    public static $helper;
    
    /** @var MobbexLogger */
    public static $logger;

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
        self::$config = new \Mobbex\WP\Checkout\Includes\Config();
        self::$helper = new \MobbexHelper();
        self::$logger = new \MobbexLogger();

        MobbexGateway::load_textdomain();
        MobbexGateway::load_update_checker();
        MobbexGateway::check_dependencies();
        MobbexGateway::check_upgrades();

        if (MobbexGateway::$errors) {
            foreach (MobbexGateway::$errors as $error)
                self::$logger->notice($error);

            return;
        }

        self::check_warnings();

        // Init order and product admin settings
        Mbbx_Order_Admin::init();
        Mbbx_Product_Admin::init();

        // Add Mobbex gateway
        MobbexGateway::load_gateway();
        MobbexGateway::add_gateway();

        // Init controllers
        new \Mobbex\Controller\Payment;
        new \Mobbex\Controller\Checkout;

        if (self::$config->financial_info_active === 'yes')
            add_action('woocommerce_after_add_to_cart_form', [$this, 'display_finnacial_button']);

        if (self::$config->financial_widget_on_cart === 'yes')
            add_action('woocommerce_after_cart_totals', [$this, 'display_finnacial_button'], 1);

        add_action('rest_api_init', function () {
            register_rest_route('mobbex/v1', '/widget', [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'financial_widget_update'],
                'permission_callback' => '__return_true',
            ]);
        });

        // Enqueue assets
        add_action('wp_enqueue_scripts', [$this, 'mobbex_assets_enqueue']);
        add_action('admin_enqueue_scripts', [$this, 'load_admin_scripts']);

        // Add some useful things
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_action_links']);
        add_filter('plugin_row_meta', [$this, 'plugin_row_meta'], 10, 2);

        // Validate Cart items
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validate_cart_items'], 10, 2);

        // Create financial widget shortcode
        add_shortcode('mobbex_button', [$this, 'shortcode_mobbex_button']);

        // Display payment options on checkout
        add_filter('woocommerce_available_payment_gateways', [$this, 'load_payment_options']);
        add_filter('wc_get_template', [$this, 'load_payment_template'], 10, 3);
    }

    /**
     * Check dependencies.
     *
     * @throws Exception
     */
    public static function check_dependencies()
    {
        if (!class_exists('WooCommerce') || !function_exists('WC') || version_compare(defined('WC_VERSION') ? WC_VERSION : '', '2.6', '<')) {
            MobbexGateway::$errors[] = __('WooCommerce version 2.6 or greater needs to be installed and activated.', 'mobbex-for-woocommerce');
        }

        if (!is_ssl()) {
            MobbexGateway::$errors[] = __('Your site needs to be served via HTTPS to comunicate securely with Mobbex.', 'mobbex-for-woocommerce');
        }

        if (!function_exists('curl_init')) {
            MobbexGateway::$errors[] = __('Mobbex requires the cURL PHP extension to be installed on your server', 'mobbex-for-woocommerce');
        }

        if (!function_exists('json_decode')) {
            MobbexGateway::$errors[] = __('Mobbex requires the JSON PHP extension to be installed on your server', 'mobbex-for-woocommerce');
        }

        $openssl_warning = __('Mobbex requires OpenSSL >= 1.0.1 to be installed on your server', 'mobbex-for-woocommerce');
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
    }

    /**
     * Check pending database upgrades and upgrade if is needed.
     */
    public static function check_upgrades()
    {
        try {
            $db_version = get_option('woocommerce-mobbex-version');

            if ($db_version < '3.6.0')
                create_mobbex_transaction_table();

            // Update db version
            if ($db_version != MOBBEX_VERSION)
                update_option('woocommerce-mobbex-version', MOBBEX_VERSION);
        } catch (\Exception $e) {
            self::$errors[] = 'Mobbex DB Upgrade error';
        }
    }

    /**
     * Check and log minor problems of install.
     */
    public static function check_warnings()
    {
        // Check install directory
        if (basename(__DIR__) == 'woocommerce-master')
            self::$logger->notice(sprintf(
                'El directorio de instalación es incorrecto (<code>%s</code>). Si descargó el zip directamente del repositorio, reinstale el plugin utilizando el archivo <code>%s</code> de <a href="%s">%3$s</a>',
                basename(__DIR__),
                'wc-mobbex.x.y.z.zip',
                'https://github.com/mobbexco/woocommerce/releases/latest'
            ));

        // Check if credentials are configured
        if (self::$config->enabled == 'yes' && (!self::$config->api_key || !self::$config->access_token))
            self::$logger->notice(sprintf(
                'Debe especificar el API Key y Access Token en la <a href="%s">configuración</a>.',
                admin_url('admin.php?page=wc-settings&tab=checkout&section=mobbex')
            ));
    }

    public function add_action_links($links)
    {
        $plugin_links = [
            '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=mobbex') . '">' . __('Settings', 'mobbex-for-woocommerce') . '</a>',
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
                '<a href="' . esc_url(MobbexGateway::$site_url) . '" target="_blank">' . __('Website', 'mobbex-for-woocommerce') . '</a>',
                '<a href="' . esc_url(MobbexGateway::$doc_url) . '" target="_blank">' . __('Documentation', 'mobbex-for-woocommerce') . '</a>',
                '<a href="' . esc_url(MobbexGateway::$github_url) . '" target="_blank">' . __('Contribute', 'mobbex-for-woocommerce') . '</a>',
                '<a href="' . esc_url(MobbexGateway::$github_issues_url) . '" target="_blank">' . __('Report Issues', 'mobbex-for-woocommerce') . '</a>',
            ];

            $links = array_merge($links, $plugin_links);
        }

        return $links;
    }

    public static function load_textdomain()
    {
        load_plugin_textdomain('mobbex-for-woocommerce', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }

    public static function load_update_checker()
    {
        require 'plugin-update-checker/plugin-update-checker.php';
        $myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
            'https://github.com/mobbexco/woocommerce/',
            __FILE__,
            'mobbex-plugin-update-checker'
        );
        $myUpdateChecker->getVcsApi()->enableReleaseAssets();
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

    public function mobbex_assets_enqueue()
    {
        global $post;

        $dir_url = plugin_dir_url(__FILE__);

        // Only if directory url looks good
        if (empty($dir_url) || substr($dir_url, -1) != '/')
            return self::$logger->debug('Mobbex Enqueue Error: Invalid directory URL', $dir_url, is_checkout() || is_product());

        // Product page
        if (is_product() || (isset($post->post_content) && has_shortcode($post->post_content, 'mobbex_button'))) {
            wp_enqueue_script('mbbx-product-button-js', $dir_url . 'assets/js/finance-widget.js', null, MOBBEX_VERSION);
            wp_enqueue_style('mobbex_product_style', $dir_url . 'assets/css/product.css', null, MOBBEX_VERSION);

            wp_localize_script('mbbx-product-button-js', 'mobbexWidget', [
                'widgetUpdateUrl' => get_rest_url(null, 'mobbex/v1/widget')
            ]);
            wp_enqueue_script('mbbx-product-button-js', $dir_url . 'assets/js/finance-widget.js', null, MOBBEX_VERSION);
        }

        // Checkout page
        if (is_checkout()) {
            // Exclude scripts from cache plugins minification
            !defined('DONOTCACHEPAGE') && define('DONOTCACHEPAGE', true);
            !defined('DONOTMINIFY') && define('DONOTMINIFY', true);

            wp_enqueue_script('mobbex-embed', 'https://res.mobbex.com/js/embed/mobbex.embed@' . MOBBEX_EMBED_VERSION . '.js', null, MOBBEX_VERSION);
            wp_enqueue_script('mobbex-sdk', 'https://res.mobbex.com/js/sdk/mobbex@' . MOBBEX_SDK_VERSION . '.js', null, MOBBEX_VERSION);

            // Enqueue payment asset files
            wp_enqueue_style('mobbex-checkout-style', $dir_url . 'assets/css/checkout.css', null, MOBBEX_VERSION);
            wp_register_script('mobbex-checkout-script', $dir_url . 'assets/js/mobbex.bootstrap.js', ['jquery'], MOBBEX_VERSION);

            wp_localize_script('mobbex-checkout-script', 'mobbex_data', [
                'is_pay_for_order' => !empty($_GET['pay_for_order']),
            ]);
            wp_enqueue_script('mobbex-checkout-script');
        }
    }

    /**
     * Load all admin scripts and styles.
     * 
     * @param string $hook
     */
    public function load_admin_scripts($hook)
    {
        global $post, $current_screen;

        // Product admin page
        if (($hook == 'post-new.php' || $hook == 'post.php') && $post->post_type == 'product') {
            wp_enqueue_style('mbbx-product-style', plugin_dir_url(__FILE__) . 'assets/css/product-admin.css', null, MOBBEX_VERSION);
            wp_enqueue_script('mbbx-product-js', plugin_dir_url(__FILE__) . 'assets/js/product-admin.js', null, MOBBEX_VERSION);
        }

        // Category admin page
        if (isset($current_screen->id) && $current_screen->id == 'edit-product_cat') {
            wp_enqueue_style('mbbx-category-style', plugin_dir_url(__FILE__) . 'assets/css/category-admin.css', null, MOBBEX_VERSION);
            wp_enqueue_script('mbbx-category-js', plugin_dir_url(__FILE__) . 'assets/js/category-admin.js', null, MOBBEX_VERSION);
        }

        // Plugin config page
        if ($hook == 'woocommerce_page_wc-settings' && isset($_GET['section']) && $_GET['section'] == 'mobbex') {
            wp_enqueue_style('mbbx-plugin-style', plugin_dir_url(__FILE__) . 'assets/css/plugin-config.css', null, MOBBEX_VERSION);
            wp_enqueue_script('mbbx-plugin-js', plugin_dir_url(__FILE__) . 'assets/js/plugin-config.js', null, MOBBEX_VERSION);
        }
    }

    /**
     * Display finance widget open button in product page.
     */
    public function display_finnacial_button()
    {
        do_shortcode('[mobbex_button]');
    }

    /**
     * Add new button to show a modal with financial information
     * only if the checkbox of financial information is checked
     * Shortcode function, return button html
     * and a hidden table with plans
     * in woocommerce echo do_shortcode('[mobbex_button]'); in content-single-product.php
     * or [mobbex_button] in wordpress pages
     */
    public function shortcode_mobbex_button($params)
    {
        global $post;

        // Try to get shortcode params
        if (isset($params['price'])) {
            $price        = $params['price'];
            $products_ids = isset($params['products_ids']) ? explode(',', $params['products_ids']) : [];
        } else if (is_cart()) {
            $price        = WC()->cart->get_total(null);
            $products_ids = array_column(WC()->cart->get_cart() ?: [], 'product_id');
        } else if ($post && $post->post_type == 'product') {
            $price        = wc_get_product($post->ID)->get_price();
            $products_ids = [$post->ID];
        } else {
            return;
        }

        // Try to enqueue scripts
        wp_enqueue_script('mbbx-product-button-js', plugin_dir_url(__FILE__) . 'assets/js/finance-widget.js', null, MOBBEX_VERSION);
        wp_enqueue_style('mobbex_product_style', plugin_dir_url(__FILE__) . 'assets/css/product.css', null, MOBBEX_VERSION);

        $data = [
            'price'   => $price,
            'sources' => self::$helper->get_sources($price, self::$helper->get_installments($products_ids)),
            'style'   => [
                'show_button'   => isset($params['show_button']) ? $params['show_button'] : true,
                'theme'         => self::$config->visual_theme,
                'custom_styles' => self::$config->financial_widget_styles,
                'text'          => self::$config->financial_widget_button_text,
                'logo'          => self::$config->financial_widget_button_logo
            ]
        ];

        include_once plugin_dir_path(__FILE__) . 'templates/finance-widget.php';
    }

    /**
     * Creates a updated financial widget with selected variant price & returns it in a string
     * 
     * @return string $widget
     */
    public function financial_widget_update()
    {
        if (empty($_POST['variantPrice']) || empty($_POST['variantId']))
            exit;

        ob_start() && do_shortcode('[mobbex_button ' . http_build_query([
            'price'        => $_POST['variantPrice'],
            'products_ids' => implode(',', [$_POST['variantId']]),
            'show_button'  => false,
        ], '', ' ') . ']');

        return ob_get_clean();
    }

    /**
     * Check that the Cart does not have products from different stores.
     * 
     * @param bool $valid
     * @param int $product_id
     * 
     * @return bool $valid
     */
    public static function validate_cart_items($valid, $product_id)
    {
        $cart_items = !empty(WC()->cart->get_cart()) ? WC()->cart->get_cart() : [];

        // Get store from current product
        $product_store = MobbexHelper::get_store_from_product($product_id);

        // Get stores from cart items
        foreach ($cart_items as $item) {
            $item_store = MobbexHelper::get_store_from_product($item['product_id']);

            // If there are different stores in the cart items
            if ($product_store != $item_store) {
                wc_add_notice(__('The cart cannot have products from different sellers at the same time.', 'mobbex-for-woocommerce'), 'error');
                return false;
            }
        }

        return $valid;
    }

    /**
     * Load payment options on gateway to show in checkout.
     *  
     * @param array $options
     * 
     * @return array 
     */
    public function load_payment_options($options)
    {
        if (is_cart() || is_order_received_page() || !is_checkout() || !self::$helper->isReady() || empty($options['mobbex']))
            return $options;

        // Get checkout from context loaded object
        $response = self::$helper->get_context_checkout();

        // Add cards and payment methods to gateway
        $options['mobbex']->cards   = isset($response['wallet']) ? $response['wallet'] : [];
        $options['mobbex']->methods = isset($response['paymentMethods']) ? $response['paymentMethods'] : [];

        return $options;
    }

    /**
     * Load own template to show payment options in checkout.
     *  
     * @param array $options
     * 
     * @return array 
     */
    public function load_payment_template($template, $template_name, $args)
    {
        if (!self::$helper->isReady() || $template_name != 'checkout/payment-method.php' || $args['gateway']->id != 'mobbex' || self::$config->disable_template == 'yes')
            return $template;

        return plugin_dir_path(__FILE__) . 'templates/payment-options.php';
    }
}

function create_mobbex_transaction_table()
{
    global $wpdb;

    $wpdb->get_results(
        'CREATE TABLE IF NOT EXISTS ' . $wpdb->prefix . 'mobbex_transaction('
            . 'id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,'
            . 'order_id INT(11) NOT NULL,'
            . 'parent TEXT NOT NULL,'
            . 'operation_type TEXT NOT NULL,'
            . 'payment_id TEXT NOT NULL,'
            . 'description TEXT NOT NULL,'
            . 'status_code TEXT NOT NULL,'
            . 'status_message TEXT NOT NULL,'
            . 'source_name TEXT NOT NULL,'
            . 'source_type TEXT NOT NULL,'
            . 'source_reference TEXT NOT NULL,'
            . 'source_number TEXT NOT NULL,'
            . 'source_expiration TEXT NOT NULL,'
            . 'source_installment TEXT NOT NULL,'
            . 'installment_name TEXT NOT NULL,'
            . 'installment_amount DECIMAL(18,2) NOT NULL,'
            . 'installment_count TEXT NOT NULL,'
            . 'source_url TEXT NOT NULL,'
            . 'cardholder TEXT NOT NULL,'
            . 'entity_name TEXT NOT NULL,'
            . 'entity_uid TEXT NOT NULL,'
            . 'customer TEXT NOT NULL,'
            . 'checkout_uid TEXT NOT NULL,'
            . 'total DECIMAL(18,2) NOT NULL,'
            . 'currency TEXT NOT NULL,'
            . 'risk_analysis TEXT NOT NULL,'
            . 'data TEXT NOT NULL,'
            . 'created TEXT NOT NULL,'
            . 'updated TEXT NOT NULL'
            . ');'
    );
}

$mobbexGateway = new MobbexGateway;
add_action('plugins_loaded', [&$mobbexGateway, 'init']);
register_activation_hook(__FILE__, 'create_mobbex_transaction_table');

// Remove mbbx entity saved data on uninstall
register_deactivation_hook(__FILE__, function() {
    update_option('mbbx_entity', '');
});
