<?php

namespace Mobbex\WP\Checkout\Observer;

class Checkout
{
    /** @var \Mobbex\WP\Checkout\Model\Config */
    public $config;

    /** @var \Mobbex\WP\Checkout\Model\Helper */
    public $helper;

    /** @var \Mobbex\WP\Checkout\Model\Logger */
    public $logger;

    public function __construct()
    {
        $this->config = new \Mobbex\WP\Checkout\Model\Config();
        $this->helper = new \Mobbex\WP\Checkout\Model\Helper();
        $this->logger = new \Mobbex\WP\Checkout\Model\Logger();
    }

    /**
     * Check that the Cart does not have products from different stores.
     * 
     * @param bool $valid
     * @param int $product_id
     * 
     * @return bool $valid
     */
    public function validate_cart_items($valid, $product_id)
    {
        $cart_items = !empty(WC()->cart->get_cart()) ? WC()->cart->get_cart() : [];

        // Get store from current product
        $product_store = \Mobbex\WP\Checkout\Model\Helper::get_store_from_product($product_id);

        // Get stores from cart items
        foreach ($cart_items as $item) {
            $item_store = \Mobbex\WP\Checkout\Model\Helper::get_store_from_product($item['product_id']);

            // If there are different stores in the cart items
            if ($product_store != $item_store) {
                wc_add_notice(__('The cart cannot have products from different sellers at the same time.', 'mobbex-for-woocommerce'), 'error');
                return false;
            }
        }

        return $valid;
    }

    /**
     * @deprecated
     * Add Mobbex extra data fields to woocommerce classic checkout.
     * 
     * @param array $fields
     * @return array $fields
     * 
     */
    public function add_checkout_fields($fields)
    {
        if (!$this->helper->should_add_own_dni_field())
            return $fields;

        $customer_id = WC()->customer ? WC()->customer->get_id() : null;

        $fields['billing_dni'] = [
            'type'        => 'text',
            'required'    => true,
            'clear'       => false,
            'label'       => 'DNI',
            'placeholder' => 'Ingrese su DNI',
            'default'     => WC()->session && WC()->session->get('mbbx_billing_dni') ? WC()->session->get('mbbx_billing_dni') : get_user_meta($customer_id, 'billing_dni', true),
        ];

        return $fields;
    }

    /**
     * Register the DNI field for Checkout Blocks.
     */
    public function register_blocks_checkout_fields()
    {
        if (!$this->helper->should_add_own_dni_field() || !function_exists('woocommerce_register_additional_checkout_field'))
            return;

        woocommerce_register_additional_checkout_field([
            'id'                => BLOCKS_DNI_FIELD_ID,
            'label'             => "DNI",
            'location'          => 'contact',
            'type'              => 'text',
            'required'          => true,
            'sanitize_callback' => function ($value) {
                return is_string($value) ? trim($value) : $value;
            },
            'validate_callback' => function (\WP_Error $errors, $field_key, $field_value) {
                if (!empty($field_value))
                    return;

                $errors->add('mobbex_dni_required', __('Complete el campo DNI', MOBBEX_WC_TEXT_DOMAIN));
            },
        ]);
    }

    /**
     * @deprecated
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
        $dni = $this->helper->get_dni_field_key();

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

        $dni = $this->helper->get_dni_field_key();

        // Exit if field is not configured
        if (!$dni)
            return;

        // Try to save by customer to show in future purchases
        if ($customer_id)
            update_user_meta($customer_id, 'billing_dni', $_POST[$dni]);

        // Save by order
        update_post_meta($order_id, '_billing_dni', $_POST[$dni]);
    }

    /**
     * Get default DNI value from session or customer meta.
     *
     * @param int|null $customer_id
     *
     * @return string
     */
    public function get_dni_default_value($customer_id = null)
    {
        if (WC()->session && WC()->session->get('mbbx_billing_dni'))
            return WC()->session->get('mbbx_billing_dni');

        return $customer_id ? get_user_meta($customer_id, 'billing_dni', true) : '';
    }
}
