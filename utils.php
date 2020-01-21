<?php

// Defines
define( 'MOBBEX_WC_TEXT_DOMAIN', 'mobbex-for-woocommerce' );
define( 'MOBBEX_CHECKOUT', 'https://mobbex.com/p/checkout/create' );

if (!function_exists('mobbex_debug')) {
    // https://github.com/bonny/WordPress-Simple-History/

    function mobbex_debug($message, $data = [])
    {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG === true) {
            apply_filters(
                'simple_history_log',
                'Mobbex: ' . $message,
                $data,
                'debug'
            );
        }
    }

}
