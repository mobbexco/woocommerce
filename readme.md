# Mobbex for Woocommerce

This plugin provides integration between WooCommerce and Mobbex Payment Solution. With the provided solution you will be able to get your store integrated with our payment gateway in mather of seconds. Just install it, enable the plugin and provide your credentials. That's all!!! You can get paid now ;).

## Installation

#### Wordpress

Version 5.0 or greater

#### WooCommerce

Version 3.5.2 or greater

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

#### Checkout data filter
To manipulate the information sent to Mobbex checkout, you must use the filter ```mobbex_checkout_custom_data```. When using it, it will receive as an argument the body of the checkout to be modified

#### Webhook data filter
To manipulate or save data from Mobbex webhook, you must use the filter ```mobbex_order_webhook```.
## Preguntas Frecuentes

#### Error: "No se pudo validar la transacción. Contacte con el administrador de su sitio"

Esto se debe a que tu sitio posee una redirección en el archivo .htaccess o a nivel servidor y no somos capaces de encontrar los parametros necesarios para validar tu transacción. Por favor revisá tu .htaccess o ponete en contacto con el administrador de tu servidor.

#### Error: "Token de seguridad inválido."

Al igual que el error anterior esto se debe a que el parametro de validación se pierde durante la redirección. Revisá la configuración de tu sitio.

## Changelog

## 3.12.0 :: 2023-02-27
- Fix wc cianbox support
- Improve expired webhooks processing and prevent duplicated status change
- Fix that check if risk analysis is set
- Refactor: added new model to manage the module settings
- Fix: updated the js sdk of mobbex embed from v1.0.20 to v1.0.23
- Add an option to customize the route to be redirected to when there is an error
- Add the option to configure the site-id in advanced options
- Add improved customizations options

## 3.11.0 :: 2022-12-27
- Re-add finnacial cost/discount on every parent webhook
- Enable webhook debugging with XDebug by option
- Add the option to set the 'timeout' node in the checkout body
- Improve dependencies error showing

## 3.10.0 :: 2022-12-02
- Force addresses street type to string to prevent errors
- Fix order total updating on processing mail view
- Add warning on wrong directory installs
- Fix credentials check warning on admin panel
- Add payment information widget in the order panel
- Fix pending orders form submit handle

### 3.9.1 :: 2022-11-07
- Add assets enqueue support for yith checkout manager
- Fix logger duplicated ready notice
- Fix result code passing on checkout exception
- New POSIX compatible script for build

### 3.9.0 :: 2022-10-06
- Allow to make refunds of operations paid with multiple cards (multicard) from platform.
- Add notes with child transaction information to the order, once their are processed.
- Use more specific selector when capturing the checkout completion event.
- Modify address handling to improve data security.
- Modify financing widget images routes to improve data security.
- Fix order status and order amount update when payment fails.
- Fix processing of webhooks received when doing partial returns from console.
- Fix width of the images of the payment methods in checkout.
- Fix getting Appearance tab "Font Size" option.
- Fix addition of the operation risk note of each order.

### 3.8.1 :: 2022-08-12
- Fix PHP < 7.3 support.
- Fix payment_mode config access on helper.

### 3.8.0 :: 2022-06-27
- Add custom order status options.
- Add options to link products with subscriptions.
- Support application/json type webhooks.
- Use new woocommerce api method by default.
- Refresh saved entity data on plugin deactivation.

### 3.7.1 :: 2022-05-27
- Fix mbbxToggleOptions function name conflict with subscriptions plugin
- Fix parent webhooks check when using multivendor
- Fix plugin default settings obtaining
- Remove jQuery BlockUI plugin dependencies
- Remove timestamp from checkout references to prevent duplicated payments.
- Remove customer user agent from checkout data.

### 3.7.0 :: 2022-02-11
- Add option to show finance widget on cart page
- Apply title option to card_input method
- Update finance widget prices on product variant changes
- Format widget prices using platform configuration
- Fix finance widget customization options
- Fix DNI options hiding

### 3.6.3 :: 2022-01-24
- Add ecommerce agent header to API calls
- Fix checkout url generation for some installations.

### 3.6.2 :: 2021-12-28
- Add installment amount legend to finance widget.
- Send address and user agent data to Mobbex in checkout generation.
- Improve dni save method.

### 3.6.1 :: 2021-11-11
- Fix checkout form submit validation

### 3.6.0 :: 2021-11-11
- Show payment methods directly in woocommerce checkout
- Add multivendor options to product and category settings
- Add 2-step mail sending option
- Save webhook data in new transaction table
- Show installments amount in saved cards
- Improve configuration management
- Fix customer DNI save
- Fix personalization options obtaining

### 3.5.1 :: 2021-11-05
- Remove all trailing commas for PHP < 7.3 support

### 3.5.0 :: 2021-11-04
- Add finance widget personalization options 
- Filter plans hidden by plans with advanced rules in finance widget
- Add debug mode option.
- Add more platform data on checkout generation and webhook response
- Refactor checkout generation. Use cart to create wallet checkout
- Fix checkout items update on cart update when wallet is active
- Fix duplicated orders when wallet is active
- Fix checkout DNI field persistence
- Fix checkout domain obtaining (remove subdomain)

