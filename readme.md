# Mobbex for Woocommerce

This plugin provides integration between WooCommerce and Mobbex Payment Solution. With the provided solution you will be able to get your store integrated with our payment gateway in mather of seconds. Just install it, enable the plugin and provide your credentials. That's all!!! You can get paid now ;).

## Installation

#### Wordpress

Version 4.4 or greater

#### Steps

1) Get the latest version of the plugin
2) Get into Plugins -> Add New
3) Hit the Upload plugin button
4) Select the zip file and upload
5) Activate the plugin

## Important Information and Interaction

#### WP Cerber ( Security Plugin )

If you are using WP Cerber for security you must go under WP Cerber settings on "Antispam" option, introduce the next sentence in the Query whitelist input box:

```wc-api=mobbex_webhook```

If you don't do it you won't be able to receive the information about the payment and will be marked in a wrong way.

## Changelog

### 2.0.2 :: 2019-08-23

- Implemented Mobbex Button
- Switch to enable/disable Mobbex Button
- New status handling
- New WooCommerce API.
- Some new information on the payment details
- Order ID into de Order Info

### 1.0.1

- Ignored status 0 and 1.
- Added payment method title.

### 1.0.0

- Initial release.

## TODO:

- [ ] Comments/Instructions.
- [x] Mobbex Button
- [x] Error handling. (process order)
- [x] Localization/Internationalization.
- [x] HTTPS check, WooCommerce check.
- [x] Use order items.