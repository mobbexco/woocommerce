<?php
require_once 'utils.php';

class MobbexHelper
{
    /**
     * All 'ahora' plans.
     */
    public static $ahora = ['ahora_3', 'ahora_6', 'ahora_12', 'ahora_18'];

    /** Module configuration settings */
    public $settings = [];

    /**
     * Load plugin settings.
     */
    public function __construct()
    {
        // Get default and saved values
        $default_values = array_fill_keys(array_keys(include('config-options.php')), null);
        $saved_values   = get_option('woocommerce_' . MOBBEX_WC_GATEWAY_ID .'_settings', null) ?: [];

        // The intent constant overwrite payment mode setting
        if (defined('MOBBEX_CHECKOUT_INTENT')) {
            $saved_values['payment_mode'] = MOBBEX_CHECKOUT_INTENT;
        } else if ($saved_values['payment_mode'] == 'yes') {
            $saved_values['payment_mode'] = 'payment.2-step';
        } else {
            $saved_values['payment_mode'] = 'payment.v2';
        }

        $this->settings = array_merge($default_values, $saved_values);

        // Set settings as properties for backward support
        foreach ($this->settings as $key => $value) {
            $key = str_replace('-', '_', $key);
            $this->$key = $value;
        }
    }

    public function debug($message = 'debug', $data = [], $force = false)
    {
        if ($this->settings['debug_mode'] != 'yes' && !$force)
            return;

        apply_filters(
            'simple_history_log',
            'Mobbex: ' . $message,
            $data,
            'debug'
        );
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

        // Get installments
        $installments = self::get_installments_query($inactivePlans, $activePlans);

        if ($installments)
            $data .= '&' . $installments;

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

            if (!empty($response['data']))
                return $response['data'];
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
     * Return entity UID from a product ID.
     * 
     * @param int|string $product_id
     * 
     * @return string|null
     */
    public function get_entity($product_id)
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
            'parent'             => MobbexHelper::is_parent_webhook($res['payment']['operation']['type'], $multicard, $multivendor) ? 'yes' : 'no',
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

    public function get_api_endpoint($endpoint, $order_id)
    {
        $query = [
            'mobbex_token' => $this->generate_token(),
            'platform' => "woocommerce",
            "version" => MOBBEX_VERSION,
        ];

        if ($order_id) {
            $query['mobbex_order_id'] = $order_id;
        }

        if ($endpoint === 'mobbex_webhook' && $this->settings['use_webhook_api']) {
            return add_query_arg($query, get_rest_url(null, 'mobbex/v1/webhook'));
        } else {
            $query['wc-api'] = $endpoint;
        }

        return add_query_arg($query, home_url('/'));
    }

    public function valid_mobbex_token($token)
    {
        return $token == $this->generate_token();
    }

    public function generate_token()
    {
        return md5($this->settings['api-key'] . '|' . $this->settings['access-token']);
    }

    public function get_product_image($product_id)
    {
        $product = wc_get_product($product_id);

        if (!$product)
            return;

        $image   = wp_get_attachment_image_url($product->get_image_id(), 'thumbnail');
        $default = wc_placeholder_img_src('thumbnail');

        return $image ?: $default;
    }

    /**
     * Update order total.
     * 
     * @param WC_Order $order
     * @param int|string $total
     */
    public function update_order_total($order, $total)
    {
        if ($total == $order->get_total())
            return;

        // Create an item with total difference
        $item = new WC_Order_Item_Fee();

        $item->set_name($total > $order->get_total() ? __('Cargo financiero', 'mobbex-for-woocommerce') : __('Descuento', 'mobbex-for-woocommerce'));
        $item->set_amount($total - $order->get_total());
        $item->set_total($total - $order->get_total());

        // Add the item and recalculate totals
        $order->add_item($item);
        $order->calculate_totals();
    }

    /**
     * Retrieve a checkout created from current Cart|Order as appropriate.
     * 
     * @uses Only to show payment options.
     * 
     * @return array|null
     */
    public function get_context_checkout()
    {
        $order = wc_get_order(get_query_var('order-pay'));
        $cart  = WC()->cart;

        $helper = $order ? new MobbexOrderHelper($order) : new MobbexCartHelper($cart);

        // If is pending order page create checkout from order and return
        if ($order)
            return $helper->create_checkout();

        // Try to get previous cart checkout data
        $cart_checkout = WC()->session->get('mobbex_cart_checkout');
        $cart_hash     = $cart->get_cart_hash();

        $response = isset($cart_checkout[$cart_hash]) ? $cart_checkout[$cart_hash] : $helper->create_checkout();

        if ($response)
            WC()->session->set('mobbex_cart_checkout', [$cart_hash => $response]);

        return $response;
    }
}