### 3.4.0 :: 2021-08-11
- Add multicard mode option
- New common and advanced plans filter by category
- Filter finance widget plans based on product and product categories settings
- Make embed mode enabled by default
- Improve category settings view
- Improve finance widget view and performance
- Fix get store when multisite is disabled
- Fix order mail sending in 2step payment mode
- Fix finance widget positioning
- Fix wallet form rendering in pay-for-order page
- Fix checkout form data saving when use wallet
- Fix domain obtaining for sites installed in subfolders

### 3.3.3 :: 2021-07-16
- Organize plugin settings by groups.
- Fix user pending orders endpoint url and response format.
- Fix financing widget images rendering.
- Fix financing widget plans obtaining loop when using ajax.

### 3.3.2 :: 2021-07-02
- Unify and improve financing widget view.
- Some fixes.

### 3.3.1 :: 2021-06-09
- Structure improvements and some fixes.

### 3.3.0 :: 2021-06-04
- Added Multisite settings to product and category admin pages.
- New Shortcode functionality for financing widget.
- Inactive plans are no longer displayed in the financing widget.
- New 2-step payment mode.
- New Action to capture a payment from the order admin page.
- Improves plans filter and financing widget views.

### 3.2.1 :: 2021-05-18
- Fix get plans by category in checkout creation
- Add cache constants defined condition

### 3.2.0 :: 2021-05-10
- Add finnancing button to grouped and variable products
- Fix advanced plans condition (must now be active on all products in a payment)
- Refactor get_installments function

### 3.1.7 :: 2021-04-20
- Fix own DNI field validation
- Add webhook API permission callback
- Fix helper instancing on install (set default options empty)
- New internal hooks (product admin configuration)
- Add MIT licence

### 3.1.6 :: 2021-03-11
- Fix helper properties loading.

### 3.1.5 :: 2021-03-01
- Fix assets equeue using helper.
- Fix categories plans get in checkout.

### 3.1.4 :: 2021-02-19
- Add uniqueness to funding widget styles to prevent design errors.
- Add additional verifications to get_checkout and financing widget filters,
- Unextend helper from WC_Settings_API for compatibility with other plugins.

### 3.1.3 :: 2021-02-04
- Fix wallet option error when sending empty Wallet form in checkout.
- Cards view improvements in Wallet form.
- Move checkout do action to support Wallet checkouts.
- Add check for unsaved plans data.

### 3.1.2 :: 2021-01-26
- Add do_action filters to checkout and webhook process.
- Fix modal styles.

### 3.1.1 :: 2021-01-18
- Re-add reseller id to reference.
- Send order id as argument in checkout hook.

### 3.1.0 :: 2021-01-15
- Add plans filter by category.
- Add common/advanced plans filter by product.
- Add plans widget to product view.
- New filter to edit mobbex webhook data from external plugins/themes.
- Fix minify conflicts with cache plugins.
- Structure improvements.

### 3.0.3 :: 2020-12-16
- Fix handling of order hold states.

### 3.0.2 :: 2020-12-03
- Fix DNI field save.

### 3.0.1 :: 2020-12-01
- Uniqueness improvement for reference id.
- Structure improvements.

### 3.0.0 :: 2020-10-29
- Implemented Mobbex Wallet fully on-site.
- Switch to enable/disable Mobbex Wallet.
- Integrate plugin update checker.
- Save customer information when not logged in and has paid.
- New constant for checkout management.
- Send checkout lifetime to Mobbex.
- New filter to edit checkout data from external plugins/themes.
- Mobbex Embed updated to 1.0.17.

### 2.4.0 :: 2020-09-14
- Implemented mobbex refund.
- Set processing fees/discounts on Checkout.
- Save mobbex payment data in Order.

### 2.3.0 :: 2020-06-08
- Use the standard WC API.
- Custom Mobbex Header Settings.
- Add Customer into Checkout for Smart Checkout.
- New Mobbex Button.
- Reseller ID Settings.
- Set the Paid Total on Checkout.
- Multiple Fixes.

### 2.2.0 :: 2020-04-15
- Added new Theme Settings
- New Webhook using WP API
- Mobbex Button is now disabled by default because is not Working with WooCommerce 4 due to new React UI ( Fix in Progress )
- Better Logging on DEBUG Mode
- Moved constants into defines
- Some fixes because bugs must be squashed.

### 2.1.3 :: 2020-01-22
- Mobbex Button v0.9.30
- Multiple fixes.

### 2.1.2 :: 2020-01-21
- Fix for Mobbex Button
- Some fixes on the new version.

### 2.1.0 :: 2020-01-21

- Implemented new Test Mode using your own credentials.
- New Translations.
- New Mobbex Button 0.9.22 with In Site Experience.
- New standards and helpers methods.
- Some important fixes on the module.

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
