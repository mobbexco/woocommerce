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
    ],

    'access-token' => [
        'title'       => __('Access Token', 'mobbex-for-woocommerce'),
        'description' => __('Your Mobbex access token.', 'mobbex-for-woocommerce'),
        'type'        => 'text',
    ],

    'test_mode' => [
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

    'description' => [
        'title'       => __('Description', 'mobbex-for-woocommerce'),
        'description' => __('This description will be shown on user checkout.', 'mobbex-for-woocommerce'),
        'type'        => 'textarea',
        'default'     => '',
        'class'       => 'mbbx-into-appearance',
    ],

    'checkout_theme' => [
        'title'       => __('Checkout Theme', 'mobbex-for-woocommerce'),
        'description' => __('You can customize your Checkout Theme from here.', 'mobbex-for-woocommerce'),
        'type'        => 'select',
        'options'     => [
            'light' => __('Light Theme', 'mobbex-for-woocommerce'),
            'dark'  => __('Dark Theme', 'mobbex-for-woocommerce'),
        ],
        'default'     => 'light',
        'class'       => 'mbbx-into-appearance',
    ],

    'checkout_title' => [
        'title'       => __('Checkout Title', 'mobbex-for-woocommerce'),
        'description' => __('You can customize your Checkout Title from here.', 'mobbex-for-woocommerce'),
        'type'        => 'text',
        'default'     => '',
        'class'       => 'mbbx-into-appearance',
    ],

    'checkout_logo'  => [
        'title'       => __('Checkout Logo URL', 'mobbex-for-woocommerce'),
        'description' => __('You can customize your Checkout Logo from here. The logo URL must be HTTPS and must be only set if required. If not set the Logo set on Mobbex will be used. Dimensions: 250x250 pixels', 'mobbex-for-woocommerce'),
        'type'        => 'text',
        'default'     => '',
        'class'       => 'mbbx-into-appearance',
    ],

    'checkout_background_color' => [
        'title'       => __('Checkout Background Color', 'mobbex-for-woocommerce'),
        'description' => __('You can customize your Checkout Background Color from here.', 'mobbex-for-woocommerce'),
        'type'        => 'text',
        'default'     => '#ECF2F6',
        'class'       => 'colorpick mbbx-into-appearance',
    ],

    'checkout_primary_color' => [
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

    'financial_widget_theme' => [
        'title'       => __('Financial Widget Theme', 'mobbex-for-woocommerce'),
        'description' => __('You can customize your Financial Widget Theme from here.', 'mobbex-for-woocommerce'),
        'type'        => 'select',
        'options'     => [
            'light' => __('Light Theme', 'mobbex-for-woocommerce'),
            'dark'  => __('Dark Theme', 'mobbex-for-woocommerce'),
        ],
        'default'     => 'light',
        'class'       => 'mbbx-into-appearance',
    ],

    'financial_widget_button_color' => [
        'title'       => __('Financial Widget Button Color', 'mobbex-for-woocommerce'),
        'description' => __('You can customize your financial widget button color from here.', 'mobbex-for-woocommerce'),
        'type'        => 'text',
        'default'     => null,
        'class'       => 'colorpick mbbx-into-appearance',
    ],

    'financial_widget_button_font_color' => [
        'title'       => __('Financial Widget Button Font Color', 'mobbex-for-woocommerce'),
        'description' => __('You can customize your financial widget button font color from here.', 'mobbex-for-woocommerce'),
        'type'        => 'text',
        'default'     => null,
        'class'       => 'colorpick mbbx-into-appearance',
    ],

    'financial_widget_button_font_size' => [
        'title'       => __('Financial Widget Button Font Size', 'mobbex-for-woocommerce'),
        'description' => __('You can customize your financial widget button font size from here.', 'mobbex-for-woocommerce'),
        'type'        => 'text',
        'default'     => null,
        'class'       => 'mbbx-into-appearance',
    ],

    'financial_widget_button_padding' => [
        'title'       => __('Financial Widget Button Padding', 'mobbex-for-woocommerce'),
        'description' => __('You can customize your financial widget button padding from here.', 'mobbex-for-woocommerce'),
        'type'        => 'text',
        'default'     => null,
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

    '2_step_processing_mail' => [
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

    'debug_mode' => [
        'title'   => __('Modo Debug', 'mobbex-for-woocommerce'),
        'label'   => __('Activar Modo Debug.', 'mobbex-for-woocommerce'),
        'class'   => 'mbbx-into-advanced',
        'type'    => 'checkbox',
        'default' => 'no',
    ],

    'unified_mode' => [
        'title'   => __('Modo unificado', 'mobbex-for-woocommerce'),
        'label'   => __('Deshabilita la subdivisión de los métodos de pago en la página de finalización de la compra. Las opciones se verán en el checkout.', 'mobbex-for-woocommerce'),
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

    'use_webhook_api' => [
        'title'   => __('Use new WebHook API', 'mobbex-for-woocommerce'),
        'type'    => 'checkbox',
        'label'   => __('Use the WebHook by API instead of old Controller. Permalinks must be Active to use it Safely', 'mobbex-for-woocommerce'),
        'default' => 'no',
        'class'   => 'mbbx-into-advanced',
    ],
];