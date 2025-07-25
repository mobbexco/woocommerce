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

define('MOBBEX_VERSION', '3.20.3');
define('MOBBEX_SDK_VERSION', '1.1.0');
define('MOBBEX_EMBED_VERSION', '1.2.0');

define('MOBBEX_LIST_PLANS', 'https://api.mobbex.com/p/sources/list/arg/{tax_id}?total={total}');

define('MOBBEX_PAYMENT_IMAGE', 'https://res.mobbex.com/images/sources/{reference}.png');

