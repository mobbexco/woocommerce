<?php

class MobbexOrderHelper
{
    /** Order instance ID */
    public $id;

    /** @var WC_Order */
    public $order;

    /** @var MobbexHelper */
    public $helper;

    /**
    * Constructor.
    * 
    * @param WC_Order WooCommerce Order instance.
    * @param MobbexHelper Base plugin helper.
    */
    public function __construct($order, $helper = null)
    {
        $this->id     = $order->get_id();
        $this->order  = $order;
        $this->helper = $helper ?: new MobbexHelper();
    }

    /**
     * Create a checkout using the WooCommerce Order instance.
     * 
     * @return mixed
     */
    public function create_checkout()
    {
        // Try to configure api with order store credentials
        $store = $this->get_store();

        $api_key      = !empty($store['api_key']) ? $store['api_key'] : $this->helper->settings['api-key'];
        $access_token = !empty($store['access_token']) ? $store['access_token'] : $this->helper->settings['access-token'];

        $api      = new MobbexApi($api_key, $access_token);
        $checkout = new MobbexCheckout($this->helper->settings, $api);

        $this->add_initial_data($checkout);
        $this->add_items($checkout);
        $this->add_installments($checkout);
        $this->add_customer($checkout);

        try {
            $response = $checkout->create();
        } catch (\Exception $e) {
            $response = null;
            $this->helper->debug('Mobbex Checkout Creation Failed: ' . $e->getMessage(), isset($e->data) ? $e->data : '');
        }

        do_action('mobbex_checkout_process', $response, $this->id);

        return $response;
    }

    /**
     * Add order initial data to checkout.
     * 
     * @param MobbexCheckout $checkout
     */
    private function add_initial_data($checkout)
    {
        $checkout->set_reference($this->id);
        $checkout->set_total($this->order->get_total());
        $checkout->set_endpoints(
            $this->helper->get_api_endpoint('mobbex_return_url', $this->id),
            $this->helper->get_api_endpoint('mobbex_webhook', $this->id)
        );
    }

    /**
     * Add order items to checkout.
     * 
     * @param MobbexCheckout $checkout
     */
    private function add_items($checkout)
    {
        $order_items    = $this->order->get_items() ?: [];
        $shipping_items = $this->order->get_items('shipping') ?: [];

        foreach ($order_items as $item)
            $checkout->add_item(
                $item->get_total(),
                $item->get_quantity(),
                $item->get_name(),
                $this->helper->get_product_image($item->get_product_id()),
                $this->helper->get_entity($item->get_product_id())
            );

        foreach ($shipping_items as $item)
            $checkout->add_item($item->get_total(), 1, __('Shipping: ', 'mobbex-for-woocommerce') . $item->get_name());
    }

    /**
     * Add installments configured to checkout.
     * 
     * @param MobbexCheckout $checkout
     */
    private function add_installments($checkout)
    {
        $inactive_plans = $active_plans = [];
        $products = $this->helper::get_product_ids($this->order);

        // Get plans from order products
        foreach ($products as $product_id) {
            $inactive_plans = array_merge($inactive_plans, $this->helper::get_inactive_plans($product_id));
            $active_plans   = array_merge($active_plans, $this->helper::get_active_plans($product_id));
        }

        // Block inactive (common) plans from installments
        foreach ($inactive_plans as $plan_ref)
            $checkout->block_installment($plan_ref);

        // Add active (advanced) plans to installments (only if the plan is active on all products)
        foreach (array_count_values($active_plans) as $plan_uid => $reps) {
            if ($reps == count($products))
                $checkout->add_installment($plan_uid);
        }
    }

    /**
     * Add order customer data to checkout.
     * 
     * @param MobbexCheckout $checkout
     */
    private function add_customer($checkout)
    {
        $user = new WP_User($this->order->get_user_id());

        $checkout->set_customer(
            $this->order->get_formatted_billing_full_name() ?: $user->display_name,
            $this->order->get_billing_email() ?: $user->user_email,
            get_post_meta($this->id, '_billing_dni', true) ?: get_user_meta($user->ID, 'billing_dni', true),
            $this->order->get_billing_phone() ?: get_user_meta($user->ID, 'phone_number', true),
            $user->ID
        );

        $checkout->set_address(
            $this->order->get_billing_address_1(),
            $this->order->get_billing_postcode(),
            $this->order->get_billing_state(),
            $this->helper->convert_country_code($this->order->get_billing_country()),
            $this->order->get_customer_note(),
            $this->order->get_customer_user_agent()
        );
    }

    /**
     * Get Store from order items multisite configuration.
     * 
     * @return array|null
     */
    public function get_store()
    {
        $stores = get_option('mbbx_stores') ?: [];
        $items  = $this->order->get_items() ?: [];

        // Search store configured
        foreach ($items as $item) {
            $store_id = $this->helper::get_store_from_product($item->get_product_id());

            if ($store_id && !empty($stores[$store_id]))
                return $stores[$store_id];
        }
    }
}