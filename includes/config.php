<?php 

namespace Mobbex\WP\Checkout\Includes;

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
        foreach (include('config-options.php') as $key => $option) {
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
        
        if (strpos($field_name, '_plans')){
            $method = "get_".$catalog_type."_meta";
            return unserialize($method($id, $field_name, true)) ?: [];
        }

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
    public function get_product_plans($products)
    {
        foreach ($products as $id) {
            foreach (['common_plans', 'advanced_plans'] as $value) {
                //Get product active plans
                ${$value} = array_merge($this->get_catalog_settings($id, $value), ${$value});
                //Get product category active plans
                foreach (wc_get_product_term_ids($product_id, 'product_cat') as $categoryId)
                    ${$value} = array_unique(array_merge(${$value}, $this->get_catalog_settings($categoryId, $value, 'term')));
            }
        }

        return compact('common_plans', 'advanced_plans');
    }

    /**
     * Method for compatibility with old plans saving method
     * @param string $product_id
     * @param array $plans
     * @return array
     */
    public function get_old_plans($product_id, $plans)
    {
        $inactive_plans = [];

        // Get inactive 'ahora' plans (previus save method)
        foreach (['ahora_3', 'ahora_6', 'ahora_12', 'ahora_18'] as $plan) {
            // Get from product
            if (get_post_meta($product_id, $plan, true) === 'yes') {
                $inactive_plans[] = $plan;
                continue;
            }

            // Get from product categories
            foreach (wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']) as $cat_id) {
                if (get_term_meta($cat_id, $plan, true) === 'yes') {
                    $inactive_plans[] = $plan;
                    break;
                }
            }
        }

        return array_merge($inactive_plans, $plans);
    }

}