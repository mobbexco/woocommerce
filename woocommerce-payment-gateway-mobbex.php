<?php
/*
 * Plugin Name:       WooCommerce Mobbex Gateway
 * Plugin URI:        https://bitbucket.org/etruel/woocommerce-mobbex-gateway
 * Description:       A payment gateway created to help get payments by Mobbex with WooCommerce.
 * Version:           1.1
 * Author:            etruel, sniuk
 * Author URI:        https://etruel.com
 * Requires at least: 4.1
 * Tested up to:      4.8
 * Text Domain:       woocommerce-mobbex-gateway
 * Domain Path:       languages
 *
 */
if( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly.

/**
 * Required functions
 */
require_once('woo-includes/woo-functions.php');

if( !class_exists( 'WC_Gateway_Mobbex' ) ) {

  /**
   * WooCommerce {%Gateway Name%} main class.
   *
   * @TODO    Replace 'Gateway_Name' with the name of your payment gateway class.
   * @class   Gateway_Name
   * @version 1.0.0
   */
  final class WC_Gateway_Mobbex {

    /**
     * Gateway version.
     *
     * @access public
     * @var    string
     */
    static public $version = '1.1';

    /**
     * Instance of this class.
     *
     * @access protected
     * @access static
     * @var object
     */
    protected static $instance = null;

    /**
     * Slug
     *
     * @TODO   Rename the $gateway_slug to match the name of the payment gateway your building.
     * @access public
     * @var    string
     */
    static public $gateway_slug = 'payment_gateway_mobbex';

    /**
     * Text Domain
     *
     * @TODO   Rename the $text_domain to match the name of the payment gateway your building.
     * @access public
     * @var    string
     */
    static public $text_domain = 'woocommerce-mobbex-gateway';

    /**
     * The Gateway Name.
     *
     * @TODO   Rename the payment gateway name to the gateway your building.
     * @NOTE   Do not put WooCommerce in front of the name. It is already applied.
     * @access public
     * @var    string
     */
     static public $name = "Payment Gateway mobbex";

    /**
     * The Gateway URL.
     *
     * @TODO   Replace the url
     * @access public
     * @var    string
     */
     static public $web_url = "https://bitbucket.org/etruel/woocommerce-mobbex-gateway/";

    /**
     * The Gateway documentation URL.
     *
     * @TODO   Replace the url
     * @access public
     * @var    string
     */
     static public $doc_url = "https://bitbucket.org/etruel/woocommerce-mobbex-gateway/wiki/";

    /**
     * Return an instance of this class.
     *
     * @return object A single instance of this class.
     */
    public static function get_instance() {
      // If the single instance hasn't been set, set it now.
      if( null == self::$instance ) {
        self::$instance = new self;
        self::$instance->hooks();
      }

      return self::$instance;
    }

    /**
     * Throw error on object clone
     *
     * The whole idea of the singleton design pattern is that there is a single
     * object therefore, we don't want the object to be cloned.
     *
     * @since  1.0.0
     * @access public
     * @return void
     */
     public function __clone() {
       // Cloning instances of the class is forbidden
       _doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'woocommerce-mobbex-gateway' ), self::$version );
     }

    /**
     * Disable unserializing of the class
     *
     * @since  1.0.0
     * @access public
     * @return void
     */
     public function __wakeup() {
       // Unserializing instances of the class is forbidden
       _doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'woocommerce-mobbex-gateway' ), self::$version );
     }

    /**
     * Initialize the plugin public actions.
     *
     * @access public
     */
    public static function hooks() {
        // Hooks.
        add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array(__CLASS__, 'action_links' ) );
        add_filter( 'plugin_row_meta', array(__CLASS__, 'plugin_row_meta' ), 10, 2 );
        add_action( 'init', array(__CLASS__, 'load_plugin_textdomain' ) );

        // Is WooCommerce activated?
        if( !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
          add_action('admin_notices', array(__CLASS__, 'woocommerce_missing_notice' ) );
          return false;
        }
        else{
          // Check we have the minimum version of WooCommerce required before loading the gateway.
          if( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.2', '>=' ) ) {
            if( class_exists( 'WC_Payment_Gateway' ) ) {

              self::includes();

              add_filter( 'woocommerce_payment_gateways', array( __CLASS__, 'add_gateway' ) );
              add_filter( 'woocommerce_currencies', array(__CLASS__, 'add_currency' ) );
              add_filter( 'woocommerce_currency_symbol', array( __CLASS__, 'add_currency_symbol' ), 10, 2 );
            }
          }
          else {
            add_action( 'admin_notices', array( __CLASS__, 'upgrade_notice' ) );
            return false;
          }
        }

    }
    

    /**
     * Plugin action links.
     *
     * @access public
     * @param  mixed $links
     * @return void
     */
    public static function action_links( $links ) {
       if( current_user_can( 'manage_woocommerce' ) ) {
         $plugin_links = array(
           '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=mobbex') . '">' . __( 'Payment Settings', 'woocommerce-mobbex-gateway' ) . '</a>',
         );
         return array_merge( $plugin_links, $links );
       }

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
     static public function plugin_row_meta( $input, $file ) {
       if( plugin_basename( __FILE__ ) !== $file ) {
         return $input;
       }

       $links = array(
         '<a href="' . esc_url( self::$doc_url ) . '">' . __( 'Documentation', 'woocommerce-mobbex-gateway' ) . '</a>',
       );

       $input = array_merge( $input, $links );

       return $input;
     }

    /**
     * Load Localisation files.
     *
     * Note: the first-loaded translation file overrides any 
     * following ones if the same translation is present.
     *
     * @access public
     * @return void
     */
    public static function load_plugin_textdomain() {
      // Set filter for plugin's languages directory
      $lang_dir = dirname( plugin_basename( __FILE__ ) ) . '/languages/';
      $lang_dir = apply_filters( 'woocommerce_' . self::$gateway_slug . '_languages_directory', $lang_dir );

      // Traditional WordPress plugin locale filter
      $locale = apply_filters( 'plugin_locale',  get_locale(), self::$text_domain );
      $mofile = sprintf( '%1$s-%2$s.mo', self::$text_domain, $locale );
      // Setup paths to current locale file
      $mofile_local  = $lang_dir . $mofile;
      $mofile_global = WP_LANG_DIR . '/' . self::$text_domain . '/' . $mofile;

      if( file_exists( $mofile_global ) ) {
        // Look in global /wp-content/languages/plugin-name/ folder
        load_textdomain( self::$text_domain, $mofile_global );
      }
      else if( file_exists( $mofile_local ) ) {
        // Look in local /wp-content/plugins/plugin-name/languages/ folder
        load_textdomain( self::$text_domain, $mofile_local );
      }
      else {
        // Load the default language files
        load_plugin_textdomain( self::$text_domain, false, $lang_dir );
      }
    }

    /**
     * Include files.
     *
     * @access private
     * @return void
     */
    private static function includes() {
      include_once( 'includes/class-wc-gateway-' . str_replace( '_', '-', self::$gateway_slug ) . '.php' );

      // This supports the plugin extensions 'WooCommerce Subscriptions' and 'WooCommerce Pre-orders'.
      //if( class_exists( 'WC_Subscriptions_Order' ) || class_exists( 'WC_Pre_Orders_Order' ) ) {
        //include_once( 'includes/class-wc-gateway-' . str_replace( '_', '-', self::$gateway_slug ) . '-add-ons.php' );
      //}
    }

    /**
     * This filters the gateway to only supported countries.
     *
     * @TODO   List the country codes the payment gateway your building supports.
     * @access public
     */
    public static  function gateway_country_base() {
      return apply_filters( 'woocommerce_gateway_country_base', array( 'US', 'UK', 'FR' ) );
    }

    /**
     * Add the gateway.
     *
     * @access public
     * @param  array $methods WooCommerce payment methods.
     * @return array WooCommerce {%Gateway Name%} gateway.
     */
    public static function add_gateway( $methods ) {
      // This checks if the gateway is supported for your country.
      

        //if( class_exists( 'WC_Subscriptions_Order' ) ) {
          //$methods[] = 'WC_Gateway_' . str_replace( ' ', '_', self::$name ) . '_Subscription';
       // }
       // else {
          $methods[] = 'WC_Gateway_' . str_replace( ' ', '_', self::$name );
        //}

      

      return $methods;
    }

    /**
     * Add the currency.
     *
     * @TODO   Use this function only if you are adding a new currency. 
     *         e.g. STR for Stellar
     * @access public
     * @return array
     */
    public static function add_currency( $currencies ) {
      //$currencies['ABC'] = __( 'Currency Name', 'woocommerce-mobbex-gateway' );
      return $currencies;
    }

    /**
     * Add the currency symbol.
     *
     * @TODO   Use this function only when using the function 'add_currency'. 
     *         If currency has no symbol, leave $currency_symbol blank.
     * @access public
     * @return string
     */
    public static function add_currency_symbol( $currency_symbol, $currency ) {
      switch( $currency ) {
        case 'ABC':
          $currency_symbol = '$';
        break;
      }
      return $currency_symbol;
    }

    /**
     * WooCommerce Fallback Notice.
     *
     * @access public
     * @return string
     */
    public static function woocommerce_missing_notice() {
      echo '<div class="error woocommerce-message wc-connect"><p>' . sprintf( __( 'Sorry, <strong>WooCommerce %s</strong> requires WooCommerce to be installed and activated first. Please install <a href="%s">WooCommerce</a> first.', self::$text_domain), self::$name, admin_url('plugin-install.php?tab=search&type=term&s=WooCommerce' ) ) . '</p></div>';
    }

    /**
     * WooCommerce Payment Gateway Upgrade Notice.
     *
     * @access public
     * @return string
     */
    public static function upgrade_notice() {
      echo '<div class="updated woocommerce-message wc-connect"><p>' . sprintf( __( 'WooCommerce %s depends on version 2.2 and up of WooCommerce for this gateway to work! Please upgrade before activating.', 'payment-gateway-mobbex' ), self::$name ) . '</p></div>';
    }

    /** Helper functions ******************************************************/

    /**
     * Get the plugin url.
     *
     * @access public
     * @return string
     */
    public static function plugin_url() {
      return untrailingslashit( plugins_url( '/', __FILE__ ) );
    }

    /**
     * Get the plugin path.
     *
     * @access public
     * @return string
     */
    public static function plugin_path() {
      return untrailingslashit( plugin_dir_path( __FILE__ ) );
    }

  } // end if class

   add_action( 'plugins_loaded', array( 'WC_Gateway_Mobbex', 'get_instance' ), 0 );

} // end if class exists.

/**
 * Returns the main instance of WC_Gateway_Mobbex to prevent the need to use globals.
 *
 * @return WooCommerce Gateway Name
 */
function WC_Gateway_Mobbex() {
	return WC_Gateway_Mobbex::get_instance();
}

?>