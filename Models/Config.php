<?php 

namespace Mobbex\WP\Checkout\Models;

class Config 
{
    public function __construct()
    {
        //Get settings array
        $this->settings = $this->getSettings();
        //Set property for each setting
        $this->setProperties();
    }

    /**
     * Return an array with all Mobbex settings & his values
     * @return array $settings
     */
    public function getSettings()
    {
        //Get saved values from db
        $saved_values = get_option('woocommerce_' . MOBBEX_WC_GATEWAY_ID . '_settings', null);

        //Create settings array
        foreach (include(__DIR__.'/../utils/config-options.php') as $key => $option) {
            if (isset($option['default']))
                $settings[str_replace('-', '_', $key)] = isset($saved_values[$key]) ? $saved_values[$key] : $option['default'];
        }

        // The intent constant overwrite payment mode setting
        if (defined('MOBBEX_CHECKOUT_INTENT'))
            $settings['payment_mode'] = MOBBEX_CHECKOUT_INTENT;
        else if ($settings['payment_mode'] == 'yes')
            $settings['payment_mode'] = 'payment.2-step';
        else
            $settings['payment_mode'] = 'payment.v2';

        return $settings;
    }

    /**
     * Set a property in config class for each mobbex setting
     */
    public function setProperties()
    {
        foreach ($this->settings as $key => $value)
            $this->$key = $value;
    }

    /**
     * Returns formated Mobbex settings to be used in php sdk
     * @return array
     */
    public function formated_settings()
    {
        $formatedSettings = [];

        foreach ($this->settings as $key => $value) {

            switch ($value) {
                case 'yes':
                    $formatedSettings[$key] = true;
                    break;
                case 'no':
                    $formatedSettings[$key] = false;
                    break;
                default:
                    $formatedSettings[$key] = $value;
                    break;
            }

        }

        return $formatedSettings;
    }

    /** CATALOG SETTINGS **/

    /**
     * Retrieve the given product/category option.
     * 
     * @param int|string $id
     * @param string $field_name
     * @param string $catalog_type
     * 
     * @return array|string
     */
    public function get_catalog_settings($id, $field_name, $catalog_type = 'post')
    {
        if (strpos($field_name, '_plans'))
            return get_metadata($catalog_type, $id, $field_name, true) ?: [];

        return get_metadata($catalog_type, $id, $field_name, true) ?: '';
    }

    /**
     * Returns the entity asigned to a product
     * @param string $product_id
     * @return string 
     * 
     */
    public function get_product_entity($product_id)
    {
        if(!$this->multivendor)
            return '';

        if ($this->get_catalog_settings($product_id, 'mbbx_entity'))
            return $this->get_catalog_settings($product_id, 'mbbx_entity');

        foreach (wc_get_product_term_ids($product_id, 'product_cat') as $term_id) {
            if ($this->get_catalog_settings($term_id, 'mbbx_entity', 'term'))
                return $this->get_catalog_settings($term_id, 'mbbx_entity', 'term');
        }

        return apply_filters('filter_mbbx_entity', $product_id);
    }

    /**
     * Return the subscription UID from a product ID.
     * 
     * @param int|string $product_id
     * 
     * @return string|null
     */
    public function get_product_subscription($product_id)
    {
        if ($this->get_catalog_settings($product_id, 'mbbx_enable_sus'))
            return $this->get_catalog_settings($product_id, 'mbbx_sus_uid');
    }

    /**
     * Get active plans for a given products.
     * @param array $products
     * @return array $array
     */
    public function get_catalog_plans($products, $catalog_type = 'post', $admin = false)
    {
        $common_plans = $advanced_plans = $advanced_cat_plans = [];

        foreach ($products as $id) {

            $common_plans = array_merge($this->get_catalog_settings($id, 'common_plans', $catalog_type), $common_plans);

            if(empty($advanced_plans))
                $advanced_plans = array_merge($this->get_catalog_settings($id, 'advanced_plans', $catalog_type), $advanced_plans);
            else
                $advanced_plans = array_intersect($this->get_catalog_settings($id, 'advanced_plans', $catalog_type), $advanced_plans);

            //Get product category active plans
            if (!$admin && $catalog_type === 'post') {
                //Add categories common plans
                foreach (wc_get_product_term_ids($id, 'product_cat') as $categoryId)
                    $common_plans = array_unique(array_merge($common_plans, $this->get_catalog_settings($categoryId, 'common_plans', 'term')));
                
                //Add categories advanced plans
                foreach ($this->filter_categories($products) as $categoryId){
                    //Filter categories advanced plans
                    if(empty($advanced_cat_plans))
                        $advanced_cat_plans = array_merge($advanced_cat_plans, $this->get_catalog_settings($categoryId, 'adva$advanced_cat_plans', 'term'));
                    else    
                        $advanced_cat_plans = array_intersect($advanced_cat_plans, $this->get_catalog_settings($categoryId, 'advanced_plans', 'term'));
                    //Merge categories advanced plans with product advanced plans
                    $advanced_plans = array_unique(array_merge($advanced_plans, $advanced_cat_plans));
                }
            }
        }

        return compact('common_plans', 'advanced_plans');
    }

    /**
     * Returns the categories in common between a list of products.
     * @param array $products List of product ids.
     * @return array Filtered categories.
     */
    public function filter_categories($products)
    {
        $categories = $filtered_categories = [];

        //Get categories id's
        foreach ($products as $id) {
            foreach (wc_get_product_term_ids($id, 'product_cat') as $categoryId)
                $categories[$id][] = $categoryId;
        }

        //Filter categories
        foreach ($categories as $key => $value) {
            if(empty($filtered_categories))
                $filtered_categories = array_merge($filtered_categories, $value);
            else
                $filtered_categories =  array_intersect($filtered_categories, $value);
        }

        return $filtered_categories;
    }

    /**
     * Get current store data from product or product category.
     * 
     * @param string $meta_type 'post'|'term'.
     * @param int|string $id
     */
    public function get_store_data($meta_type, $id)
    {
        // Get store saved data
        $stores        = get_option('mbbx_stores') ?: [];
        $current_store = $this->get_catalog_settings($id, 'mbbx_store', $meta_type);

        return [
            'enable_ms'   => $this->get_catalog_settings($id, 'mbbx_enable_multisite', $meta_type),
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
     * Save a store or create a new one as appropriate.
     * 
     * @param string $meta_type 'post'|'term'.
     * @param int|string $id
     * @param string $store ID of the store. Use "new" to create a new one.
     * @param array $new_store_data
     */
    public function save_store($meta_type, $id, $store, $new_store_data)
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
}