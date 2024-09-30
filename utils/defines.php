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

define('MOBBEX_VERSION', '3.19.1');
define('MOBBEX_SDK_VERSION', '1.1.0');
define('MOBBEX_EMBED_VERSION', '1.0.23');

define('MOBBEX_LIST_PLANS', 'https://api.mobbex.com/p/sources/list/arg/{tax_id}?total={total}');

define('MOBBEX_PAYMENT_IMAGE', 'https://res.mobbex.com/images/sources/{reference}.png');

// Subscriptions defines
define('MOBBEX_CREATE_SUBSCRIPTION', 'https://api.mobbex.com/p/subscriptions');
define('MOBBEX_MODIFY_SUBSCRIPTION', 'https://api.mobbex.com/p/subscriptions/{id}');
define('MOBBEX_CREATE_SUBSCRIBER', 'https://api.mobbex.com/p/subscriptions/{id}/subscriber');
define('MOBBEX_SUBSCRIPTION', 'https://api.mobbex.com/p/subscriptions/{id}/subscriber/{sid}/execution');
define('MOBBEX_RETRY_EXECUTION', 'https://api.mobbex.com/p/subscriptions/{id}/subscriber/{sid}/execution/{eid}/action/retry');

// Subscriptions Coupon URL
define('MOBBEX_SUBS_DIRECTORY', plugin_dir_path(__DIR__) . 'vendor/mobbexco/woocommerce-subscriptions/src/');
define('MOBBEX_SUBS_COUPON', 'https://mobbex.com/console/{entity.uid}/operations/?oid={payment.id}');

define('MOBBEX_SUBS_WC_GATEWAY', 'WC_Gateway_Mbbx_Subs');
define('MOBBEX_SUBS_WC_GATEWAY_ID', 'mobbex_subs');

define('MOBBEX_SUBS_VERSION', '3.1.1');