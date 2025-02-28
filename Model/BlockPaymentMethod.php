<?php

namespace Mobbex\WP\Checkout\Model;

/**
 * Mobbex Payments Blocks integration
 *
 * @since 3.15
 */
final class BlockPaymentMethod extends \Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType
{
    /**
     * Payment method name.
     * @var string
     */
    protected $name = 'mobbex';

    /** 
     * Config model instance.
     * @var \Mobbex\WP\Checkout\Model\Config 
     */
    public $config;

    /**
     * Initializes the payment method type.
     */
    public function initialize()
    {
        $this->config = new Config();
    }

    /**
     * Returns if this payment method should be active. If false, the scripts will not be enqueued.
     *
     * @return boolean
     */
    public function is_active()
    {
        return $this->config->enabled;
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     *
     * @return array
     */
    public function get_payment_method_script_handles()
    {
        $script_asset      = file_exists(plugin_dir_url(__FILE__) . '../assets/blocks/frontend/blocks.asset.php')
            ? require($script_asset_path)
            : array(
                'dependencies' => array(),
                'version'      => '1.2.0'
            );

        wp_register_script(
            'wc-mobbex-payments-blocks',
            plugin_dir_url(__FILE__) . '../assets/blocks/frontend/blocks.js',
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );

        return ['wc-mobbex-payments-blocks'];
    }

    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script.
     *
     * @return array
     */
    public function get_payment_method_data()
    {
        $gateway = new \WC_Gateway_Mobbex();

        return [
            'title'                => $this->config->title,
            'description'          => $this->config->description,
            'supports'             => array_filter($gateway->supports, [$gateway, 'supports']),
            'checkout_banner'      => $this->config->checkout_banner,
            'payment_method_image' => $this->config->payment_method_image,
        ];
    }
}
