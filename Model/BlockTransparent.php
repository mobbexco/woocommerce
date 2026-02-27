<?php

namespace Mobbex\WP\Checkout\Model;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Mobbex Transparent Payment Method for Blocks integration
 */
final class BlockTransparent extends AbstractPaymentMethodType
{
    /**
     * Payment method name.
     * @var string
     */
    protected $name = 'mobbex_transparent';

    /** @var \Mobbex\WP\Checkout\Model\Config */
    public $config;

    /** @var \Mobbex\WP\Checkout\Model\Logger */
    public $logger;

    /**
     * Initializes the payment method type.
     */
    public function initialize()
    {
        $this->config = new \Mobbex\WP\Checkout\Model\Config();
        $this->logger = new \Mobbex\WP\Checkout\Model\Logger();

        $this->logger->log('debug', '[Mobbex Transparent Block] Initialized');
    }

    /**
     * Returns if this payment method should be active.
     *
     * @return boolean
     */
    public function is_active()
    {
        return $this->config->transparent == 'yes';
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     *
     * @return array
     */
    public function get_payment_method_script_handles()
    {
        $script_asset_path = plugin_dir_path(__FILE__) . '../assets/blocks/frontend/transparent.asset.php';

        $script_asset = file_exists($script_asset_path)
            ? require $script_asset_path
            : [
                'dependencies' => [],
                'version'      => '1.2.0',
            ];

        wp_register_script(
            'wc-mobbex-transparent-blocks',
            plugins_url('../assets/blocks/frontend/transparent.js', __FILE__),
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );

        return ['wc-mobbex-transparent-blocks'];
    }


    /**
     * Returns an array of key=>value pairs of data made available to the transparent script.
     *
     * @return array
     */
    public function get_payment_method_data()
    {
        if (!is_checkout())
            return [];

        try {
            $gateway = new \WC_Gateway_Mobbex_Transparent();

            if (!$gateway) {
                $this->logger->log('error', '[Mobbex Transparent Block] Could not instantiate gateway');
                return [];
            }

            $intent_token = method_exists($gateway, 'get_intent_token') ? $gateway->get_intent_token() : '';

            if (empty($intent_token)) {
                $this->logger->log('warning', '[Mobbex Transparent Block] Could not retrieve intent token');
            }

            // TO DO: add sourcesUrl to get sources logo
            // $order        = wc_get_order($order_id);
            // $products_ids = $gateway->helper::get_product_ids($order);;


            $data = [
                'supports'     => ['products'],
                'intent_token' => $intent_token ?: '',
                'description'  => $gateway->description ?? '',
                'title'        => $gateway->config->transparent_title,
                'i18n'         => [
                    'cvv_label'                => __('CVV', 'mobbex-for-woocommerce'),
                    'card_dni_label'           => __('DNI', 'mobbex-for-woocommerce'),
                    'installments_label'       => __('Cuotas', 'mobbex-for-woocommerce'),
                    'expiration_label'         => __('Vencimiento', 'mobbex-for-woocommerce'),
                    'card_number_label'        => __('Número de tarjeta', 'mobbex-for-woocommerce'),
                    'installments_loading'     => __('Cargando cuotas...', 'mobbex-for-woocommerce'),
                    'installments_placeholder' => __('Seleccionar cuotas', 'mobbex-for-woocommerce'),
                    'processing'               => __('Procesando pago...', 'mobbex-for-woocommerce'),
                    'card_name_label'          => __('Nombre en la tarjeta', 'mobbex-for-woocommerce'),
                ]
            ];

            $this->logger->log('debug', '[Mobbex Transparent Block] Payment method data prepared', [
                'has_intent_token' => !empty($intent_token)
            ]);
            return $data;
        } catch (\Exception $e) {
            $this->logger->log('error', '[Mobbex Transparent Block] Error preparing payment method data', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
}
