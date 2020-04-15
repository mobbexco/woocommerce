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

## Preguntas Frecuentes

#### Error: "No se pudo validar la transacción. Contacte con el administrador de su sitio"

Esto se debe a que tu sitio posee una redirección en el archivo .htaccess o a nivel servidor y no somos capaces de encontrar los parametros necesarios para validar tu transacción. Por favor revisá tu .htaccess o ponete en contacto con el administrador de tu servidor.

#### Error: "Token de seguridad inválido."

Al igual que el error anterior esto se debe a que el parametro de validación se pierde durante la redirección. Revisá la configuración de tu sitio.

## Changelog

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