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

    /** @var \Mobbex\WP\Checkout\Model\Helper */
    public $helper;

    /** @var \Mobbex\WP\Checkout\Model\Logger */
    public $logger;

    /**
     * Initializes the payment method type.
     */
    public function initialize()
    {
        $this->config = new Config();
        $this->helper = new Helper();
        $this->logger = new Logger();
    }

    /**
     * Returns if this payment method should be active. If false, the scripts will not be enqueued.
     *
     * @return boolean
     */
    public function is_active()
    {
        return $this->config->enabled === 'yes';
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     *
     * @return array
     */
    public function get_payment_method_script_handles()
    {
        $script_asset_path = plugin_dir_path(__FILE__) . '../assets/blocks/frontend/payment-method.asset.php';

        $script_asset = file_exists($script_asset_path)
            ? require $script_asset_path
            : array(
                'dependencies' => array(),
                'version'      => '1.2.0'
            );

        // The bundle consumes these globals as window.React, window.wp.element,
        // window.wp.htmlEntities, window.wc.wcBlocksRegistry, window.wc.wcSettings
        $dependencies = array_values(array_unique(array_merge(
            $script_asset['dependencies'],
            ['react', 'wp-element', 'wp-html-entities', 'wp-i18n', 'wc-blocks-registry', 'wc-settings']
        )));

        wp_register_script(
            'wc-mobbex-payments-blocks',
            plugins_url('../assets/blocks/frontend/payment-method.js', __FILE__),
            $dependencies,
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

        $data = [
            'title'                => $this->config->title,
            'description'          => $this->config->description,
            'supports'             => array_filter($gateway->supports, [$gateway, 'supports']),
            'checkout_banner'      => $this->config->checkout_banner,
            'payment_method_image' => $this->config->payment_method_image,
            'payment_methods'      => $this->config->payment_methods,
            'wallet'               => $this->config->wallet,
            'method_icon'          => $this->config->method_icon,
            'color'                => $this->config->color,
            'cards'                => [],
            'methods'              => [],
        ];

        // Create context chekout only if wallet or payment methods are active (see Init::load_payment_options)
        if (is_checkout() && ($this->config->payment_methods == 'yes' || $this->config->wallet == 'yes')) {
            try {
                $response = $this->helper->get_context_checkout();

                $data['cards']   = isset($response['wallet'])         ? $response['wallet']         : [];
                $data['methods'] = isset($response['paymentMethods']) ? $response['paymentMethods'] : [];

                $this->logger->log('debug', '[Mobbex Block] Payment options loaded', [
                    'payment_methods' => $this->config->payment_methods,
                    'wallet'          => $this->config->wallet,
                    'methods_count'   => count($data['methods']),
                    'cards_count'     => count($data['cards']),
                    'response_keys'   => is_array($response) ? array_keys($response) : gettype($response),
                ]);
            } catch (\Exception $e) {
                $this->logger->log('error', '[Mobbex Block] Error loading payment options', [
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $data;
    }
}
