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
}