<?php

// Defines
define('MOBBEX_WC_TEXT_DOMAIN', 'mobbex-for-woocommerce');
define('MOBBEX_CHECKOUT', 'https://api.mobbex.com/p/checkout');
define('MOBBEX_REFUND', 'https://api.mobbex.com/p/operations/{ID}/refund');
define('MOBBEX_CAPTURE_PAYMENT', 'https://api.mobbex.com/p/operations/{id}/capture');

// Sources and plans
define('MOBBEX_SOURCES', 'https://api.mobbex.com/p/sources');
define('MOBBEX_ADVANCED_PLANS', 'https://api.mobbex.com/p/sources/rules/{rule}/installments');

// Coupon URL
define('MOBBEX_COUPON', 'https://mobbex.com/console/{entity.uid}/operations/?oid={payment.id}');

define('MOBBEX_WC_GATEWAY', 'WC_Gateway_Mobbex');
define('MOBBEX_WC_GATEWAY_ID', 'mobbex');

define('MOBBEX_VERSION', '3.3.3');
define('MOBBEX_EMBED_VERSION', '1.0.17');

define('MOBBEX_LIST_PLANS', 'https://api.mobbex.com/p/sources/list/arg/{tax_id}?total={total}');

define('MOBBEX_PAYMENT_IMAGE', 'https://res.mobbex.com/images/sources/{reference}.png');

define('MOBBEX_TAX_ID', 'https://api.mobbex.com/p/entity/validate');

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
