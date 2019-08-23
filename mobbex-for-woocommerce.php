<?php

/*

Plugin Name:  Mobbex for Woocommerce
Description:  A small plugin that provides Woocommerce <-> Mobbex integration.
Version:      2.0.3

 */

const MOBBEX_CHECKOUT = 'https://mobbex.com/p/checkout/create';

class MobbexGateway
{

    /**
     * Errors Array
     */
    static $errors = [];

    /**
     * Mobbex URL.
     */
    public static $site_url = "https://www.mobbex.com";

    /**
     * Gateway documentation URL.
     */
    public static $doc_url = "https://mobbex.dev";

    /**
     * Github URLs
     */
    public static $github_url = "https://github.com/mobbexco/woocommerce";
    public static $github_issues_url = "https://github.com/mobbexco/woocommerce/issues";

    public function init()
    {

        MobbexGateway::load_textdomain();

        if (!class_exists('WooCommerce')) {
            MobbexGateway::$errors[] = __('WooCommerce needs to be installed and activated.', 'mobbex-for-woocommerce');
        }

        if (!defined('WP_DEBUG') && !WP_DEBUG && !is_ssl()) {
            MobbexGateway::$errors[] = __('Your site needs to be served via HTTPS to comunicate securely with Mobbex.', 'mobbex-for-woocommerce');
        }

        if (count(MobbexGateway::$errors)) {

            foreach (MobbexGateway::$errors as $error) {
                MobbexGateway::notice('error', $error);
            }

            return;
        }

        MobbexGateway::load_gateway();
        MobbexGateway::add_gateway();

        // Add some useful things
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_action_links']);
        add_filter('plugin_row_meta', [$this, 'plugin_row_meta'], 10, 2);
    }

    public function add_action_links($links)
    {
        error_log(print_r($links, true));
        $plugin_links = [
            '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=mobbex') . '">' . __('Settings', 'mobbex-for-woocommerce') . '</a>',
        ];

        $links = array_merge($plugin_links, $links);

        error_log(print_r($links, true));

        return $links;
    }

    /**
     * Plugin row meta links
     *
     * @access public
     * @param  array $input already defined meta links
     * @param  string $file plugin file path and name being processed
     * @return array $input
     */
    public function plugin_row_meta($input, $file)
    {
        $links = [
            '<a href="' . esc_url(MobbexGateway::$site_url) . '" target="_blank">' . __('Website', 'woocommerce-mobbex-gateway') . '</a>',
            '<a href="' . esc_url(MobbexGateway::$doc_url) . '" target="_blank">' . __('Documentation', 'woocommerce-mobbex-gateway') . '</a>',
            '<a href="' . esc_url(MobbexGateway::$github_url) . '" target="_blank">' . __('Contribute', 'woocommerce-mobbex-gateway') . '</a>',
            '<a href="' . esc_url(MobbexGateway::$github_issues_url) . '" target="_blank">' . __('Report Issues', 'woocommerce-mobbex-gateway') . '</a>',
        ];

        return array_merge($input, $links);
    }

    public static function load_textdomain()
    {

        load_plugin_textdomain('mobbex-for-woocommerce', false, dirname(plugin_basename(__FILE__)) . '/languages/');

    }

    public static function load_gateway()
    {

        require_once plugin_dir_path(__FILE__) . 'gateway.php';

    }

    public static function add_gateway()
    {

        add_filter('woocommerce_payment_gateways', function ($methods) {

            $methods[] = 'WC_Gateway_Mobbex';
            return $methods;

        });

    }

    public static function notice($type, $msg)
    {

        add_action('admin_notices', function () use ($type, $msg) {

            $class = esc_attr("notice notice-$type");
            $msg = esc_html($msg);

            ob_start();?>

            <div class="<?=$class?>">
                <h2>Mobbex for Woocommerce</h2>
                <p><?=$msg?></p>
            </div>

            <?php echo ob_get_clean();

        });

    }

}

$mobbexGateway = new MobbexGateway;
add_action('plugins_loaded', [ & $mobbexGateway, 'init']);