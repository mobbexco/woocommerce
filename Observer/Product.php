<?php

namespace Mobbex\WP\Checkout\Observer;

class Product 
{
    /** @var \Mobbex\WP\Checkout\Model\Config */
    public $config;

    /** @var \Mobbex\WP\Checkout\Model\Helper */
    public $helper;
    
    public function __construct()
    {
        $this->config = new \Mobbex\WP\Checkout\Model\Config();
        $this->helper = new \Mobbex\WP\Checkout\Model\Helper();

        // Create financial widget shortcode
        add_shortcode('mobbex_button', [$this, 'shortcode_mobbex_button']);
    }

    /** ADMIN SECTION **/

    /**
     * Display Mobbbex options on product or product category panel.
     * 
     * @param Term|null $term
     */
    public function show($term = null)
    {
        $meta_type = $term ? 'term' : 'post';
        $id        = is_object($term) ? $term->term_id : get_the_ID();

        // Get plan fields and current store data to use in template
        extract($this->config->get_catalog_plans($id, $meta_type, true));
        extract($this->format_fields(\Mobbex\Repository::getPlansFilterFields($id, $common_plans, $advanced_plans)));
        extract($this->config->get_store_data($meta_type, $id));

        //multivendor
        $entity = $this->config->get_catalog_settings($id, 'mbbx_entity', $meta_type);

        //subscriptions
        $is_subscription  = (bool) $this->config->get_catalog_settings($id, 'mbbx_sub_enable', $meta_type);
        $subscription_uid = $this->config->get_catalog_settings($id, 'mbbx_sub_uid', $meta_type);
        $subscription_fee = $this->get_product_subscription_signup_fee($id);

        // Render template
        $template = $meta_type == 'post' ? 'product-settings.php' : 'category-settings.php';
        include_once plugin_dir_path(__FILE__) . "../templates/$template";
    }

    /**
     * Adapt plans fields array for woocommerce usage
     * @param array $params
     * @return array  
     */
    public function format_fields($params)
    {
        extract($params);

        foreach ($commonFields as $key => $field)
            $commonFields[$key]['value'] = $field['value'] ? 'yes' : 'no';

        foreach ($advancedFields as $first_key => $fields) {
            foreach ($fields as $second_key => $field)
                $advancedFields[$first_key][$second_key]['value'] = $field['value'] ? 'yes' : 'no';
        }

        return compact('commonFields', 'advancedFields', 'sourceNames', 'sourceGroups');
    }

