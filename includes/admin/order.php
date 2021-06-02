<?php

class Mbbx_Order_Admin
{
    public static MobbexHelper $helper;

    public static function init()
    {
        // Load helper
        self::$helper = new MobbexHelper;

        // Register 'Authorized' Order status for 2-step payment mode
        self::register_authorized_order_status();
        add_filter('wc_order_statuses', [self::class, 'add_authorized_order_status']);

        // Make own statuses as valid for payment complete
        add_action('woocommerce_valid_order_statuses_for_payment_complete', [self::class, 'valid_statuses_for_payment_complete']);

        // Add capture action to Order admin page
        add_action('woocommerce_order_actions', [self::class, 'add_capture_action']);

        // Capture endpoint for order action
        add_action('woocommerce_order_action_mbbx_capture_payment', [self::class, 'capture_payment_endpoint']);

        // Add some scripts
        add_action('admin_enqueue_scripts', [self::class, 'load_scripts']);
    }

    /**
     * Create and register 'Authorized' order status.
     */
    public static function register_authorized_order_status()
    {
        $order_status = [
            'label'                     => __('Authorized', 'mobbex-for-woocommerce'),
            'public'                    => true,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list'    => true,
            'exclude_from_search'       => false,
            'label_count'               => _n_noop('Authorized <span class="count">(%s)</span>', 'Authorized <span class="count">(%s)</span>', 'mobbex-for-woocommerce'),
        ];

        register_post_status('wc-authorized', $order_status);
    }

    /**
     * Add 'Authorized' order status to order status select.
     *
     * @param array $order_statuses
     * @return array $order_statuses
     */
    public static function add_authorized_order_status($order_statuses)
    {
        return array_merge($order_statuses, ['wc-authorized' => __('Authorized', 'mobbex-for-woocommerce')]);
    }

    /**
     * Mark Mobbex order statuses as valid for payment complete.
     *
     * @param array $order_statuses
     * @return array $order_statuses
     */
    public static function valid_statuses_for_payment_complete($order_statuses)
    {
        return array_merge($order_statuses, ['authorized']);
    }

    /**
     * Add capture action to order actions select.
     * For use with 'Authorized' Orders.
     *
     * @param array $actions
     * @return array $actions
     */
    public static function add_capture_action($actions)
    {
        global $theorder;

        // Only add actions if order has 'Authorized' status
        if ($theorder->get_payment_method() == 'mobbex' && $theorder->has_status('authorized'))
            $actions['mbbx_capture_payment'] = __('Capture payment', 'mobbex-for-woocommerce'); // Capturar pago

        return $actions;
    }

    /**
     * Load styles and scripts for dynamic options.
     */
    public static function load_scripts($hook)
    {
        global $post;

        if (($hook == 'post-new.php' || $hook == 'post.php') && $post->post_type == 'shop_order') {
            wp_enqueue_style('mbbx-order-style', plugin_dir_url(__FILE__) . '../../assets/css/order-admin.css');
            wp_enqueue_script('mbbx-order', plugin_dir_url(__FILE__) . '../../assets/js/order-admin.js');

            $order       = wc_get_order($post->ID);
            $order_total = get_post_meta($post->ID, 'mbbxs_sub_total', true) ?: $order->get_total();

            // Add retry endpoint URL to script
            $mobbex_data = [
                'order_id'    => $post->ID,
                'order_total' => $order_total,
                'capture_url' => home_url('/wc-api/mbbx_capture_payment')
            ];
            wp_localize_script('mbbx-order', 'mobbex_data', $mobbex_data);
            wp_enqueue_script('mbbx-order');
        }
    }

    /**
     * Caputure 'Authorized' orders endpoint.
     * 
     * Endpoint called by order action.
     * 
     * @param WC_Order $order
     */
    public static function capture_payment_endpoint($order)
    {
        try {
            // Get "new total" value from post data
            $post_data     = wp_unslash($_POST);
            $capture_total = !empty($post_data['mbbx_capture_total']) ? $post_data['mbbx_capture_total'] : $order->get_total();

            // If data look fine
            if (is_numeric($capture_total)) {
                $order_id   = $order->get_id();
                $payment_id = get_post_meta($order_id, 'mobbex_payment_id', true);

                $result = self::$helper->capture_payment($payment_id, $capture_total);

                if ($result) {
                    update_post_meta($order_id, 'mbbx_total_captured', $capture_total);
                    $order->add_order_note(__('Payment Total Captured: $ ' , 'mobbex-for-woocommerce') . $capture_total);
                }
            }
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            $order->add_order_note(__('Payment Capture ERROR: ', 'mobbex-for-woocommerce') . $msg);
        }
    }
}