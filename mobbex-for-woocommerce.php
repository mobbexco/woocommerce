<?php 

/*

    Plugin Name:  Mobbex for Woocommerce
    Description:  A small plugin that provides Woocommerce <-> Mobbex integration.
    Version:      1.0.1

 */

const MOBBEX_CHECKOUT = 'https://mobbex.com/p/checkout/create';

class Mobbex
{

    static $errors = [];

    static function init()
    {

        self::load_textdomain();

        if (!class_exists('WooCommerce'))
            self::$errors[] = __('WooCommerce needs to be installed and activated.', 'mobbex-for-woocommerce');

        if (!is_ssl())
            self::$errors[] = __('Your site needs to be served via HTTPS to comunicate securely with Mobbex.', 'mobbex-for-woocommerce');

        if (count(self::$errors)) {

            foreach (self::$errors as $error) self::notice('error', $error);
            return;

        }

        self::load_gateway();
        self::add_gateway();

    }

    static function load_textdomain() {

        load_plugin_textdomain( 'mobbex-for-woocommerce' , false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

    }

    static function load_gateway()
    {

        require_once plugin_dir_path(__FILE__) . 'gateway.php';

    }

    static function add_gateway()
    {

        add_filter('woocommerce_payment_gateways', function ($methods) {

            $methods[] = 'WC_Gateway_Mobbex';
            return $methods;

        });

    }

    static function notice($type, $msg)
    {

        add_action('admin_notices', function () use ($type, $msg) {

            $class = esc_attr("notice notice-$type");
            $msg = esc_html($msg);

            ob_start(); ?>

            <div class="<?= $class ?>">
                <h2>Mobbex for Woocommerce</h2>
                <p><?= $msg ?></p>
            </div>

            <?php echo ob_get_clean();

        });

    }

}

add_action('plugins_loaded', ['Mobbex', 'init']);