<?php

/**
 * Products and Product Categories Admin Class.
 * 
 * Add a Mobbex settings tab to products and product categories admin panel.
 * Save product and category Mobbex settings.
 */
class Mbbx_Product_Admin
{
    /** @var MobbexHelper */
    public static $helper;

    /**
     * Init and set up hooks.
     */
    public static function init()
    {
        self::$helper = new MobbexHelper;

        // Add tab to product panel
        add_filter('woocommerce_product_data_tabs', [self::class, 'add_product_tab']);

        // Show options
        add_action('woocommerce_product_data_panels', [self::class, 'show']);
        add_action('product_cat_add_form_fields', [self::class, 'show']);
        add_action('product_cat_edit_form_fields', [self::class, 'show']);

        // Save options
        add_action('woocommerce_process_product_meta', [self::class, 'save']);
        add_action('create_product_cat', [self::class, 'save']);
        add_action('edited_product_cat', [self::class, 'save']);
    }

    /**
     * Display Mobbbex options on product or product category panel.
     * 
     * @param Term|null $term
     */
    public static function show($term = null)
    {
        $meta_type = $term ? 'term' : 'post';
        $id        = is_object($term) ? $term->term_id : get_the_ID();

        // Get plan fields and current store data to use in template
        extract(self::get_plan_fields($meta_type, $id));
        extract(self::get_store_data($meta_type, $id));

        //multivendor
        $entity = get_metadata($meta_type, $id, 'mbbx_entity', true) ?: '';
        
        //Suscriptions
        $is_suscription  = get_metadata($meta_type, $id, 'mbbx_enable_sus', true) ?: false;
        $suscription_uid = get_metadata($meta_type, $id, 'mbbx_sus_uid', true) ?: false;

        // Render template
        $template = $meta_type == 'post' ? 'product-settings.php' : 'category-settings.php';
        include_once plugin_dir_path(__FILE__) . "../../templates/$template";
    }

    /**
     * Create plan fields data from product or product category.
     * 
     * @param string $meta_type 'post'|'term'.
     * @param int|string $id
     */
    public static function get_plan_fields($meta_type, $id)
    {
        $common_fields = $advanced_fields = $source_names = [];

        // Get current checked plans
        $checked_common_plans   = get_metadata($meta_type, $id, 'common_plans', true) ?: [];
        $checked_advanced_plans = get_metadata($meta_type, $id, 'advanced_plans', true) ?: [];

        // Support previus save method
        $checked_common_plans   = is_string($checked_common_plans)   ? unserialize($checked_common_plans)   : $checked_common_plans;
        $checked_advanced_plans = is_string($checked_advanced_plans) ? unserialize($checked_advanced_plans) : $checked_advanced_plans;

        // Create common plan fields
        foreach (self::$helper->get_sources() as $source) {
            // Only if have installments
            if (empty($source['installments']['list']))
                continue;

            foreach ($source['installments']['list'] as $plan) {
                // Get value from common_plans post meta and check if it's saved using previus method
                $is_checked = (!in_array($plan['reference'], $checked_common_plans) && get_metadata($meta_type, $id, $plan['reference'], true) !== 'yes');

                // Create field array data
                $common_fields[$plan['reference']] = [
                    'id'    => 'common_plan_' . $plan['reference'],
                    'value' => $is_checked ? 'yes' : false,
                    'label' => $plan['description'] ?: $plan['name'],
                ];
            }
        }

        // Create advanced plan fields
        foreach (self::$helper->get_sources_advanced() as $source) {
            // Only if have installments
            if (empty($source['installments']))
                continue;

            // Save source name
            $source_names[$source['source']['reference']] = $source['source']['name'];

            // Create field array data
            foreach ($source['installments'] as $plan) {
                $advanced_fields[$source['source']['reference']][] = [
                    'id'      => 'advanced_plan_' . $plan['uid'],
                    'value'   => in_array($plan['uid'], $checked_advanced_plans) ? 'yes' : false,
                    'label'   => $plan['description'] ?: $plan['name'],
                ];
            }
        }

        return compact('common_fields', 'advanced_fields', 'source_names');
    }

    /**
     * Get current store data from product or product category.
     * 
     * @param string $meta_type 'post'|'term'.
     * @param int|string $id
     */
    public static function get_store_data($meta_type, $id)
    {
        // Get store saved data
        $stores        = get_option('mbbx_stores') ?: [];
        $current_store = get_metadata($meta_type, $id, 'mbbx_store', true) ?: '';

        return [
            'enable_ms'   => get_metadata($meta_type, $id, 'mbbx_enable_multisite', true),
            'store_names' => array_combine(array_keys($stores), array_column($stores, 'name')) ?: [],
            'store'       => [
                'id'           => $current_store,
                'name'         => isset($stores[$current_store]['name']) ? $stores[$current_store]['name'] : '',
                'api_key'      => isset($stores[$current_store]['api_key']) ? $stores[$current_store]['api_key'] : '',
                'access_token' => isset($stores[$current_store]['access_token']) ? $stores[$current_store]['access_token'] : '',
            ],
        ];
    }

