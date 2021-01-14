<?php
/*
Plugin Name:  Mobbex for Woocommerce
Description:  A small plugin that provides Woocommerce <-> Mobbex integration.
Version:      3.0.3
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
        MobbexGateway::load_helper();
        MobbexGateway::load_update_checker();
        MobbexGateway::check_dependencies();

        if (count(MobbexGateway::$errors)) {

            foreach (MobbexGateway::$errors as $error) {
                MobbexGateway::notice('error', $error);
            }

            return;
        }

        MobbexGateway::load_gateway();
        MobbexGateway::add_gateway();

        //Add a new button after the "add to cart" button
        add_action( 'woocommerce_after_add_to_cart_button', [$this,'additional_button_add_to_cart'], 20 );

        // Add some useful things
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_action_links']);
        add_filter('plugin_row_meta', [$this, 'plugin_row_meta'], 10, 2);

        // Mobbex category management 
        //Category Creation
        add_action('product_cat_add_form_fields', [$this,'mobbex_category_panels'], 10, 1);
        add_action('create_product_cat', [$this,'mobbex_category_save'], 10, 1);
        //Category Edition
        add_action('edited_product_cat', [$this,'mobbex_category_save'], 10, 1);
        add_action('product_cat_edit_form_fields', [$this,'mobbex_category_panels_edit'], 10, 1);
        
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

    public static function load_helper()
    {
        require_once plugin_dir_path(__FILE__) . 'includes/helper.php';
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


    /**
     * Add new button to show a modal with financial information
     * only if the checkbox of financial information is checked
     * @access public
     */
    function additional_button_add_to_cart() {
        ?>
        <Style>
            /* The Modal (background) */
            .modal {
                display: none; /* Hidden by default */
                position: fixed; /* Stay in place */
                z-index: 1; /* Sit on top */
                left: 0;
                top: 0;
                width: 100%; /* Full width */
                height: 100%; /* Full height */
                overflow: auto; /* Enable scroll if needed */
                background-color: rgb(0,0,0); /* Fallback color */
                background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
            }
            
            /* Modal Content/Box */
            .modal-content {
                background-color: #fefefe;
                margin: 10% auto auto; /* 15% from the top and centered */
                padding: 20px;
                border: 1px solid #888;
                width: 60%; /* Could be more or less, depending on screen size */
                height: 100%; /* Full height */
            }
            /* The Close Button */
            .close {
                color: #aaa;
                float: right;
                font-size: 28px;
                font-weight: bold;
            }
            
            .close:hover,
            .close:focus {
                color: black;
                text-decoration: none;
                cursor: pointer;
            } 
        </Style>
        <?php
            global $product;
            //Get the Tax_id(CUIT) from plugin settings
            $mobbexGateway = WC()->payment_gateways->payment_gateways()[MOBBEX_WC_GATEWAY_ID];
            //Set Financial info URL
            $url_information = "https://mobbex.com/p/sources/widget/arg/".$mobbexGateway->tax_id."/?total=".$product->get_price();
            $is_active = $mobbexGateway->financial_info_active;
            
            // Only for simple product type
            if( ! $product->is_type('simple') ) return;
            
            // Trigger/Open The Modal if the checkbox is true in the plugin settings and tax_id is set
            if($is_active && $mobbexGateway->tax_id){
                echo '<button id="myBtn">Ver Financiación</button>';
                echo sprintf('<div id="product_total_price" style="margin-bottom:20px;">%s %s</div>',__('Product Total:','woocommerce'),'<span class="price">'.$product->get_price().'</span>');
            }
            
        ?>
        <!-- The Modal -->
        <div id="myModal" class="modal">
            <!-- Modal content -->
            <div class="modal-content">
                <span class="close">&times;</span>
                <iframe id="iframe" src=<?php echo $url_information ?>></iframe>
            </div>
        </div>
        <script>
            // Get the modal
            var modal = document.getElementById("myModal");

            // Get the button that opens the modal
            var btn = document.getElementById("myBtn");

            // Get the <span> element that closes the modal
            var span = document.getElementsByClassName("close")[0];

            // When the user clicks on <span> (x), close the modal
            span.onclick = function() {
                modal.style.display = "none";
            }

            // When the user clicks on the button, show/open the modal
            btn.onclick  = function(e) {
                e.preventDefault();
                modal.style.display = "block";
                window.dispatchEvent(new Event('resize'));
                document.getElementById('iframe').style.width = "100%"; 
                document.getElementById('iframe').style.height = "100%"; 
                return false;
            }

            // When the user clicks anywhere outside of the modal, close it
            window.onclick = function(event) {
                if (event.target == modal) {
                    modal.style.display = "none";
                }
            } 

            //acumulate poduct price based in the quantity
            jQuery(function($){
                var price = <?php echo $product->get_price(); ?>,
                taxId = <?php echo $mobbexGateway->tax_id; ?>,
                currency = '<?php echo get_woocommerce_currency_symbol(); ?>';

                $('[name=quantity]').change(function(){
                    if (!(this.value < 1)) {

                        var product_total = parseFloat(price * this.value);
                        $('#product_total_price .price').html( currency + product_total.toFixed(2));
                        //change the value send to the service
                        document.getElementById("iframe").src = "https://mobbex.com/p/sources/widget/arg/"+ taxId +'?total='+product_total;

                    }
                });
            });

        </script>
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

        echo '<div id="mobbex_product_data" class="panel woocommerce_options_panel hidden">';
        echo '<h2>' . __('Enable for plans to NOT appear at checkout for this product', MOBBEX_WC_TEXT_DOMAIN) . ':</h2>';
        echo '<p>' . __('Common plans in all payment methods', MOBBEX_WC_TEXT_DOMAIN) . ':</p>';

        $ahora = array(
            'ahora_3'  => 'Ahora 3',
            'ahora_6'  => 'Ahora 6',
            'ahora_12' => 'Ahora 12',
            'ahora_18' => 'Ahora 18',
        );

        // Set rendered plans so there are no duplicates
        $rendered_plans = [];

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
            $rendered_plans[] = $key;
        }

        // Get sources with common plans
        $sources = $helper->get_sources();
        $checked_common_plans = unserialize(get_post_meta(get_the_ID(), 'common_plans', true));

        foreach ($sources as $source) {
            // If source has plans render checkboxes
            if (!empty($source['installments']['list'])) {
                $installments = $source['installments']['list'];

                foreach ($installments as $installment) {
                    // If it hasn't been rendered yet
                    if (!in_array($installment['reference'], $rendered_plans)) {
                        $is_checked = is_array($checked_common_plans) ? in_array($installment['reference'], $checked_common_plans) : false;

                        $checkbox_data = [
                            'id'      => 'common_plan_' . $installment['reference'],
                            'value'   => $is_checked ? 'yes' : false,
                            'label'   => $installment['description'] ? : $installment['name'],
                        ];

                        if ($is_checked) {
                            $checkbox_data['custom_attributes'] = 'checked';
                        }

                        woocommerce_wp_checkbox($checkbox_data);
                        $rendered_plans[] = $installment['reference'];
                    }
                }
            }
        }

        // Get sources with advanced rule plans
        $sources_advanced = $helper->get_sources_advanced();
        $checked_advanced_plans = unserialize(get_post_meta(get_the_ID(), 'advanced_plans', true));

        echo '<hr><h2>' . __('Enable for plans to appear at checkout for this product', MOBBEX_WC_TEXT_DOMAIN) . ':</h2>';
        echo '<p>' . __('Plans with advanced rules by payment method', MOBBEX_WC_TEXT_DOMAIN) . ':</p>';
        
        foreach ($sources_advanced as $source) {
            if (!empty($source['installments'])) {
                echo '
                    <div style="display: flex; align-items: center; padding-left: 15px;">
                        <img src="https://res.mobbex.com/images/sources/' . $source['source']['reference'] . '.png" style="border-radius: 100%; width: 40px;">
                        <p>' . $source['source']['name'] . ':' . '</p>
                    </div>';

                foreach ($source['installments'] as $installment) {
                    if (!in_array($installment['uid'], $rendered_plans)) {
                        $is_checked = is_array($checked_advanced_plans) ? in_array($installment['uid'], $checked_advanced_plans) : false;

                        $checkbox_data = array(
                            'id'      => 'advanced_plan_' . $installment['uid'],
                            'value'   => $is_checked ? 'yes' : false,
                            'label'   => $installment['description'] ? : $installment['name'],
                        );

                        if ($is_checked) {
                            $checkbox_data['custom_attributes'] = 'checked';
                        }

                        woocommerce_wp_checkbox($checkbox_data);
                        $rendered_plans[] = $installment['uid'];
                    }
                }
            }
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

        $common_plans = [];
        $advanced_plans = [];
        $post_fields = $_POST;

        // Get plans selected and save as meta data
        foreach ($post_fields as $id => $value) {
            if (strpos($id, 'common_plan_') !== false && $value === 'yes') {
                $uid = explode('common_plan_', $id)[1];
                $common_plans[] = $uid;
            } else if (strpos($id, 'advanced_plan_') !== false && $value === 'yes'){
                $uid = explode('advanced_plan_', $id)[1];
                $advanced_plans[] = $uid;
            } else {
                unset($post_fields[$id]);
            }
        }
        $product->update_meta_data('common_plans', serialize($common_plans));
        $product->update_meta_data('advanced_plans', serialize($advanced_plans));

        $product->save();
    }


    /**
     *  Add plans checkbox list to the category creation form
     */
    public function mobbex_category_panels()
    {
        
        echo '<div id="mobbex_category_data" class="form-field">';
        echo '<h2><b>' . __('Choose the plans you want NOT to appear during the purchase', MOBBEX_WC_TEXT_DOMAIN) . ':</b></h2>';
        

        // Array with active plans
        $plans = array(
            'ahora_3'  => 'Ahora 3',
            'ahora_6'  => 'Ahora 6',
            'ahora_12' => 'Ahora 12',
            'ahora_18' => 'Ahora 18',
        );
        
        foreach ($plans as $key => $value) {
            $checkbox_data = array(
                'id'      => $key,
                'value'   => get_term_meta(get_the_ID(), $key, true),
                'label'   => $value,
            );
            woocommerce_wp_checkbox($checkbox_data);//Add the checkbox as array    
        }
        
        echo '</div>';
    }



    /**
     * Add Payment plans for a category in the edition page, and search if any of them was checked before
     */
    public function mobbex_category_panels_edit($term)
    {
            //getting term ID - category ID
            $term_id = $term->term_id;

            echo '<div id="mobbex_category_data" class="form-field">';
            echo '<h2><b>' . __('Choose the plans you want NOT to appear during the purchase', MOBBEX_WC_TEXT_DOMAIN) . ':</b></h2>';
            
            // Array with the active plans
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

            echo '</div>';
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