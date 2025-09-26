# Mobbex for Woocommerce

This plugin provides integration between WooCommerce and Mobbex Payment Solution. With the provided solution you will be able to get your store integrated with our payment gateway in mather of seconds. Just install it, enable the plugin and provide your credentials. That's all!!! You can get paid now ;).

## Requirements
- PHP 7.0 -> 8.3.6
- WordPress 5 -> 6.8.2
- WooCommerce 3.5.2 -> 10.2.1
- MySQL 5.6 -> 8.0

## Installation

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