    /**
     * Save Mobbbex options from product and product category.
     * 
     * @param int $id The post|term ID.
     */
    public static function save($id)
    {
        // Get meta type from current action
        $meta_type = current_action() == 'woocommerce_process_product_meta' ? 'post' : 'term';

        self::remove_previus_ahora_values($meta_type, $id);

        // Get plans selected
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'common_plan_') !== false && $value === 'no') {
                // Add UID to common plans
                $common_plans[] = explode('common_plan_', $key)[1];
            } else if (strpos($key, 'advanced_plan_') !== false && $value === 'yes') {
                // Add UID to advanced plans
                $advanced_plans[] = explode('advanced_plan_', $key)[1];
            }
        }

        // Get multisite options
        $enable_ms    = !empty($_POST['mbbx_enable_multisite']) && $_POST['mbbx_enable_multisite'] === 'yes';
        $store        = !empty($_POST['mbbx_store']) ? $_POST['mbbx_store'] : false;
        $name         = !empty($_POST['mbbx_store_name']) ? $_POST['mbbx_store_name'] : false;
        $api_key      = !empty($_POST['mbbx_api_key']) ? $_POST['mbbx_api_key'] : false;
        $access_token = !empty($_POST['mbbx_access_token']) ? $_POST['mbbx_access_token'] : false;

        // Get Entity 
        $entity = !empty($_POST['mbbx_entity']) ? $_POST['mbbx_entity'] : false;

        //Suscription options
        $is_suscription  = !empty($_POST['mbbx_enable_sus']) && $_POST['mbbx_enable_sus'] === 'yes';
        $suscription_uid = !empty($_POST['mbbx_sus_uid']) ? $_POST['mbbx_sus_uid'] : false;

        // Save all data as meta data
        update_metadata($meta_type, $id, 'common_plans', $common_plans);
        update_metadata($meta_type, $id, 'advanced_plans', $advanced_plans);
        update_metadata($meta_type, $id, 'mbbx_enable_multisite', $enable_ms);
        update_metadata($meta_type, $id, 'mbbx_entity', $entity);
        update_metadata($meta_type, $id, 'mbbx_enable_sus', $is_suscription);
        
        if ($enable_ms)
        self::save_store($meta_type, $id, $store, compact('name', 'api_key', 'access_token'));
        
        if($is_suscription)
        update_metadata($meta_type, $id, 'mbbx_sus_uid', $suscription_uid);

    }

    /**
     * Remove ahora plans values saved with previus method.
     * 
     * @param string $meta_type 'post'|'term'
     * @param int|string $id
     */
    public static function remove_previus_ahora_values($meta_type, $id)
    {
        foreach (self::$helper::$ahora as $plan) {
            if (get_metadata($meta_type, $id, $plan, true))
                delete_metadata($meta_type, $id, $plan);
        }
    }

    /**
     * Save a store or create a new one as appropriate.
     * 
     * @param string $meta_type 'post'|'term'.
     * @param int|string $id
     * @param string $store ID of the store. Use "new" to create a new one.
     * @param array $new_store_data
     */
    public static function save_store($meta_type, $id, $store, $new_store_data)
    {
        // Get current existing stores
        $stores = get_option('mbbx_stores') ?: [];

        // If store already exists, save selection
        if (array_key_exists($store, $stores)) {
            update_metadata($meta_type, $id, 'mbbx_store', $store);
        } else if ($store === 'new' && $new_store_data['name'] && $new_store_data['api_key'] && $new_store_data['access_token']) {
            // Create new store
            $new_store          = md5($new_store_data['api_key'] . '|' . $new_store_data['access_token']);
            $stores[$new_store] = $new_store_data;

            // Save selection and new store data
            update_option('mbbx_stores', $stores) && update_metadata($meta_type, $id, 'mbbx_store', $new_store);
        }
    }


    /**
     * Add Mobbex tab to product settings.
     * 
     * @param array $tabs
     * 
     * @return array
     */
    public static function add_product_tab($tabs)
    {
        $tabs['mobbex'] = [
            'label'    => 'Mobbex',
            'target'   => 'mobbex_product_data',
            'priority' => 21,
        ];

        return $tabs;
    }
}
