<?php
require_once 'utils.php';

class MobbexHelper
{
    /**
     * All 'ahora' plans.
     */
    public static $ahora = ['ahora_3', 'ahora_6', 'ahora_12', 'ahora_18'];

    public function __construct()
    {
        // Init settings (Full List in WC_Gateway_Mobbex::init_form_fields)
        $option_key = 'woocommerce_' . MOBBEX_WC_GATEWAY_ID . '_settings';
        $settings = get_option($option_key, null) ?: [];
        foreach ($settings as $key => $value) {
            $key = str_replace('-', '_', $key);
            $this->$key = $value;
        }
    }

    public function isReady()
    {
        return ($this->enabled === 'yes' && !empty($this->api_key) && !empty($this->access_token));
    }

    /**
     * Get sources with common plans and advanced plans from mobbex.
     * @param integer|null $total
     * @param array|null $inactivePlans
     * @param array|null $activePlans
     */
    public function get_sources($total = null, $inactivePlans = null, $activePlans = null)
    {
        // If plugin is not ready
        if (!$this->isReady()) {
            return [];
        }

        $data = $total ? '?total=' . $total : null;

        //Get installments
        $data .= '&' . self::get_installments_query($inactivePlans, $activePlans);

        $response = wp_remote_get(MOBBEX_SOURCES . $data, [

            'headers' => [
                'cache-control' => 'no-cache',
                'content-type' => 'application/json',
                'x-api-key' => $this->api_key,
                'x-access-token' => $this->access_token,
            ],

        ]);

        if (!is_wp_error($response)) {
            $response = json_decode($response['body'], true);
            $data = $response['data'];
            if ($data) {
                return $data;
            }
        }

        return [];
    }

    /**
     * Get sources with advanced rule plans from mobbex.
     * @param string $rule
     */
    public function get_sources_advanced($rule = 'externalMatch')
    {
        if (!$this->isReady())
            return [];

        $response = wp_remote_get(str_replace('{rule}', $rule, MOBBEX_ADVANCED_PLANS), [
            'headers' => [
                'cache-control' => 'no-cache',
                'content-type' => 'application/json',
                'x-api-key' => $this->api_key,
                'x-access-token' => $this->access_token,
            ],
        ]);

        if (!is_wp_error($response)) {
            $response = json_decode($response['body'], true);
            $data = $response['data'];
            if ($data) {
                return $data;
            }
        }

        return [];
    }

    /**
     * Returns a query param with the installments of the product.
     * @param array $inactivePlans
     * @param array $activePlans
     */
    public static function get_installments_query($inactivePlans = null, $activePlans = null)
    {

        $installments = [];

        //get plans
        if ($inactivePlans) {
            foreach ($inactivePlans as $plan) {
                $installments[] = "-$plan";
            }
        }

        if ($activePlans) {
            foreach ($activePlans as $plan) {
                $installments[] = "+uid:$plan";
            }
        }

        //Build query param
        $query = http_build_query(['installments' => $installments]);
        $query = preg_replace('/%5B[0-9]+%5D/simU', '%5B%5D', $query);

        return $query;
    }

    /**
     * Retrive inactive common plans from a product and its categories.
     * 
     * @param int $product_id
     * 
     * @return array
     */
    public static function get_inactive_plans($product_id)
    {
        $categories     = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
        $inactive_plans = [];

        // Get inactive 'ahora' plans (previus save method)
        foreach (self::$ahora as $plan) {
            // Get from product
            if (get_post_meta($product_id, $plan, true) === 'yes') {
                $inactive_plans[] = $plan;
                continue;
            }

            // Get from product categories
            foreach ($categories as $cat_id) {
                if (get_term_meta($cat_id, $plan, true) === 'yes') {
                    $inactive_plans[] = $plan;
                    break;
                }
            }
        }

        // Get plans from product and product categories
        $inactive_plans = array_merge($inactive_plans, self::unserialize_array(get_post_meta($product_id, 'common_plans', true)));

        foreach ($categories as $cat_id)
            $inactive_plans = array_merge($inactive_plans, self::unserialize_array(get_term_meta($cat_id, 'common_plans', true)));

        // Remove duplicated and return
        return array_unique($inactive_plans);
    }

    /**
     * Retrive active advanced plans from a product and its categories.
     * 
     * @param int $product_id
     * 
     * @return array
     */
    public static function get_active_plans($product_id)
    {
        $categories     = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
        $active_plans = [];

        // Get plans from product and product categories
        $active_plans = array_merge($active_plans, self::unserialize_array(get_post_meta($product_id, 'advanced_plans', true)));

        foreach ($categories as $cat_id)
            $active_plans = array_merge($active_plans, self::unserialize_array(get_term_meta($cat_id, 'advanced_plans', true)));

        // Remove duplicated and return
        return array_unique($active_plans);
    }

