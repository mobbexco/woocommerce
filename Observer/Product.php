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
        $manual         = $this->config->get_catalog_settings($id, "manual_config", $meta_type) ?: "no";
        $show_plans     = $this->config->get_catalog_settings($id, "show_featured", $meta_type) ?: "no";
        $featured_plans = $this->config->get_catalog_settings($id, "featured_plans", $meta_type) ?: null;
        $advanced_plans = $this->config->get_catalog_settings($id, "advanced_plans", $meta_type) ?: null;

        // gets store data
        extract($this->config->get_store_data($meta_type, $id));

        // multivendor
        $entity = $this->config->get_catalog_settings($id, 'mbbx_entity', $meta_type);

        // subscriptions
        $subscription_uid = $this->config->get_catalog_settings($id, 'mbbx_sub_uid', $meta_type);
        $subscription_fee = $this->config->get_catalog_settings($id, 'mbbx_sub_sign_up_fee', $meta_type);
        $is_subscription  = (bool) $this->config->get_catalog_settings($id, 'mbbx_sub_enable', $meta_type);

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
            'mbbx_sub_uid'          => !empty($_POST['mbbx_sub_uid']) ? $_POST['mbbx_sub_uid'] : false,
            'mbbx_sub_enable'       => !empty($_POST['mbbx_sub_enable']) && $_POST['mbbx_sub_enable'] === 'yes',
            'manual_config'         => !empty($_POST['mobbex_manual_config']) ? $_POST['mobbex_manual_config'] : 'no',
            'featured_plans'        => !empty($_POST['mobbex_featured_plans']) ? $_POST['mobbex_featured_plans'] : [],
            'mbbx_sub_sign_up_fee'  => !empty($_POST['mbbx_sub_sign_up_fee']) ? $_POST['mbbx_sub_sign_up_fee'] : false,
            'mbbx_enable_multisite' => !empty($_POST['mbbx_enable_multisite']) && $_POST['mbbx_enable_multisite'] === 'yes',
            'show_featured'         => !empty($_POST['mobbex_show_featured_plans']) ? $_POST['mobbex_show_featured_plans'] : 'no',
            'advanced_plans'        => !empty($_POST['mobbex_advanced_plans']) ? json_decode(stripslashes($_POST['mobbex_advanced_plans']), true) : [],
            'best_plan'             => null,
        ];

        // Get multisite options
        $store        = !empty($_POST['mbbx_store']) ? $_POST['mbbx_store'] : false;
        $api_key      = !empty($_POST['mbbx_api_key']) ? $_POST['mbbx_api_key'] : false;
        $name         = !empty($_POST['mbbx_store_name']) ? $_POST['mbbx_store_name'] : false;
        $access_token = !empty($_POST['mbbx_access_token']) ? $_POST['mbbx_access_token'] : false;

        // Save all $options data as meta data
        foreach ($options as $key => $option)
            update_metadata($meta_type, $id, $key, $option);

        // save best plan to show in produts catalog page
        if ($meta_type == "post")
            $this->save_best_plan($id);

        if ($options['mbbx_enable_multisite'])
            $this->save_store($meta_type, $id, $store, compact('name', 'api_key', 'access_token'));
    }

    /**
     * save_best_plan saves the required data to show the best plan banner in products catalog page
     * 
     * @param object $product
     */
    private function save_best_plan($id)
    {
        $featured_plans = $this->config->get_all_settings($id, "mobbex_manual_config")
            ? json_decode($this->config->get_all_settings($id, "mobbex_featured_plans"), true)
            : null;

        if (empty($featured_plans))
            return null;

        $best_plan = $this->get_best_plan($featured_plans, $id);
        update_metadata('post', $id, 'best_plan', $best_plan);
    }

    /**
     * get_best_plan get the best plan configured as featured plan for a product
     * 
     * @param array      $featured_plans
     * @param int|string $id
     * 
     * @return null|string best plan in featured plans
     */
    private function get_best_plan($featured_plans, $id) 
    {
        $sources = [];
        $total   = wc_get_product($id)->get_price();

        // Get product plans
        extract($this->config->get_products_plans([$id]));

        $installments = \Mobbex\Repository::getInstallments(
            [$id], 
            $common_plans,
            $advanced_plans
        );

        // Get sources from cache or Mobbex API
        try {
            $sources = \Mobbex\Repository::getSources(
                $total,
                $installments
            );
        }  catch (\Exception $e) {
            $this->logger->log(
                'error', 
                'Product > getSources', 
                $e->getMessage()
            );
            return null;
        }
        
        if (empty($sources))
            return null;

        return $this->helper->filter_featured_plans($sources, $featured_plans);
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
        $product_ids = [];

        // Try to get shortcode params
        if (isset($params['price'])) {
            $price        = $params['price'];
            $product_ids  = isset($params['products_ids']) ? explode(',', $params['products_ids']) : [];
        } else if (is_cart()) {
            $price        = WC()->cart->get_total(null);
            $product_ids  = array_column(WC()->cart->get_cart() ?: [], 'product_id');
        } else if ($post && $post->post_type == 'product') {
            $price       = wc_get_product($post->ID)->get_price();
            $product_ids = [$post->ID];
        } else {
            return;
        }

        $query = [
            'mbbx_products_price' => $price,
            'mbbx_products_ids'   => implode(',', $product_ids),
        ];

        $data = [
            'theme'                 => $this->config->theme,
            'featured_installments' => $this->handle_featured_installments($product_ids),
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

    /**
     * handle_best_plan handles show or not product best plan banner in shop view
     */
    public function handle_best_plan() 
    {
        $show_flag   = $this->config->show_flag_on_products == "yes";
        $show_banner = $this->config->show_banner_on_products == "yes";

        if (!$show_banner && !$show_flag) 
            return;

        global $product;
        if (!$product) return;

        $id = $product->get_id();

        $best_plan = json_decode($this->config->get_catalog_settings($id, "best_plan"), true);

        if (!$best_plan) return;

        // Pass PHP variables to JavaScript
        $show_flag_js   = $show_flag ? 'true' : 'false';
        $show_banner_js = $show_banner ? 'true' : 'false';

        echo "<script>window.showFlag = $show_flag_js; window.showBanner = $show_banner_js;</script>";
        
        echo '<div class="mobbex-finance-data"
            data-product-id="' . esc_attr($id) . '"
            data-plan-count="' . esc_attr($best_plan['count']) . '"
            data-plan-amount="' . esc_attr($best_plan['amount']) . '"
            data-plan-source="' . esc_attr($best_plan['source']) . '"
            data-plan-percentage="' . esc_attr($best_plan['percentage']) . '"
        ></div>';
    }


    /* Finance Widget */
    
    /**
     * Handle featured installments configuration and return the correct value depending on context.
     * In cart it is only possible to show auto-selected featured plans
     * 
     * @param array $product_ids
     * 
     * @return string|null
     */
    public function handle_featured_installments($product_ids = [])
    {
        if(!isset($product_ids) || empty($product_ids)){
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
            return $this->config->show_featured_installments_on_cart == "yes"
                ? "[]"
                : null;
        }
        
        return count($product_ids) > 1
            ? "[]"
            : $this->config->get_featured_plans_configuration($product_ids[0]);
    }
}
