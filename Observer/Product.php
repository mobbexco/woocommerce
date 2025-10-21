<?php

namespace Mobbex\WP\Checkout\Observer;

class Product
{
    /** @var \Mobbex\WP\Checkout\Model\Config */
    public $config;

    /** @var \Mobbex\WP\Checkout\Model\Helper */
    public $helper;

    /** @var \Mobbex\WP\Checkout\Model\Logger */
    public $logger;

    public function __construct()
    {
        $this->config = new \Mobbex\WP\Checkout\Model\Config();
        $this->helper = new \Mobbex\WP\Checkout\Model\Helper();
        $this->logger = new \Mobbex\WP\Checkout\Model\Logger;

        // Create finance widget shortcode
        add_shortcode('mobbex_finance_widget', [$this, 'shortcode_mobbex_finance_widget']);
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

        // gets plans
        $filtered_plans = \Mobbex\Repository::getPlansFilterFields($id);
        extract($this->config->get_catalog_plans($id, $meta_type, true));

        // gets plans configurator settings
        $selected_plans = $this->config->get_catalog_settings($id, "selected_plans", $meta_type) ?: "[]";
        $manual         = $this->config->get_catalog_settings($id, "mobbex_manual_config", $meta_type) ?: "no";
        $featured_plans = $this->config->get_catalog_settings($id, "mobbex_featured_plans", $meta_type) ?: "[]";
        $show_plans     = $this->config->get_catalog_settings($id, "mobbex_show_featured_plans", $meta_type) ?: "no";
        $common_plans   = $this->config->get_catalog_settings($id, "common_plans", $meta_type) ?: "[]";
        $advanced_plans = $this->config->get_catalog_settings($id, "advanced_plans", $meta_type) ?: "[]";

        // gets store data
        extract($this->config->get_store_data($meta_type, $id));

        //multivendor
        $entity = $this->config->get_catalog_settings($id, 'mbbx_entity', $meta_type);

        //subscriptions
        $is_subscription  = (bool) $this->config->get_catalog_settings($id, 'mbbx_sub_enable', $meta_type);
        $subscription_uid = $this->config->get_catalog_settings($id, 'mbbx_sub_uid', $meta_type);
        $subscription_fee = $this->config->get_catalog_settings($id, 'mbbx_sub_sign_up_fee', $meta_type);

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
            'common_plans'               => [],
            'advanced_plans'             => [],
            'selected_plans'             => [],
            'mobbex_featured_plans'      => '[]',
            'mobbex_show_featured_plans' => 'no',
            'mobbex_manual_config'       => 'no',
            'mbbx_entity'                => !empty($_POST['mbbx_entity']) ? $_POST['mbbx_entity'] : false,
            'mbbx_sub_uid'               => !empty($_POST['mbbx_sub_uid']) ? $_POST['mbbx_sub_uid'] : false,
            'mbbx_sub_enable'            => !empty($_POST['mbbx_sub_enable']) && $_POST['mbbx_sub_enable'] === 'yes',
            'mbbx_sub_sign_up_fee'       => !empty($_POST['mbbx_sub_sign_up_fee']) ? $_POST['mbbx_sub_sign_up_fee'] : false,
            'mbbx_enable_multisite'      => !empty($_POST['mbbx_enable_multisite']) && $_POST['mbbx_enable_multisite'] === 'yes',
        ];

        // Get multisite options
        $store        = !empty($_POST['mbbx_store']) ? $_POST['mbbx_store'] : false;
        $api_key      = !empty($_POST['mbbx_api_key']) ? $_POST['mbbx_api_key'] : false;
        $name         = !empty($_POST['mbbx_store_name']) ? $_POST['mbbx_store_name'] : false;
        $access_token = !empty($_POST['mbbx_access_token']) ? $_POST['mbbx_access_token'] : false;

        // Get activated plans
        $common_plans   = !empty($_POST['common_plans'])
            ? json_decode(stripslashes($_POST['common_plans']), true) 
            : [];
        $advanced_plans = !empty($_POST['advanced_plans'])
            ? json_decode(stripslashes($_POST['advanced_plans']), true) 
            : [];

        // Get plans selected and configuration
        $options['selected_plans'] = !empty($_POST['selected_plans']) 
            ? $_POST['selected_plans']
            : [];
        $options['mobbex_manual_config'] = !empty($_POST['mobbex_manual_config'])
            ? $_POST['mobbex_manual_config']
            : 'no';
        $options['mobbex_featured_plans'] = !empty($_POST['mobbex_featured_plans']) 
            ? $_POST['mobbex_featured_plans']
            : [];
        $options['mobbex_show_featured_plans'] = !empty($_POST['mobbex_show_featured_plans']) 
            ? $_POST['mobbex_show_featured_plans'] 
            : 'no';

        // Add UID to common and advanced plans
        foreach ($common_plans as $common_plan => $value)
            $options['common_plans'][] = $value;

        foreach ($advanced_plans as $advanced_plan => $value)
            $options['advanced_plans'][] = $value;

        // Save all $options data as meta data
        foreach ($options as $key => $option)
            update_metadata($meta_type, $id, $key, $option);

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
    public function display_finance_widget()
    {
        do_shortcode('[mobbex_finance_widget]');
    }