    /**
     * Get all product IDs from Order.
     * 
     * @param WP_Order $order
     * @return array $products
     */
    public static function get_product_ids($order)
    {
        $products = [];

        foreach ($order->get_items() as $item)
            $products[] = $item->get_product_id();

        return $products;
    }

    /**
     * Get all category IDs from Order.
     * Duplicates are removed.
     * 
     * @param WP_Order $order
     * @return array $categories
     */
    public static function get_category_ids($order)
    {
        $categories = [];

        // Get Products Ids
        $products = self::get_product_ids($order);

        foreach ($products as $product)
            $categories = array_merge($categories, wp_get_post_terms($product, 'product_cat', ['fields' => 'ids']));

        // Remove duplicated IDs and return
        return array_unique($categories);
    }

    /**
     * Get payment mode.
     * 
     * @return string|null $payment_mode
     */
    public function get_payment_mode()
    {
        if (defined('MOBBEX_CHECKOUT_INTENT') && !empty(MOBBEX_CHECKOUT_INTENT)) {
            // Try to get from constant first
            return MOBBEX_CHECKOUT_INTENT;
        } else if (!empty($this->payment_mode) && $this->payment_mode === 'yes') {
            return 'payment.2-step';
        }
    }

    /**
     * Get Store configured by product/category using Multisite options.
     * 
     * @param WP_Order $order
     * 
     * @return array|null $store
     */
    public static function get_store($order)
    {
        $stores   = get_option('mbbx_stores') ?: [];
        $products = self::get_product_ids($order);

        // Search store configured
        foreach ($products as $product_id) {
            $store_configured = self::get_store_from_product($product_id);

            if (!empty($store_configured) && !empty($stores[$store_configured]))
                return $stores[$store_configured];
        }
    }

    /**
     * Get Store ID from product and its categories.
     * 
     * @param int|string $product_id
     * 
     * @return string|null $store_id
     */
    public static function get_store_from_product($product_id)
    {
        $stores     = get_option('mbbx_stores') ?: [];
        $store      = get_post_meta($product_id, 'mbbx_store', true);
        $ms_enabled = get_post_meta($product_id, 'mbbx_enable_multisite', true);

        if ($ms_enabled && !empty($store) && !empty($stores[$store]))
            return $store;

        // Get stores from product categories
        $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);

