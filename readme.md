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
