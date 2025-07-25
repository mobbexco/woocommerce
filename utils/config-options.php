<?php

defined('ABSPATH') || exit;

return [
    /* General options */

    'enabled' => [
        'title'   => __('Enable/Disable', 'mobbex-for-woocommerce'),
        'type'    => 'checkbox',
        'label'   => __('Enable checking out with Mobbex.', 'mobbex-for-woocommerce'),
        'default' => 'yes',
    ],

    'api-key' => [
        'title'       => __('API Key', 'mobbex-for-woocommerce'),
        'description' => __('Your Mobbex API key.', 'mobbex-for-woocommerce'),
        'type'        => 'text',
        'default'     => '',
    ],

    'access-token' => [
        'title'       => __('Access Token', 'mobbex-for-woocommerce'),
        'description' => __('Your Mobbex access token.', 'mobbex-for-woocommerce'),
        'type'        => 'text',
        'default'     => '',
    ],

    'test' => [
        'title'   => __('Enable/Disable Test Mode', 'mobbex-for-woocommerce'),
        'type'    => 'checkbox',
        'label'   => __('Enable Test Mode.', 'mobbex-for-woocommerce'),
        'default' => 'no',
    ],

    'button' => [
        'title'   => __('Enable/Disable Button', 'mobbex-for-woocommerce'),
        'type'    => 'checkbox',
        'label'   => __('Enable Mobbex Button experience.', 'mobbex-for-woocommerce'),
        'default' => 'yes',
    ],

    'wallet' => [
        'title'   => __('Enable/Disable Wallet', 'mobbex-for-woocommerce'),
        'type'    => 'checkbox',
        'label'   => __('Enable Mobbex Wallet experience.', 'mobbex-for-woocommerce'),
        'default' => 'no',
    ],

    'financial_info_active' => [
        'title'       => __('Financial Information', 'mobbex-for-woocommerce'),
        'description' => __('Show financial information in all products.', 'mobbex-for-woocommerce'),
        'type'        => 'checkbox',
        'default'     => '',

    ],

    'own_dni' => [
        'title'       => __('Add DNI field', 'mobbex-for-woocommerce'),
        'description' => __('Add DNI field on checkout.', 'mobbex-for-woocommerce'),
        'type'        => 'checkbox',
        'default'     => '',
    ],

    'custom_dni' => [
        'title'       => __('Use custom DNI field', 'mobbex-for-woocommerce'),
        'description' => __('If you ask for DNI field on checkout please provide the custom field.', 'mobbex-for-woocommerce'),
        'type'        => 'text',
        'default'     => '',
    ],

    /* Orders options */
    'orders_tab' => [
        'title' => __('Orders Configuration', 'mobbex-for-woocommerce'),
        'type'  => 'title',
        'class' => 'mbbx-tab mbbx-tab-orders',
    ],

    'order_status_approved' => [
        'title'       => __('Order status approve', 'mobbex-for-woocommerce'),
        'description' => __('Select the status for approve orders.', 'mobbex-for-woocommerce'),
        'type'        => 'select',
        'options'     => function_exists('wc_get_order_statuses') ? wc_get_order_statuses() : [],
        'default'     => 'wc-processing',
        'class'       => 'mbbx-into-orders',
    ],

    'order_status_on_hold' => [
        'title'       => __('Order status on hold', 'mobbex-for-woocommerce'),
        'description' => __('Select the status for on hold orders.', 'mobbex-for-woocommerce'),
        'type'        => 'select',
        'options'     => function_exists('wc_get_order_statuses') ? wc_get_order_statuses() : [],
        'default'     => 'wc-on-hold',
        'class'       => 'mbbx-into-orders',
    ],

    'order_status_failed' => [
        'title'       => __('Order status failed', 'mobbex-for-woocommerce'),
        'description' => __('Select the status for failed orders.', 'mobbex-for-woocommerce'),
        'type'        => 'select',
        'options'     => function_exists('wc_get_order_statuses') ? wc_get_order_statuses() : [],
        'default'     => 'wc-failed',
        'class'       => 'mbbx-into-orders',
    ],
    'order_status_refunded' => [
        'title'       => __('Order status refunded', 'mobbex-for-woocommerce'),
        'description' => __('Select the status for refunded orders.', 'mobbex-for-woocommerce'),
        'type'        => 'select',
        'options'     => function_exists('wc_get_order_statuses') ? wc_get_order_statuses() : [],
        'default'     => 'wc-refunded',
        'class'       => 'mbbx-into-orders',
    ],
    'paid_statuses' => [
        'title'       => __('Paid Statuses', 'mobbex-for-woocommerce'),
        'description' => __('Select the statuses that you want to be treated as paid statuses. (Hold Ctrl & click to select more than one).', 'mobbex-for-woocommerce'),
        'type'        => 'multiselect',
        'options'     => function_exists('wc_get_order_statuses') ? wc_get_order_statuses() : [],
        'default'     => [],
        'class'       => 'mbbx-into-orders',
    ],

    /* Appearance options */

    'appearance_tab' => [
        'title' => __('Appearance', 'mobbex-for-woocommerce'),
        'type'  => 'title',
        'class' => 'mbbx-tab mbbx-tab-appearance',
    ],

    'title'=> [
        'title'       => __('Title', 'mobbex-for-woocommerce'),
        'type'        => 'text',
        'description' => __('This title will be shown on user checkout.', 'mobbex-for-woocommerce'),
        'default'     => __('Pay with Mobbex', 'mobbex-for-woocommerce'),
        'desc_tip'    => true,
        'class'       => 'mbbx-into-appearance',
    ],
    
    'payment_method_image' => [
        'title'       => __('Payment method image', 'mobbex-for-woocommerce'),
        'description' => __('You can customize your checkout logo from here.', 'mobbex-for-woocommerce'),
        'type'        => 'text',
        'class'       => 'mbbx-into-appearance',
    ],

    'description' => [
        'title'       => __('Description', 'mobbex-for-woocommerce'),
        'description' => __('This description will be shown on user checkout.', 'mobbex-for-woocommerce'),
        'type'        => 'textarea',
        'default'     => '',
        'class'       => 'mbbx-into-appearance',
    ],

    'theme' => [
        'title'       => __('Visual Theme', 'mobbex-for-woocommerce'),
        'description' => __('You can customize your Checkout Theme from here.', 'mobbex-for-woocommerce'),
        'type'        => 'select',
        'options'     => [
            'light' => __('Light Theme', 'mobbex-for-woocommerce'),
            'dark'  => __('Dark Theme', 'mobbex-for-woocommerce'),
        ],
        'default'     => 'light',
        'class'       => 'mbbx-into-appearance',
    ],

    'header_name' => [
        'title'       => __('Header Name', 'mobbex-for-woocommerce'),
        'description' => __('You can customize your Checkout Title from here.', 'mobbex-for-woocommerce'),
        'type'        => 'text',
        'default'     => '',
        'class'       => 'mbbx-into-appearance',
    ],

    'header_logo'  => [
        'title'       => __('Header Logo URL', 'mobbex-for-woocommerce'),
        'description' => __('You can customize your Header Logo from here. The logo URL must be HTTPS and must be only set if required. If not set the Logo set on Mobbex will be used. Dimensions: 250x250 pixels', 'mobbex-for-woocommerce'),
        'type'        => 'text',
        'default'     => '',
        'class'       => 'mbbx-into-appearance',
    ],

    'background' => [
        'title'       => __('Checkout Background Color', 'mobbex-for-woocommerce'),
        'description' => __('You can customize your Checkout Background Color from here.', 'mobbex-for-woocommerce'),
        'type'        => 'text',
        'default'     => '#ECF2F6',
        'class'       => 'colorpick mbbx-into-appearance',
    ],

    'color' => [
        'title'       => __('Checkout Primary Color', 'mobbex-for-woocommerce'),
        'description' => __('You can customize your Checkout Primary Color for Buttons and TextFields from here.', 'mobbex-for-woocommerce'),
        'type'        => 'text',
        'class'       => 'colorpick mbbx-into-appearance',
        'default'     => '#6f00ff',
    ],

    'financial_widget_on_cart' => [
        'title'       => __('Widget de financiación en carrito', 'mobbex-for-woocommerce'),
        'description' => __('Mostrar el botón de financiación en la página del carrito.', 'mobbex-for-woocommerce'),
        'type'        => 'checkbox',
        'default'     => 'no',
        'class'       => 'mbbx-into-appearance',
    ],

    'show_featured_installments' => [
        'title'       => __('Mostrar cuotas destacadas', 'mobbex-for-woocommerce'),
        'description' => __('Permite mostrar u ocultar cuotas destacadas en el widget de financiación.', 'mobbex-for-woocommerce'),
        'type'        => 'checkbox',
        'default'     => 'yes',
        'class'       => 'mbbx-into-appearance',
    ],

    'checkout_banner' => [
        'title'       => __('Mobbex Banner in Checkout', 'mobbex-for-woocommerce'),
        'description' => __('You can set a banner to show in Mobbex checkout. (ex. https://res.mobbex.com/ecommerce/mobbex_1.png).', 'mobbex-for-woocommerce'),
        'type'        => 'text',
        'default'     => '',
        'class'       => 'mbbx-into-appearance',
    ],
    'method_icon' => [
        'title'     => __('Enable/Disable Methods Icons', 'mobbex-for-woocommerce'),
        'type'      => 'checkbox',
        'label'     => __('Show payment methods icons in checkout.', 'mobbex-for-woocommerce'),
        'default'   => 'yes',
        'class'       => 'mbbx-into-appearance',
    ],

    'show_no_interest_labels' => [
        'title'       => __('Mostrar etiqueta "sin intereses" en el checkout', 'mobbex-for-woocommerce'),
        'type'        => 'checkbox',
        'default'     => 'yes',
        'class'       => 'mbbx-into-appearance',
    ],

    /* Advanced options */

    'advanced_configuration_tab' => [
        'title' => __('Advanced Configuration', 'mobbex-for-woocommerce'),
        'type'  => 'title',
        'class' => 'mbbx-tab mbbx-tab-advanced',
    ],

    'multicard' => [
        'title'   => __('Enable/Disable Multicard', 'mobbex-for-woocommerce'),
        'type'    => 'checkbox',
        'label'   => __('Allow to pay the operation with multiple cards (incompatible with marketplace).', 'mobbex-for-woocommerce'), // Permite abonar la operación con múltiples tarjetas
        'default' => 'no',
        'class'   => 'mbbx-into-advanced',
    ],

    'multivendor' => [
        'title'   => __('Enable/Disable Multivendor', 'mobbex-for-woocommerce'),
        'type'    => 'select',
        'label'   => __('Allow to pay the operation with multiple vendor (incompatible with multicard).', 'mobbex-for-woocommerce'), // Permite abonar la operación con múltiples tarjetas
        'options' => [
            'no'      => __('Disable', 'mobbex-for-woocommerce'),
            'active'  => __('Active', 'mobbex-for-woocommerce'),
            'unified' => __('Unified', 'mobbex-for-woocommerce'),
        ],
        'default' => 'no',
        'class'   => 'mbbx-into-advanced',
    ],

    'payment_mode' => [
        'title'   => __('Enable/Disable 2-step Payment Mode', 'mobbex-for-woocommerce'),
        'type'    => 'checkbox',
        'label'   => __('Enable 2-step Payment Mode.', 'mobbex-for-woocommerce'),
        'default' => 'no',
        'class'   => 'mbbx-into-advanced',
    ],

    'two_step_processing_mail' => [
        'title'       => __('Mail de pedido procesado', 'mobbex-for-woocommerce'),
        'description' => __('Para uso de operatoria en 2 pasos', 'mobbex-for-woocommerce'),
        'type'        => 'select',
        'default'     => 'capture',
        'class'       => 'mbbx-into-advanced',
        'options'     => [
            'capture'   => __('Al capturar un pago', 'mobbex-for-woocommerce'),
            'authorize' => __('Al autorizar un pago', 'mobbex-for-woocommerce'),
        ],
    ],

    'reseller_id' => [
        'title'       => __('Reseller ID', 'mobbex-for-woocommerce'),
        'description' => __('You can customize your Reseller ID from here. This field is optional and must be used only if was specified by the main seller.', 'mobbex-for-woocommerce'),
        'type'        => 'text',
        'default'     => '',
        'class'       => 'mbbx-into-advanced',
    ],

    'error_redirection' => [
        'title'       => __('Redirection after error', 'mobbex-for-woocommerce'),
        'description' => __('You can customize the route to be redirected to after a payment error. It must be an existing path within the store.', 'mobbex-for-woocommerce'),
        'type'        => 'text',
        'default'     => '',
        'class'       => 'mbbx-into-advanced',
    ],

    'site_id' => [
        'title'       => __('Site ID', 'mobbex-for-woocommerce'),
        'description' => __('Si utiliza las mismas credenciales en otro sitio complete este campo con un identificador que permita diferenciar las referencias de sus operaciones.', 'mobbex-for-woocommerce'),
        'type'        => 'text',
        'default'     => '',
        'class'       => 'mbbx-into-advanced',
    ],

    'debug_mode' => [
        'title'   => __('Modo Debug', 'mobbex-for-woocommerce'),
        'label'   => __('Activar Modo Debug.', 'mobbex-for-woocommerce'),
        'class'   => 'mbbx-into-advanced',
        'type'    => 'checkbox',
        'default' => 'no',
    ],

    'payment_methods' => [
        'title'   => __('Medios de pago en sitio', 'mobbex-for-woocommerce'),
        'label'   => __('Habilita la subdivisión de los métodos de pago en la página de finalización de la compra. Las opciones se verán en el checkout.', 'mobbex-for-woocommerce'),
        'class'   => 'mbbx-into-advanced',
        'type'    => 'checkbox',
        'default' => 'no',
    ],

    'disable_discounts' => [
        'title'   => __('Descuentos Desabilitados', 'mobbex-for-woocommerce'),
        'label'   => __('Deshabilita los descuentos de woocommerce en el checkout de Mobbex.', 'mobbex-for-woocommerce'),
        'class'   => 'mbbx-into-advanced',
        'type'    => 'checkbox',
        'default' => 'no',
    ],

    'disable_template'=> [
        'title'   => __('Deshabilitar plantilla', 'mobbex-for-woocommerce'),
        'label'   => __('Deshabilitar plantilla para el mostrado de los métodos de pago.', 'mobbex-for-woocommerce'),
        'class'   => 'mbbx-into-advanced',
        'type'    => 'checkbox',
        'default' => 'no',
    ],

    'timeout' => [
        'title'             => __('Tiempo de vida Checkout', 'mobbex-for-woocommerce'),
        'description'       => __('Establecer tiempo de vida del Checkout en minutos', 'mobbex-for-woocommerce'),
        'class'             => 'mbbx-into-advanced',
        'type'              => 'number',
        'default'           => 5,
        'custom_attributes' => [
            'min'       => '1',
        ],
    ],

    'return_timeout' => [
        'title'             => __('Tiempo de redirección', 'mobbex-for-woocommerce'),
        'description'       => __('Establecer tiempo de espera en segundos para la redirección/cierre checkout', 'mobbex-for-woocommerce'),
        'class'             => 'mbbx-into-advanced',
        'type'              => 'number',
        'default'           => 5,
        'custom_attributes' => [
            'min'       => '1',
        ],
    ],

    'final_currency' => [
        'title'             => __('Convertir Moneda', 'mobbex-for-woocommerce'),
        'description'       => __('Permite convertir la moneda en la que se procesa el pago. (ARS, USD)', 'mobbex-for-woocommerce'),
        'class'             => 'mbbx-into-advanced',
        'type'              => 'text',
        'default'           => '',
    ],

    'process_webhook_retries' => [
        'title'   => __('Procesar reintentos de webhooks', 'mobbex-for-woocommerce'),
        'label'   => __('Permite procesar nuevamente un webhook si este se reenvía de forma automática (los reintentos manuales siempre se procesan).', 'mobbex-for-woocommerce'),
        'class'   => 'mbbx-into-advanced',
        'type'    => 'checkbox',
        'default' => 'no',
    ],

];