    /**
     * Creates a updated finance widget with selected variant price & returns it in a string
     * 
     * @return string $widget
     */
    public function finance_widget_update()
    {
        if (empty($_POST['price']) || empty($_POST['id']))
            exit;

        //Get parent product id to get plans
        $product_id = isset($_POST['child']) && $_POST['child'] ? wc_get_product($_POST['id'])->get_parent_id() : $_POST['id'];

        ob_start() && do_shortcode('[mobbex_finance_widget ' . http_build_query([
            'price'        => $_POST['price'],
            'products_ids' => implode(',', [$product_id]),
            'show_button'  => false,
        ], '', ' ') . ']');

        return ob_get_clean();
    }

    /**
     * Show a modal with financial information
     * only if the checkbox of financial information is checked
     * Shortcode function, return finance widget component (finance-widget.min.js)
     * in woocommerce echo do_shortcode('[mobbex_finance_widget]'); in content-single-product.php
     * or [mobbex_finance_widget] in wordpress pages
     */
    public function shortcode_mobbex_finance_widget($params)
    {
        global $post;
        $products_ids = [];

        // Try to get shortcode params
        if (isset($params['price'])) {
            $price        = $params['price'];
            $products_ids = isset($params['products_ids']) ? explode(',', $params['products_ids']) : [];
        } else if (is_cart()) {
            $price          = WC()->cart->get_total(null);
            $products_ids   = array_column(WC()->cart->get_cart() ?: [], 'product_id');
        } else if ($post && $post->post_type == 'product') {
            $price        = wc_get_product($post->ID)->get_price();
            $products_ids = [$post->ID];
        } else {
            return;
        }

        $query = [
            'mbbx_products_price' => $price,
            'mbbx_products_ids'   => implode(',', $products_ids),
        ];

        $featured_plans = $this->handle_featured_installments($products_ids);

        $data = [
            'featured_installments' => $featured_plans,
            'theme'                 => $this->config->theme,
            'sources_url'           => add_query_arg(
                $query,
                get_rest_url(null, 'mobbex/v1/sources')
                )
            ];
            
        $dir_url = str_replace('/Observer', '', plugin_dir_url(__FILE__));

        // Try to enqueue styles and scripts
        wp_enqueue_style(
            'mobbex_product_style',
            $dir_url . 'assets/css/product.css',
            null, MOBBEX_VERSION
        );
        wp_enqueue_script(
            'mbbx-finance-widget', $dir_url . "assets/js/finance-widget.min.js",
            null,
            MOBBEX_VERSION,
            ['in_footer' => true]
        );

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

    /**
     * Add mobbex subscription fee to cart and checkout if it exists in the product
     * 
     * @param WC_Cart $cart
     */
    public function maybe_add_mobbex_subscription_fee($cart)
    {
        if ($cart->is_empty())
            return;

        foreach ($cart->get_cart() as $item) {
            $sign_up_price = $this->config->get_product_subscription_signup_fee($item['product_id']);
            $sign_up_price > 0 
                ? $cart->add_fee("Costo de instalación", $sign_up_price * $item['quantity'] , false) 
                : '';
        }
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
        // Sometimes the hook gets an array type product and avoid non subscription products
        if (!is_object($product) || !$this->is_subscription($product->get_id()))
            return $price_html;

        // Set sign up price
        $sign_up_price = $this->config->get_product_subscription_signup_fee($product->get_id());

        return $sign_up_price ? $price_html .= __(" /mes y $$sign_up_price de costo de instalación") : $price_html;
    }


    /* Finance Widget */
    
    /**
     * Handle featured installments configuration and return the correct value depending on context.
     * In cart it is only possible to show auto-selected featured plans
     * 
     * @return string|null
     */
    public function handle_featured_installments($products_ids = [])
    {
        if(!isset($products_ids) || empty($products_ids)){
            $this->logger->log(
                "error", 
                "Error: No se encontraron productos para obtener financiación destacada.",
                "mobbex-for-woocommerce"
            );
            return null;
        }

        if (is_cart()){
            $this->logger->log(
                "debug", 
                "show_featured_installments_on_cart config:",
                $this->config->show_featured_installments_on_cart
            );
            return $this->config->show_featured_installments_on_cart
                ? "[]"
                : null;
        }
        
        if(count($products_ids) == 1)
            return $this->get_featured_plans_configuration($products_ids[0]);
        else
            return "[]";
    }

    /**
     * Get product featured plans configuration
     * 
     * @param string|int $product_id 
     * 
     * @return string|null
     */
    public function get_featured_plans_configuration($product_id)
    {
        $product        = wc_get_product($product_id);
        $manual_config  = $product->get_meta("mobbex_manual_config") ?: "no";
        $featured_plans = $product->get_meta("mobbex_featured_plans") ?: "[]";
        $show_featured  = $product->get_meta("mobbex_show_featured_plans") ?: "no";

        $this->logger->log(
            "debug",
            "get_featured_plans > product_id: $product_id - featured plans configuration",
            [
                "manual_select"           => $manual_config,
                "show_featured_plans"     => $show_featured,
                "selected_featured_plans" => $featured_plans,
            ]
        );

        if ($show_featured == "no")
            return null;

        if ($manual_config == "yes")
            return $featured_plans;
        else
            return "[]";
    }
}