    /**
     * Save Mobbbex options from product and product category.
     * 
     * @param int $id The post|term ID.
     */
    public function save($id)
    {
        // Get meta type from current action
        $meta_type = current_action() == 'woocommerce_process_product_meta' ? 'post' : 'term';

        $options = [
            'mbbx_entity'           => !empty($_POST['mbbx_entity']) ? $_POST['mbbx_entity'] : false,
            'mbbx_sub_enable'       => !empty($_POST['mbbx_sub_enable']) && $_POST['mbbx_sub_enable'] === 'yes',
            'mbbx_sub_uid'          => !empty($_POST['mbbx_sub_uid']) ? $_POST['mbbx_sub_uid'] : false,
            'mbbx_sub_sign_up_fee'  => !empty($_POST['mbbx_sub_sign_up_fee']) ? $_POST['mbbx_sub_sign_up_fee'] : false,
            'mbbx_enable_multisite' => !empty($_POST['mbbx_enable_multisite']) && $_POST['mbbx_enable_multisite'] === 'yes',
            'common_plans'          => [],
            'advanced_plans'        => [],
        ];

        // Get multisite options
        $store        = !empty($_POST['mbbx_store']) ? $_POST['mbbx_store'] : false;
        $name         = !empty($_POST['mbbx_store_name']) ? $_POST['mbbx_store_name'] : false;
        $api_key      = !empty($_POST['mbbx_api_key']) ? $_POST['mbbx_api_key'] : false;
        $access_token = !empty($_POST['mbbx_access_token']) ? $_POST['mbbx_access_token'] : false;

        // Get plans selected
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'common_plan_') !== false && $value === 'no'
            ) {
                // Add UID to common plans
                $options['common_plans'][] = explode('common_plan_', $key)[1];
            } else if (strpos($key, 'advanced_plan_') !== false && $value === 'yes') {
                // Add UID to advanced plans
                $options['advanced_plans'][] = explode('advanced_plan_', $key)[1];
            }
        }

        // Save all $options data as meta data
        foreach ($options as $key => $option)
            update_metadata($meta_type, $id, $key, strpos($key, "_plans") ? json_encode($option) : $option);

        if ($options['mbbx_enable_multisite'])
            $this->save_store($meta_type, $id, $store, compact('name', 'api_key', 'access_token'));
    }

    /**
     * Add Mobbex tab to product settings.
     * 
     * @param array $tabs
     * 
     * @return array
     */
    public function add_product_tab($tabs)
    {
        $tabs['mobbex'] = [
            'label'    => 'Mobbex',
            'target'   => 'mobbex_product_data',
            'priority' => 21,
        ];

        return $tabs;
    }

    /** FRONT **/

    /**
     * Display finance widget open button in product page.
     */
    public function display_finnacial_button()
    {
        do_shortcode('[mobbex_button]');
    }

    /**
     * Creates a updated financial widget with selected variant price & returns it in a string
     * 
     * @return string $widget
     */
    public function financial_widget_update()
    {
        if (empty($_POST['price']) || empty($_POST['id']))
            exit;

        //Get parent product id to get plans
        $product_id = isset($_POST['child']) && $_POST['child'] ? wc_get_product($_POST['id'])->get_parent_id() : $_POST['id'];

        ob_start() && do_shortcode('[mobbex_button ' . http_build_query([
            'price'        => $_POST['price'],
            'products_ids' => implode(',', [$product_id]),
            'show_button'  => false,
        ], '', ' ') . ']');

        return ob_get_clean();
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

        $dir_url = str_replace('/Observer', '', plugin_dir_url(__FILE__));

        // Try to enqueue scripts
        wp_enqueue_script('mbbx-product-button-js', $dir_url . "assets/js/finance-widget.js", null, MOBBEX_VERSION);
        wp_enqueue_style('mobbex_product_style', $dir_url . 'assets/css/product.css', null, MOBBEX_VERSION);

        //Get product plans
        extract($this->config->get_products_plans($products_ids));

        $data = [
            'price'   => $price,
            'sources' => \Mobbex\Repository::getSources($price, \Mobbex\Repository::getInstallments($products_ids, $common_plans, $advanced_plans)),
            'style'   => [
                'show_button'   => isset($params['show_button']) ? $params['show_button'] : true,
                'theme'         => $this->config->theme,
                'custom_styles' => $this->config->financial_widget_styles,
                'text'          => $this->config->financial_widget_button_text,
                'logo'          => $this->config->financial_widget_button_logo
            ]
        ];

        include_once __DIR__ . '/../templates/finance-widget.php';
    }

    /**
     * Check if product is a subscription
     * 
     * @param object $product
     * 
     * @return bool
     */
    public function is_subscription($product_id)
    {
        return (bool) $this->config->get_catalog_settings($product_id, 'mbbx_sub_enable');
    }

    /*
     * Get product subscription sign-up fee from API
     * 
     * @param int|string $id
     * 
     * @return int|string product subscription sign-up fee
     */
    public function get_product_subscription_signup_fee($id)
    {
        $subscription = $this->config->get_product_subscription($id);
        return $subscription ? $subscription['setupFee'] : '';
    }

    /**
     * Add mobbex subscription fee to cart and checkout if it exists in the product
     * 
     * @param WC_Cart $cart
     */
    public function maybe_add_mobbex_subscription_fee($cart)
    {
        foreach ( $cart->get_cart() as $item )
            $product_id = $item['product_id'];
        
        $subscription = $this->config->get_product_subscription($product_id);

        $subscription ? $cart->add_fee( __('Sign-up Fee', 'woocommerce') , $subscription['setupFee'], false ) : '';
    }

    /**
     * Display sign up fee on product price
     * 
     * @param string $price_html
     * @param WC_Product $product
     * 
     * @return string $sign_up_fee
     */
    public function display_sign_up_fee_on_price($price_html, $product)
    {
        // Sometimes the hook gets an array type product 
        if (!is_object($product))
            return;

        // Avoid non subscription products
        if(!$this->is_subscription($product->get_id()))
            return $price_html;

        // Set sign up price
        $sign_up_price = $this->get_product_subscription_signup_fee($product->get_id());

        return $sign_up_price ? $price_html .= __(" /month and a $$sign_up_price sign-up fee") : $price_html;
    }
}