        foreach ($categories as $cat_id) {
            $store      = get_term_meta($cat_id, 'mbbx_store', true);
            $ms_enabled = get_term_meta($cat_id, 'mbbx_enable_multisite', true);

            if ($ms_enabled && !empty($store) && !empty($stores[$store]))
                return $store;
        }
    }

    /**
     * Capture 'authorized' payment using Mobbex API.
     * 
     * @param string|int $payment_id
     * @param string|int $total
     * 
     * @return bool $result
     */
    public function capture_payment($payment_id, $total)
    {
        if (!$this->isReady())
            throw new Exception(__('Plugin is not ready', 'mobbex-for-woocommerce'));

        if (empty($payment_id) || empty($total))
            throw new Exception(__('Empty Payment UID or params', 'mobbex-for-woocommerce'));

        // Capture payment
        $response = wp_remote_post(str_replace('{id}', $payment_id, MOBBEX_CAPTURE_PAYMENT), [
            'headers' => [
                'cache-control'  => 'no-cache',
                'content-type'   => 'application/json',
                'x-api-key'      => $this->api_key,
                'x-access-token' => $this->access_token,
            ],

            'body'        => json_encode(compact('total')),
            'data_format' => 'body',
        ]);

        if (!is_wp_error($response)) {
            $response = json_decode($response['body'], true);

            if (!empty($response['result']))
                return true;
        }

        throw new Exception(__('An error occurred in the execution', 'mobbex-for-woocommerce'));
    }

    /**
     * Try unserialize a string forcing an array as return.
     * 
     * @param mixed $var
     * 
     * @return array
     */
    public static function unserialize_array($var)
    {
        if (is_string($var))
            $var = unserialize($var);

        return is_array($var) ? $var : [];
    }

    /* MULTIVENDOR METHODS */

    /**
     * Return an array with al the merchants from the items list.
     * 
     * @param array $items
     * @return array
     * 
     */
    public static function get_merchants($items)
    {

        $merchants = [];

        //Get the merchants from items list
        foreach ($items as $item) {
            if (!empty($item['entity'])) 
                $merchants[] = ['uid' => $item['entity']];
        }

        return $merchants;
    }

    /**
     * Return the UID of the entity of a product.
     * 
     * @param array $item
     * @param array $product_id
     * @return string
     * 
     */
    public static function get_entity($item, $product_id)
    {
        if (get_metadata('post', $product_id, 'mbbx_entity', true)) {
            return get_metadata('post', $product_id, 'mbbx_entity', true);
        }
        
        $product_terms_ids = wc_get_product_term_ids($product_id, 'product_cat');
        foreach ($product_terms_ids as $id) {
            if (get_metadata('term', $id, 'mbbx_entity', true))
                return get_metadata('term', $id, 'mbbx_entity', true);
        }
    }

    /* WEBHOOK METHODS */

    /**
     * Format the webhook data in an array.
     * 
     * @param int $cart_id
     * @param array $res
     * @param bool $multivendor
     * @param bool $multicard
     * @return array $data
     * 
     */
    public static function format_webhook_data($order_id, $res, $multicard, $multivendor)
    {
        $data = [
            'order_id'           => $order_id,
            'parent'             => MobbexHelper::is_parent_webhook($res['payment']['operation']['type'], $multicard) ? 'yes' : 'no',
            'operation_type'     => isset($res['payment']['operation']['type']) ? $res['payment']['operation']['type'] : '',
            'payment_id'         => isset($res['payment']['id']) ? $res['payment']['id'] : '',
            'description'        => isset($res['payment']['description']) ? $res['payment']['description'] : '',
            'status_code'        => isset($res['payment']['status']['code']) ? $res['payment']['status']['code'] : '',
            'status_message'     => isset($res['payment']['status']['message']) ? $res['payment']['status']['message'] : '',
            'source_name'        => isset($res['payment']['source']['name']) ? $res['payment']['source']['name'] : 'Mobbex',
            'source_type'        => isset($res['payment']['source']['type']) ? $res['payment']['source']['type'] : 'Mobbex',
            'source_reference'   => isset($res['payment']['source']['reference']) ? $res['payment']['source']['reference'] : '',
            'source_number'      => isset($res['payment']['source']['number']) ? $res['payment']['source']['number'] : '',
            'source_expiration'  => isset($res['payment']['source']['expiration']) ? json_encode($res['payment']['source']['expiration']) : '',
            'source_installment' => isset($res['payment']['source']['installment']) ? json_encode($res['payment']['source']['installment']) : '',
            'installment_name'   => isset($res['payment']['source']['installment']['description']) ? json_encode($res['payment']['source']['installment']['description']) : '',
            'installment_amount' => isset($res['payment']['source']['installment']['amount']) ? $res['payment']['source']['installment']['amount'] : '',
            'installment_count'  => isset($res['payment']['source']['installment']['count'] ) ? $res['payment']['source']['installment']['count']  : '',
            'source_url'         => isset($res['payment']['source']['url']) ? json_encode($res['payment']['source']['url']) : '',
            'cardholder'         => isset($res['payment']['source']['cardholder']) ? json_encode(($res['payment']['source']['cardholder'])) : '',
            'entity_name'        => isset($res['entity']['name']) ? $res['entity']['name'] : '',
            'entity_uid'         => isset($res['entity']['uid']) ? $res['entity']['uid'] : '',
            'customer'           => isset($res['customer']) ? json_encode($res['customer']) : '',
            'checkout_uid'       => isset($res['checkout']['uid']) ? $res['checkout']['uid'] : '',
            'total'              => isset($res['payment']['total']) ? $res['payment']['total'] : '',
            'currency'           => isset($res['checkout']['currency']) ? $res['checkout']['currency'] : '',
            'risk_analysis'      => isset($res['payment']['riskAnalysis']['level']) ? $res['payment']['riskAnalysis']['level'] : '',
            'data'               => json_encode($res),
            'created'            => isset($res['payment']['created']) ? $res['payment']['created'] : '',
            'updated'            => isset($res['payment']['updated']) ? $res['payment']['created'] : '',
        ];

        return $data;
    }

    /**
     * Receives the webhook "opartion type" and return true if the webhook is parent and false if not
     * 
     * @param string $operationType
     * @param bool $multicard
     * @return bool true|false
     * @return bool true|false
     * 
     */
    public static function is_parent_webhook($operationType, $multicard, $multivendor)
    {
        if ($operationType === "payment.v2") {
            if ($multicard || $multivendor)
                return false;
        }
        return true;
    }

    /**
     * Receives an array and returns an array with the data format for the 'insert' method
     * 
     * @param array $array
     * @return array $format
     * 
     */
    public static function db_column_format($array)
    {
        $format = [];

        foreach ($array as $value) {
            switch (gettype($value)) {
                case "bolean":
                    $format[] = '%s';
                    break;
                case "integer":
                    $format[] = '%d';
                    break;
                case "double":
                    $format[] = '%f';
                    break;
                case "string":
                    $format[] = '%s';
                    break;
                case "array":
                    $format[] = '%s';
                    break;
                case "object":
                    $format[] = '%s';
                    break;
                case "resource":
                    $format[] = '%s';
                    break;
                case "NULL":
                    $format[] = '%s';
                    break;
                case "unknown type":
                    $format[] = '%s';
                    break;
                case "bolean":
                    $format[] = '%s';
                    break;
            }
        }
        return $format;
    }
}
