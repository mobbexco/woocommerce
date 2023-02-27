<?php

namespace Mobbex\Controller;

final class Checkout
{
    /** @var \Mobbex\WP\Checkout\Includes\Config */
    public $config;

    /** @var \MobbexHelper */
    public $helper;

    /** @var \MobbexLogger */
    public $logger;

    public function __construct()
    {
        $this->config = new \Mobbex\WP\Checkout\Includes\Config();
        $this->helper = new \MobbexHelper();
        $this->logger = new \MobbexLogger();

        // Only if the plugin is enabled
        if ($this->helper->isReady()) {
            // Add additional checkout fields
            if ($this->config->own_dni == 'yes')
                add_filter('woocommerce_billing_fields', [$this, 'add_checkout_fields']);

            // Display fields on admin panel and try to save it
            add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'display_checkout_fields_data']);
            add_action('woocommerce_after_checkout_validation', [$this, 'validate_checkout_fields']);
            add_action('woocommerce_checkout_update_order_meta', [$this, 'save_checkout_fields']);
        }
    }

    /**
     * Add Mobbex extra data fields to woocommerce checkout.
     * 
     * @param array $fields
     * @return array $fields
     * 
     */
    public function add_checkout_fields($fields)
    {
        $cutomer_id = WC()->customer ? WC()->customer->get_id() : null;

        $fields['billing_dni'] = [
            'type'        => 'text',
            'required'    => true,
            'clear'       => false,
            'label'       => 'DNI',
            'placeholder' => 'Ingrese su DNI',
            'default'     => WC()->session->get('mbbx_billing_dni') ?: get_user_meta($cutomer_id, 'billing_dni', true),
        ];

        return $fields;
    }

    /**
     * Display the Mobbex extra fields in woocommerce checkout.
     * 
     * @param object $order
     * 
     */
    public function display_checkout_fields_data($order)
    {
        ?>
        <p>
            <strong>DNI:</strong>
            <?= get_post_meta($order->get_id(), '_billing_dni', true) ?: get_post_meta($order->get_id(), 'billing_dni', true) ?>
        </p>
        <?php
    }

    /**
     * Add DNI validation to woocommerce checkout.
     * 
     */
    public function validate_checkout_fields()
    {
        // Get dni field key
        $own_dni = $this->config->own_dni == 'yes' ? 'billing_dni' : false;
        $dni     = $this->config->custom_dni ? $this->config->custom_dni : $own_dni;

        // Exit if field is not configured
        if (!$dni)
            return;

        if (empty($_POST[$dni]))
            return wc_add_notice('Complete el campo DNI', 'error');

        WC()->session->set('mbbx_billing_dni', $_POST[$dni]);
    }

    /**
     * Save the data of DNI field in the woocommmerce checkout.
     * 
     * @param string $order_id
     * 
     */
    public function save_checkout_fields($order_id)
    {
        $customer_id = wc_get_order($order_id)->get_customer_id();

        // Get dni field key
        $own_dni = $this->config->own_dni == 'yes' ? 'billing_dni' : false;
        $dni     = $this->config->custom_dni ? $this->config->custom_dni : $own_dni;

        // Exit if field is not configured
        if (!$dni)
            return;

        // Try to save by customer to show in future purchases
        if ($customer_id)
            update_user_meta($customer_id, 'billing_dni', $_POST[$dni]);

        // Save by order
        update_post_meta($order_id, '_billing_dni', $_POST[$dni]);
    }
}