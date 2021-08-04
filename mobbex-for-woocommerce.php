<?php
/*
Plugin Name:  Mobbex for Woocommerce
Description:  A small plugin that provides Woocommerce <-> Mobbex integration.
Version:      3.3.3
WC tested up to: 4.6.1
Author: mobbex.com
Author URI: https://mobbex.com/
Copyright: 2020 mobbex.com
 */

require_once 'includes/utils.php';

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
        MobbexGateway::load_update_checker();
        MobbexGateway::check_dependencies();

        if (count(MobbexGateway::$errors)) {

            foreach (MobbexGateway::$errors as $error) {
                MobbexGateway::notice('error', $error);
            }

            return;
        }

        MobbexGateway::load_helper();
        MobbexGateway::load_order_admin();
        MobbexGateway::load_gateway();
        MobbexGateway::add_gateway();

        $helper = new MobbexHelper();
        if (!empty($helper->financial_info_active) && $helper->financial_info_active === 'yes') {
            // Add a new button after the "add to cart" button
            add_action('woocommerce_after_add_to_cart_form', [$this, 'additional_button_add_to_cart'], 20 );
        }

        // Enqueue assets
        add_action('wp_enqueue_scripts', [$this, 'mobbex_assets_enqueue']);
        add_action('admin_enqueue_scripts', [$this, 'load_admin_scripts']);

        // Add some useful things
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_action_links']);
        add_filter('plugin_row_meta', [$this, 'plugin_row_meta'], 10, 2);

        // Mobbex category management 
        add_action('product_cat_add_form_fields', [$this,'mobbex_category_panels']);
        add_action('product_cat_edit_form_fields', [$this,'mobbex_category_panels']);
        add_action('create_product_cat', [$this,'mobbex_category_save']);
        add_action('edited_product_cat', [$this,'mobbex_category_save']);

        // Mobbex product management tab
        add_filter('woocommerce_product_data_tabs', [$this, 'mobbex_product_settings_tabs']);
        add_action('woocommerce_product_data_panels', [$this, 'mobbex_product_panels']);
        add_action('woocommerce_process_product_meta', [$this, 'mobbex_product_save']);
        add_action('admin_head', [$this, 'mobbex_icon']);

        // Validate Cart items
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validate_cart_items'], 10, 2);

        // Checkout update actions
        add_action('woocommerce_api_mobbex_checkout_update', [$this, 'mobbex_checkout_update']);
        add_action('woocommerce_cart_emptied', function(){WC()->session->set('order_id', null);});
        add_action('woocommerce_add_to_cart', function(){WC()->session->set('order_id', null);});

        add_action('rest_api_init', function () {
            register_rest_route('mobbex/v1', '/webhook', [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'mobbex_webhook_api'],
                'permission_callback' => '__return_true',
            ]);
        });

        // Create financial widget shortcode
        add_shortcode('mobbex_button', [$this, 'shortcode_mobbex_button']);

        //support 'custon-logo' enable the usage of wp_get_attachment_image_src in gateway:getTheme()
        add_theme_support( 'custom-logo' );
    }

    /**
     * Check dependencies.
     *
     * @throws Exception
     */
    public static function check_dependencies()
    {
        if (!class_exists('WooCommerce')) {
            MobbexGateway::$errors[] = __('WooCommerce needs to be installed and activated.', 'mobbex-for-woocommerce');
        }

        if (!function_exists('WC')) {
            MobbexGateway::$errors[] = __('Mobbex requires WooCommerce to be activated', 'mobbex-for-woocommerce');
        }

        if (!is_ssl()) {
            MobbexGateway::$errors[] = __('Your site needs to be served via HTTPS to comunicate securely with Mobbex.', 'mobbex-for-woocommerce');
        }

        if (version_compare(WC_VERSION, '2.6', '<')) {
            MobbexGateway::$errors[] = __('Mobbex requires WooCommerce version 2.6 or greater', 'mobbex-for-woocommerce');
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

        load_plugin_textdomain('mobbex-for-woocommerce', false, dirname(plugin_basename(__FILE__)) . '/languages/');

    }

    public static function load_helper()
    {
        require_once plugin_dir_path(__FILE__) . 'includes/helper.php';
    }

    public static function load_order_admin()
    {
        require_once plugin_dir_path(__FILE__) . 'includes/admin/order.php';
        Mbbx_Order_Admin::init();
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

    public function mobbex_assets_enqueue()
    {
        global $post;
        $helper = new MobbexHelper();
        $dir_url = plugin_dir_url(__FILE__);
        // If dir url looks good
        if (!empty($dir_url) && substr($dir_url, -1) === '/') {
            // Product page
            if (is_product()) 
            {
                wp_register_script('mmbbx-product-button-js', plugin_dir_url(__FILE__) . 'assets/js/mobbex_product_financing.js');
                wp_enqueue_script('mmbbx-product-button-js');

                if(!empty($helper->financial_info_active)){
                    wp_register_style('mobbex_product_style', $dir_url . 'assets/css/product.css');
                    wp_enqueue_style('mobbex_product_style');
                }
            }
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
            wp_enqueue_style('mbbx-product-style', plugin_dir_url(__FILE__) . 'assets/css/product-admin.css');
            wp_enqueue_script('mbbx-product-js', plugin_dir_url(__FILE__) . 'assets/js/product-admin.js');
        }

        // Category admin page
        if (isset($current_screen->id) && $current_screen->id == 'edit-product_cat') {
            wp_enqueue_style('mbbx-category-style', plugin_dir_url(__FILE__) . 'assets/css/category-admin.css');
            wp_enqueue_script('mbbx-category-js', plugin_dir_url(__FILE__) . 'assets/js/category-admin.js');
        }

        // Plugin config page
        if ($hook == 'woocommerce_page_wc-settings' && isset($_GET['section']) && $_GET['section'] == 'mobbex') {
            wp_enqueue_style('mbbx-plugin-style', plugin_dir_url(__FILE__) . 'assets/css/plugin-config.css');
            wp_enqueue_script('mbbx-plugin-js', plugin_dir_url(__FILE__) . 'assets/js/plugin-config.js');
        }
    }

    /**
     * Add new button to show a modal with financial information
     * only if the checkbox of financial information is checked
     * @access public
     */
    public function additional_button_add_to_cart()
    {
        $helper = new MobbexHelper();

        // Trigger/Open The Modal if the checkbox is true in the plugin settings and tax_id is set
        if ($helper->financial_info_active)
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
    public function shortcode_mobbex_button()
    {
        global $post;

        // Shortcode only works in product page
        if ($post->post_type != 'product')
            return;

        include_once plugin_dir_path(__FILE__) . 'templates/financing-button.html';

        $helper  = new MobbexHelper();
        $product = wc_get_product($post->ID);
        $sources = $helper->get_list_source($product->get_price(), $product->get_id());

        $this->build_table_html($sources);//list with the payment methods and plans
        $this->build_list_html($sources);
        echo '<button  id="mbbxProductBtn" class="single_add_to_cart_button button alt">Ver Financiación</button>';

        // Send product data to javascript
        $data = [
            'price'        => $product->get_price(),
            'product_id'   => $product->get_id(),
            'product_type' => $product->get_type(),
        ];

        wp_localize_script('mmbbx-product-button-js', 'global_data_assets', $data);
        wp_enqueue_script('mmbbx-product-button-js');
    }

    /**
     * Creates html code for plans table
     */
    private function build_table_html($payment_methods)
    {
        ?>
        <table id="mobbex_payment_plans_list" style="max-width:100%;display:none;border: none;">
        <?php foreach($payment_methods as $method) : ?>
            <?php if (!empty($method['name'])) : ?>
                <tr id="<?= $method['reference'] ?>" class="mobbexPaymentMethod">
                    <th colspan="2">
                        <div>
                            <img src="https://res.mobbex.com/images/sources/<?= $method['reference'] ?>.jpg"><?= $method['name'] ?>
                        </div>
                    </th>
                </tr>
                <?php foreach($method['installments'] as $installment) : ?>
                    <tr id="<?= $method['reference'] ?>">
                        <td><?= $installment['name'] ?> </td>
                        <td style="text-align: center; ">$ <?= $installment['amount'] ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endforeach; ?>
        </table>
        <?php
    }

    /**
     * Return the select html element with all the payment methods
     */
    private function build_list_html($payment_methods)
    {
        ?>
        <select name="methods" id="mobbex_methods_list" style="width:100%;display:none;">
            <option id="0" value="0">Todos</option>
            <?php foreach($payment_methods as $method) : ?>
                <?php if (!empty($method['name'])) : ?>
                    <option id="<?= $method['reference'] ?>" value="<?= $method['reference'] ?>"><?= $method['name'] ?></option>
                <?php endif; ?>
            <?php endforeach; ?>
        </select>
        <?php
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
        $helper = new MobbexHelper();
        $id     = get_the_ID();

        $common_fields = $advanced_fields = [];

        // Get sources with common and advanced rule plans
        $sources                = $helper->get_sources();
        $sources_advanced       = $helper->get_sources_advanced();
        $checked_common_plans   = get_post_meta($id, 'common_plans', true) ?: [];
        $checked_advanced_plans = get_post_meta($id, 'advanced_plans', true) ?: [];

        // Support previus save method
        $checked_common_plans   = is_string($checked_common_plans)   ? unserialize($checked_common_plans)   : $checked_common_plans;
        $checked_advanced_plans = is_string($checked_advanced_plans) ? unserialize($checked_advanced_plans) : $checked_advanced_plans;

        // Create common plan fields
        foreach ($sources as $source) {
            $plans = !empty($source['installments']['list']) ? $source['installments']['list'] : [];

            foreach ($plans as $plan) {
                // Get value from common_plans post meta and check if it's saved using previus method
                $is_checked = (!in_array($plan['reference'], $checked_common_plans) && get_post_meta($id, $plan['reference'], true) !== 'yes');

                // Create field array data
                $common_fields[$plan['reference']] = [
                    'id'                => 'common_plan_' . $plan['reference'],
                    'value'             => $is_checked ? 'yes' : false,
                    'custom_attributes' => $is_checked ? 'checked' : '', // TODO: use cbvalue instead of custom_attributes
                    'label'             => $plan['description'] ?: $plan['name'],
                ];
            }
        }

        // Create advanced plan fields
        foreach ($sources_advanced as $source) {
            $plans      = !empty($source['installments']) ? $source['installments'] : [];
            $source_ref = $source['source']['reference'];

            // Save source name
            $source_names[$source_ref] = $source['source']['name'];

            foreach ($plans as $plan) {
                // Get value from advanced_plans post meta
                $is_checked = (is_array($checked_advanced_plans) && in_array($plan['uid'], $checked_advanced_plans));

                // Create field array data
                $advanced_fields[$source_ref][] = [
                    'id'                => 'advanced_plan_' . $plan['uid'],
                    'value'             => $is_checked ? 'yes' : false,
                    'custom_attributes' => $is_checked ? 'checked' : '',
                    'label'             => $plan['description'] ?: $plan['name'],
                ];
            }
        }

        ?>
        <div id="mobbex_product_data" class="panel woocommerce_options_panel hidden">
            <?php
            do_action('mbbx_product_options');
            ?>
            <h2><?=  __('Plans Configuration', 'mobbex-for-woocommerce') ?></h2>
            <p><?=  __('Select the plans you want to appear in the checkout', 'mobbex-for-woocommerce') ?></p>
            <div class="mbbx_plans_cont">
                <div class="mbbx_plan_list">
                    <p><?= __('Common plans', 'mobbex-for-woocommerce') ?></p>
                    <?php
                    foreach ($common_fields as $field) {
                        echo '<input type="hidden" name="' . $field['id'] . '" value="no">';
                        woocommerce_wp_checkbox($field);
                    }
                    ?>
                </div>
                <div class="mbbx_plan_list">
                    <p><?= __('Plans with advanced rules', 'mobbex-for-woocommerce') ?></p>
                    <?php
                    foreach ($advanced_fields as $source_ref => $fields) {
                    ?>
                        <div class='mbbx_plan_source'>
                            <img src='https://res.mobbex.com/images/sources/<?= $source_ref ?>.png'>
                            <p><?= $source_names[$source_ref] ?></p>
                        </div>
                        <?php
                        foreach ($fields as $field) {
                            woocommerce_wp_checkbox($field);
                        }
                    }
                    ?>
                </div>
            </div>
            <hr>
            <h2><?= __('Multisite', 'mobbex-for-woocommerce') ?></h2>
            <div>
                <?php
                // Render multisite fields
                $this->product_multisite_fields($id);
                ?>
            </div>
            <?php
            do_action('mbbx_product_options_end')
            ?>
        </div>
        <?php
    }

    /**
     * Render product multisite fields.
     * 
     * @param int|string $id Product ID
     */
    public function product_multisite_fields($id)
    {
        // Get store saved data
        $stores           = get_option('mbbx_stores') ?: [];
        $current_store_id = get_post_meta($id, 'mbbx_store', true) ?: '';

        // Get all store names
        $store_names = [];
        foreach ($stores as $store_id => $store)
            $store_names[$store_id] = $store['name'];

        // Get current store values
        $store_name = $store_api_key = $store_access_token = '';
        if (!empty($current_store_id) && !empty($stores[$current_store_id])) {
            $store_name         = $store['name'];
            $store_api_key      = $store['api_key'];
            $store_access_token = $store['access_token'];
        }

        // Create fields
        $enable_field = [
            'id'          => 'mbbx_enable_multisite',
            'cbvalue'     => true,
            'label'       => __('Enable Multisite', 'mobbex-for-woocommerce'),
            'description' => __('Enable it to allow payment for this product to be received by another merchant.', 'mobbex-for-woocommerce'),
        ];

        $store_field = [
            'id'            => 'mbbx_store',
            'value'         => $current_store_id,
            'label'         => __('Store', 'mobbex-for-woocommerce'),
            'wrapper_class' => 'really-hidden',
            'options'       => array_merge(['new' => __('New Store', 'mobbex-for-woocommerce')], $store_names),
        ];

        $store_name_field = [
            'id'            => 'mbbx_store_name',
            'value'         => $store_name,
            'label'         => __('New Store Name', 'mobbex-for-woocommerce'),
            'desc_tip'      => true,
            'wrapper_class' => 'really-hidden',
        ];

        $store_api_key_field = [
            'id'            => 'mbbx_api_key',
            'value'         => $store_api_key,
            'label'         => __('API Key', 'mobbex-for-woocommerce'),
            'description'   => __('Your Mobbex API key.', 'mobbex-for-woocommerce'),
            'desc_tip'      => true,
            'wrapper_class' => 'really-hidden',
        ];

        $store_access_token_field = [
            'id'            => 'mbbx_access_token',
            'value'         => $store_access_token,
            'label'         => __('Access Token', 'mobbex-for-woocommerce'),
            'description'   => __('Your Mobbex access token.', 'mobbex-for-woocommerce'),
            'desc_tip'      => true,
            'wrapper_class' => 'really-hidden',
        ];

        woocommerce_wp_checkbox($enable_field);
        woocommerce_wp_select($store_field);
        woocommerce_wp_text_input($store_name_field);
        woocommerce_wp_text_input($store_api_key_field);
        woocommerce_wp_text_input($store_access_token_field);
    }

    public function mobbex_product_save($post_id)
    {
        $common_plans = $advanced_plans = [];
        $post_fields  = $_POST;

        // Remove values saved with previus method
        foreach (MobbexHelper::$ahora as $plan) {
            if (get_post_meta($post_id, $plan, true))
                delete_post_meta($post_id, $plan);
        }

        // Get plans selected
        foreach ($post_fields as $id => $value) {
            if (strpos($id, 'common_plan_') !== false && $value === 'no') {
                $uid = explode('common_plan_', $id)[1];
                $common_plans[] = $uid;
            } else if (strpos($id, 'advanced_plan_') !== false && $value === 'yes'){
                $uid = explode('advanced_plan_', $id)[1];
                $advanced_plans[] = $uid;
            }
        }

        // Get multisite options
        $enable_ms    = !empty($post_fields['mbbx_enable_multisite']) ? $post_fields['mbbx_enable_multisite'] : false;
        $store        = !empty($post_fields['mbbx_store']) ? $post_fields['mbbx_store'] : false;
        $store_name   = !empty($post_fields['mbbx_store_name']) ? $post_fields['mbbx_store_name'] : false;
        $api_key      = !empty($post_fields['mbbx_api_key']) ? $post_fields['mbbx_api_key'] : false;
        $access_token = !empty($post_fields['mbbx_access_token']) ? $post_fields['mbbx_access_token'] : false;

        // Save all data as post meta
        update_post_meta($post_id, 'common_plans', $common_plans);
        update_post_meta($post_id, 'advanced_plans', $advanced_plans);
        update_post_meta($post_id, 'mbbx_enable_multisite', $enable_ms);

        // Get current stores
        $stores = get_option('mbbx_stores') ?: [];

        if ($store === 'new' && $enable_ms && $store_name && $api_key && $access_token) {
            // Create and save new store
            $new_store_id          = md5("$api_key|$access_token");
            $stores[$new_store_id] = [
                'name'         => $store_name,
                'api_key'      => $api_key,
                'access_token' => $access_token,
            ];

            update_option('mbbx_stores', $stores);
            update_post_meta($post_id, 'mbbx_store', $new_store_id);
        } else if (!empty($stores[$store]) && $enable_ms) {
            // If store already exists, save selection
            update_post_meta($post_id, 'mbbx_store', $store);
        }
    }

    /**
     * Add Payment plans filter to category edition and creation page.
     * 
     * @param WP_Term $term
     */
    public function mobbex_category_panels($term)
    {
        // Get category ID
        $term_id = $term->term_id;

        echo '<div id="mobbex_category_data" class="form-field">';
        echo '<h2><b>' . __('Choose the plans you want NOT to appear during the purchase', 'mobbex-for-woocommerce') . ':</b></h2>';

        // All 'ahora' plans
        $plans = array(
            'ahora_3'  => 'Ahora 3',
            'ahora_6'  => 'Ahora 6',
            'ahora_12' => 'Ahora 12',
            'ahora_18' => 'Ahora 18',
        );
        
        foreach ($plans as $key => $value) {
            $checkbox_data = array(
                'id'      => $key,
                'value'   => get_term_meta($term_id, $key, true),
                'label'   => $value,
            );

            // if the plan was selected before its need to be check true
            if (get_term_meta($term_id, $key, true) === 'yes') {
                $checkbox_data['custom_attributes'] = 'checked';
            }
            woocommerce_wp_checkbox($checkbox_data);//Add the checkbox as array    
        }

        $this->category_multisite_fields($term_id);

        echo '</div>';
    }

    /**
     * Render category multisite fields.
     * 
     * @param int|string $id Category ID
     */
    public function category_multisite_fields($id)
    {
        // Get store saved data
        $stores           = get_option('mbbx_stores') ?: [];
        $current_store_id = get_term_meta($id, 'mbbx_store', true) ?: '';
        $enable_ms        = get_term_meta($id, 'mbbx_enable_multisite', true);

        // Get all store names
        $store_names = [];
        foreach ($stores as $store_id => $store)
            $store_names[$store_id] = $store['name'];

        // Get current store values
        $store_name = $store_api_key = $store_access_token = '';
        if (!empty($current_store_id) && !empty($stores[$current_store_id])) {
            $store_name         = $store['name'];
            $store_api_key      = $store['api_key'];
            $store_access_token = $store['access_token'];
        }

        // Creation form requires us to create a table
        $is_creation = current_action() == 'product_cat_add_form_fields';
        if ($is_creation)
            echo '<table><tbody class="mbbx_multisite_table">';

        // Create fields
        ?>
        <tr class="form-field">
            <th scope="row" valign="top"><p><?=  __('Multisite', 'mobbex-for-woocommerce') ?></p></th>
        </tr>
        <tr class="form-field">
            <th scope="row" valign="top"><label for="mbbx_enable_multisite"><?= __('Enable Multisite', 'mobbex-for-woocommerce') ?></label></th>
            <td>
                <input type="checkbox" name="mbbx_enable_multisite" id="mbbx_enable_multisite" value="yes" <?= checked($enable_ms, true, false) ?>>
                <p class="description"><?= __('Enable it to allow payment for this product to be received by another merchant.', 'mobbex-for-woocommerce') ?></p>
            </td>
        </tr>
        <tr class="form-field mbbx_store_field">
            <th scope="row" valign="top"><label for="mbbx_store"><?= __('Store', 'mobbex-for-woocommerce') ?></label></th>
            <td>
                <select name="mbbx_store" id="mbbx_store">
                    <option value="new"><?= __('New Store', 'mobbex-for-woocommerce') ?></option>
                    <?php foreach ($store_names as $id => $name) : ?>
                        <option value="<?= $id ?>" <?= selected($id, $current_store_id, false) ?>><?= $name ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr class="form-field mbbx_store_name_field">
            <th scope="row" valign="top"><label for="mbbx_store_name"><?= __('New Store Name', 'mobbex-for-woocommerce') ?></label></th>
            <td>
                <input type="text" name="mbbx_store_name" id="mbbx_store_name" value="<?= $store_name ?>">
            </td>
        </tr>
        <tr class="form-field mbbx_api_key_field">
            <th scope="row" valign="top"><label for="mbbx_api_key"><?= __('API Key', 'mobbex-for-woocommerce') ?></label></th>
            <td>
                <input type="text" name="mbbx_api_key" id="mbbx_api_key" value="<?= $store_api_key ?>">
                <p class="description"><?= __('Your Mobbex API key.', 'mobbex-for-woocommerce') ?></p>
            </td>
        </tr>
        <tr class="form-field mbbx_access_token_field">
            <th scope="row" valign="top"><label for="mbbx_access_token"><?= __('Access Token', 'mobbex-for-woocommerce') ?></label></th>
            <td>
                <input type="text" name="mbbx_access_token" id="mbbx_access_token" value="<?= $store_access_token ?>">
                <p class="description"><?= __('Your Mobbex access token.', 'mobbex-for-woocommerce') ?></p>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row" valign="top" colspan="2"><hr></th>
        </tr>
        <?php

        if ($is_creation)
            echo '</table></tbody">';
    }

    /**
     * Save the category meta data after save/update, including the selection(check) of payment plans
     */
    public function mobbex_category_save($term_id)
    {
        $plans = array(
            'ahora_3'  => false,
            'ahora_6'  => false,
            'ahora_12' => false,
            'ahora_18' => false,
        );

        foreach ($plans as $key => $value) {
            if (isset($_POST[$key]) && $_POST[$key] === 'yes') {
                $value = 'yes';
            }
            update_term_meta($term_id, $key , $value);//save the meta data
        }

        // Get multisite options
        $enable_ms    = !empty($_POST['mbbx_enable_multisite']) && $_POST['mbbx_enable_multisite'] === 'yes';
        $store        = !empty($_POST['mbbx_store']) ? $_POST['mbbx_store'] : false;
        $store_name   = !empty($_POST['mbbx_store_name']) ? $_POST['mbbx_store_name'] : false;
        $api_key      = !empty($_POST['mbbx_api_key']) ? $_POST['mbbx_api_key'] : false;
        $access_token = !empty($_POST['mbbx_access_token']) ? $_POST['mbbx_access_token'] : false;

        update_term_meta($term_id, 'mbbx_enable_multisite', $enable_ms);

        // Get current stores
        $stores = get_option('mbbx_stores') ?: [];

        if ($store === 'new' && $enable_ms && $store_name && $api_key && $access_token) {
            // Create and save new store
            $new_store_id          = md5("$api_key|$access_token");
            $stores[$new_store_id] = [
                'name'         => $store_name,
                'api_key'      => $api_key,
                'access_token' => $access_token,
            ];

            update_option('mbbx_stores', $stores);
            update_term_meta($term_id, 'mbbx_store', $new_store_id);
        } else if (!empty($stores[$store]) && $enable_ms) {
            // If store already exists, save selection
            update_term_meta($term_id, 'mbbx_store', $store);
        }
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
}

$mobbexGateway = new MobbexGateway;
add_action('plugins_loaded', [ & $mobbexGateway, 'init']);