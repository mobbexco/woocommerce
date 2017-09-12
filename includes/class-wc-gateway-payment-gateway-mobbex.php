<?php
if( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly.

/**
 * WooCommerce Gateway Name.
 *
 * @class   WC_Gateway_Payment_Gateway_mobbex
 * @extends WC_Payment_Gateway
 * @version 1.0.0
 * @package WooCommerce Payment Gateway mobbex/Includes
 * @author  Sebastien Dumont
 */
class WC_Gateway_Payment_Gateway_mobbex extends WC_Payment_Gateway {

  /**
   * Constructor for the gateway.
   *
   * @access public
   * @return void
   */
  public function __construct() {
    $this->id                 = 'mobbex';
    $this->icon               = apply_filters( 'woocommerce_payment_gateway_mobbex_icon', plugins_url( '/assets/images/cards.png', dirname( __FILE__ ) ) );
    $this->has_fields         = false;
    $this->credit_fields      = false;

    $this->order_button_text  = __( 'Pay with Mobbex', 'woocommerce-mobbex-gateway' );

    $this->method_title       = __( 'Mobbex', 'woocommerce-mobbex-gateway' );
    $this->method_description = __( 'Take payments via Mobbex.', 'woocommerce-mobbex-gateway' );

    // TODO: Rename 'WC_Gateway_Payment_Gateway_mobbex' to match the name of this class.
    $this->notify_url         = WC()->api_request_url( 'WC_Gateway_Mobbex' );

    $this->return_url         = WC()->api_request_url( 'Return_URL_Mobbex' );

   
    // TODO: 
    $this->api_endpoint       = 'https://mobbex.com/';

    // TODO: Use only what the payment gateway supports.
    $this->supports           = array(
      'subscriptions',
      'products',
      'subscription_cancellation',
      'subscription_reactivation',
      'subscription_suspension',
      'subscription_amount_changes',
      'subscription_payment_method_change',
      'subscription_date_changes',
      'default_credit_card_form',
      'refunds',
      'pre-orders'
    );

    // TODO: Replace the transaction url here or use the function 'get_transaction_url' at the bottom.
    $this->view_transaction_url = 'https://www.domain.com';

    // Load the form fields.
    $this->init_form_fields();

    // Load the settings.
    $this->init_settings();

    // Get setting values.
    $this->enabled        = $this->get_option( 'enabled' );

    $this->title          = $this->get_option( 'title' );
    $this->description    = $this->get_option( 'description' );
    $this->instructions   = $this->get_option( 'instructions' );

    $this->api_key    = $this->get_option( 'api_key' );
    $this->access_token     = $this->get_option( 'access_token' );

    $this->debug          = $this->get_option( 'debug' );

    // Logs.
    if( $this->debug == 'yes' ) {
      if( class_exists( 'WC_Logger' ) ) {
        $this->log = new WC_Logger();
      }
      else {
        $this->log = $woocommerce->logger();
      }
    }

    $this->init_gateway_sdk();

    // Hooks.
   
      add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
      add_action( 'admin_notices', array( $this, 'checks' ) );

      add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
      add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
    

    add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );

    // Customer Emails.
    add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );

    add_action( 'woocommerce_api_wc_gateway_mobbex', array( $this, 'check_ipn_response' ) );

    add_action( 'woocommerce_api_return_url_mobbex', array( $this, 'return_url_mobbex' ) );
  


  }

  public function return_url_mobbex() {
      $this->log->add( $this->id, 'Return url executed.');

      $this->log->add( $this->id, 'Request:'.var_export($_REQUEST, true));
      $token_ipn = md5($this->access_token.'|'.$this->api_key);
      $order_id = '';
      if (!empty($_REQUEST['order_id'])) {
        $order_id = $_REQUEST['order_id'];
      }
      $status = 0;
      if (!empty($_REQUEST['status'])) {
        $status = $_REQUEST['status'];
      }
      if (!empty($_REQUEST['token_mobbex_ipn']) && !empty($order_id)) {
        
        if ($_REQUEST['token_mobbex_ipn'] == $token_ipn) {
            $order = new WC_Order($order_id);
            //echo $this->get_return_url($order);
            if ($status == 0) {
              wp_redirect($order->get_cancel_order_url_raw());
              exit;
            }
            $mobbex_webhook = get_post_meta($order->id, '_mobbex_webhook', false);
            $this->log->add( $this->id, '_mobbex_webhook get:'.var_export($mobbex_webhook , true));
            if ($mobbex_webhook == false) {
              wp_redirect($order->get_cancel_order_url_raw());
              exit;
            }

            wp_redirect($this->get_return_url($order));
            exit;
        }
      }
    //return false;
  }
public function check_ipn_response() {
    if( $this->debug == 'yes' ) {
        $this->log->add( $this->id, 'Mobbex IPN response: ' . print_r($_REQUEST, true ) . ' ' );
    }
    $autorized_status_cart = array(3, 200, 300, 301);
    $autorized_status_cash = array(2, 200);
    $completed = false;
    $payment_id = 0;
    if (isset($_REQUEST['data']['payment']['id'])) {
        $payment_id = $_REQUEST['data']['payment']['id'];
    } 
    $status_payment = 0;
    if (isset($_REQUEST['data']['payment']['status']['code'])) {
      $status_payment = $_REQUEST['data']['payment']['status']['code'];
    }

    $payment_type = '';
    if (isset($_REQUEST['data']['view']['type'])) {
      $payment_type = $_REQUEST['data']['view']['type'];
    }
   
    if ($payment_type == 'card'){
      if (in_array($status_payment, $autorized_status_cart) ) {
         $completed = true;
      }
    } else if ($payment_type == 'cash'){
        if (in_array($status_payment, $autorized_status_cash) ) {
            $completed = true;
        }
    }
   

    if (!empty($_REQUEST['token_mobbex_ipn']) && !empty($_REQUEST['order_id'])) {
        $token_ipn = md5($this->access_token.'|'.$this->api_key);
        if ($_REQUEST['token_mobbex_ipn'] == $token_ipn && $_REQUEST['type'] == 'checkout' && $completed) {

            $payment = array();
            $payment['id'] = $payment_id;
            $order = new WC_Order( $_REQUEST['order_id'] );
            $current_payment = get_post_meta($order->id, '_transaction_id', '');

            // Avoid duplicate order processing
            if ($current_payment == $payment['id']) {
            	return true;
            }
            // Payment complete.
            $order->payment_complete();

            // Store the transaction ID for WC 2.2 or later.
            add_post_meta( $order->id, '_transaction_id', $payment['id'], true );
            $this->log->add( $this->id, '_mobbex_webhook added');
            update_post_meta( $order->id, '_mobbex_webhook', $_REQUEST['data']);
            update_post_meta( $order->id, '_payment_method', $_REQUEST['data']['payment']['source']['name']);
            update_post_meta( $order->id, '_payment_method_title', $_REQUEST['data']['payment']['source']['name']);
            


              // Add order note.
            $order->add_order_note( sprintf( __( 'Mobbex payment approved (ID: %s)', 'woocommerce-mobbex-gateway' ), $payment['id'] ) );

            if( $this->debug == 'yes' ) {
                $this->log->add( $this->id, 'Mobbex payment approved (ID: ' . $payment['id'] . ')' );
            }

              // Reduce stock levels.
           // $order->reduce_order_stock();

            if( $this->debug == 'yes' ) {
                $this->log->add( $this->id, 'Stocked reduced.' );
            }

            // Remove items from cart.
            WC()->cart->empty_cart();

            if( $this->debug == 'yes' ) {
                $this->log->add( $this->id, 'Cart emptied.' );
            }

              // Return thank you page redirect.
            return true;
          
          }
      }

  } 
  /**
   * Init Payment Gateway SDK.
   *
   * @access protected
   * @return void
   */
  protected function init_gateway_sdk() {
    // TODO: Insert your gateway sdk script here and call it.
  }

  /**
   * Admin Panel Options
   * - Options for bits like 'title' and availability on a country-by-country basis
   *
   * @access public
   * @return void
   */
  public function admin_options() {
    include_once( WC_Gateway_Mobbex()->plugin_path() . '/includes/admin/views/admin-options.php' );
  }

  /**
   * Check if SSL is enabled and notify the user.
   *
   * @TODO:  Use only what you need.
   * @access public
   */
  public function checks() {
    if( $this->enabled == 'no' ) {
      return;
    }

    // PHP Version.
    if( version_compare( phpversion(), '5.3', '<' ) ) {
      echo '<div class="error"><p>' . sprintf( __( 'Mobbex Error: Mobbex requires PHP 5.3 and above. You are using version %s.', 'woocommerce-mobbex-gateway' ), phpversion() ) . '</p></div>';
    }

    // Check required fields.
    else if( !$this->api_key || !$this->access_token) {
      echo '<div class="error"><p>' . __( 'Mobbex Error: Please enter your Api Key and Access Token', 'woocommerce-mobbex-gateway' ) . '</p></div>';
    }

    // Show message if enabled and FORCE SSL is disabled and WordPress HTTPS plugin is not detected.
    /*else if( 'no' == get_option( 'woocommerce_force_ssl_checkout' ) && !class_exists( 'WordPressHTTPS' ) ) {
      echo '<div class="error"><p>' . sprintf( __( 'Mobbex is enabled, but the <a href="%s">force SSL option</a> is disabled; your checkout may not be secure! Please enable SSL and ensure your server has a valid SSL certificate - Gateway Name will only work in sandbox mode.', 'woocommerce-mobbex-gateway'), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) . '</p></div>';
    }
    */
  }

  /**
   * Check if this gateway is enabled.
   *
   * @access public
   */
  public function is_available() {
    if( $this->enabled == 'no' ) {
      return false;
    }

  
    if( !$this->api_key || !$this->access_token ) {
      return false;
    }

    return true;
  }

  /**
   * Initialise Gateway Settings Form Fields
   *
   * The standard gateway options have already been applied. 
   * Change the fields to match what the payment gateway your building requires.
   *
   * @access public
   */
  public function init_form_fields() {
    $this->form_fields = array(
      'enabled' => array(
        'title'       => __( 'Enable/Disable', 'woocommerce-mobbex-gateway' ),
        'label'       => __( 'Enable Mobbex', 'woocommerce-mobbex-gateway' ),
        'type'        => 'checkbox',
        'description' => '',
        'default'     => 'yes'
      ),
      'title' => array(
        'title'       => __( 'Title', 'woocommerce-mobbex-gateway' ),
        'type'        => 'text',
        'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-mobbex-gateway' ),
        'default'     => __( 'Mobbex', 'woocommerce-mobbex-gateway' ),
        'desc_tip'    => true
      ),
      'description' => array(
        'title'       => __( 'Description', 'woocommerce-mobbex-gateway' ),
        'type'        => 'text',
        'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-mobbex-gateway' ),
        'default'     => 'Pay with Mobbex.',
        'desc_tip'    => true
      ),
      'instructions' => array(
        'title'       => __( 'Instructions', 'woocommerce-mobbex-gateway' ),
        'type'        => 'textarea',
        'description' => __( 'Instructions that will be added to the thank you page and emails.', 'woocommerce-mobbex-gateway' ),
        'default'     => '',
        'desc_tip'    => true,
      ),
      'debug' => array(
        'title'       => __( 'Debug Log', 'woocommerce-mobbex-gateway' ),
        'type'        => 'checkbox',
        'label'       => __( 'Enable logging', 'woocommerce-mobbex-gateway' ),
        'default'     => 'no',
        'description' => sprintf( __( 'Log Mobbex events inside <code>%s</code>', 'woocommerce-mobbex-gateway' ), wc_get_log_file_path( $this->id ) )
      ),
     
      
      'api_key' => array(
        'title'       => __( 'API Key', 'woocommerce-mobbex-gateway' ),
        'type'        => 'text',
        'description' => __( 'Get your API Key from your Mobbex Name account.', 'woocommerce-mobbex-gateway' ),
        'default'     => '',
        'desc_tip'    => true
      ),
      'access_token' => array(
        'title'       => __( 'Access Token', 'woocommerce-mobbex-gateway' ),
        'type'        => 'text',
        'description' => __( 'Get your API keys from your Mobbex Name account.', 'woocommerce-mobbex-gateway' ),
        'default'     => '',
        'desc_tip'    => true
      ),
     
    );
  }

  /**
   * Output for the order received page.
   *
   * @access public
   * @return void
   */
  public function receipt_page( $order ) {
   
   /*
    if (!empty($_REQUEST['wcm_p_method']) && isset($_REQUEST['status'])) {
        if ($_REQUEST['status'] == 0 || $_REQUEST['status'] == 601) {
            echo '<p>' . __( 'Your order is cancelled.', 'woocommerce-mobbex-gateway' ) . '</p>';
        } else if ($_REQUEST['status'] == 200 || $_REQUEST['status'] == 300) {
            echo '<p>' . __( 'Thank you - your order is now processing.', 'woocommerce-mobbex-gateway' ) . '</p>';
        }

    }*/
    

    // TODO: 
  }

  /**
   * Payment form on checkout page.
   *
   * @TODO:  Use this function to add credit card 
   *         and custom fields on the checkout page.
   * @access public
   */
  public function payment_fields() {
    $description = $this->get_description();

    
    if( !empty( $description ) ) {
      echo wpautop( wptexturize( trim( $description ) ) );
    }

    // If credit fields are enabled, then the credit card fields are provided automatically.
    if( $this->credit_fields ) {
      $this->credit_card_form(
        array( 
          'fields_have_names' => false
        )
      );
    }

    // This includes your custom payment fields.
    include_once( WC_Gateway_Mobbex()->plugin_path() . '/includes/views/html-payment-fields.php' );

  }

  /**
   * Outputs scripts used for the payment gateway.
   *
   * @access public
   */
  public function payment_scripts() {
    if( !is_checkout() || !$this->is_available() ) {
      return;
    }

    $suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

    // TODO: Enqueue your wp_enqueue_script's here.

  }

  /**
   * Output for the order received page.
   *
   * @access public
   */
  public function thankyou_page( $order_id ) {
    if( !empty( $this->instructions ) ) {
      echo wpautop( wptexturize( wp_kses_post( $this->instructions ) ) );
    }

    $this->extra_details( $order_id );
  }

  /**
   * Add content to the WC emails.
   *
   * @access public
   * @param  WC_Order $order
   * @param  bool $sent_to_admin
   * @param  bool $plain_text
   */
  public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
    if( !$sent_to_admin && $this->id === $order->payment_method && $order->has_status( 'on-hold' ) ) {
      if( !empty( $this->instructions ) ) {
        echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
      }

      $this->extra_details( $order->id );
    }
  }

  /**
   * Gets the extra details you set here to be 
   * displayed on the 'Thank you' page.
   *
   * @access private
   */
  private function extra_details( $order_id = '' ) {
    echo '<h2>' . __( 'Extra Details', 'woocommerce-mobbex-gateway' ) . '</h2>' . PHP_EOL;

    // TODO: Place what ever instructions or details the payment gateway needs to display here.
  }

  /**
   * Process the payment and return the result.
   *
   * @TODO   You will need to add payment code inside.
   * @access public
   * @param  int $order_id
   * @return array
   */
  public function process_payment( $order_id ) {
    $order = new WC_Order( $order_id );

    
    // This array is used just for demo testing a successful transaction.
    $description = '';
    foreach ( $order->get_items() as $item ) {
        $description .= $item['name'].PHP_EOL;
    }
    $token_ipn = md5($this->access_token.'|'.$this->api_key);

    $return_url = add_query_arg('token_mobbex_ipn', $token_ipn, $this->return_url);
    $return_url = add_query_arg('order_id', $order_id, $return_url); 

    $webhook_url = add_query_arg('token_mobbex_ipn', $token_ipn, $this->notify_url); 
    $webhook_url = add_query_arg('order_id', $order_id, $webhook_url); 
    $response = wp_remote_post('https://mobbex.com/p/checkout/create', array(
        'method' => 'POST',
        'timeout' => 45,
        'redirection' => 5,
        'httpversion' => '1.0',
        'blocking' => true,
        'headers' => array(
              'postman-token' => '4533ef25-f802-5fcc-cc03-'.md5(time()),
              'cache-control' => 'no-cache',
              'content-type' => 'application/x-www-form-urlencoded',
              'x-access-token' =>  $this->access_token,
              'x-api-key' => $this->api_key
          ),
        'body' => array(
              'total' => $order->get_total(),
              'reference' => '#'.$order_id,
              'description' => $description,
              'webhook'     => $webhook_url,
              'return_url' => $return_url,
              'email'       => $order->billing_email
          ),
        'cookies' => array()
        )
    );

    if ( is_wp_error( $response ) ) {
        $this->log->add( $this->id, 'Mobbex error: ' . $response->get_error_message() . '' );
    } else {
      
      try {
        
        if( $this->debug == 'yes' ) {
          $this->log->add( $this->id, 'Mobbex payment response: ' . print_r($response['body'], true ) . ')' );
        }   
        $json_response = json_decode($response['body']);

        

        if (!empty($json_response->data->url) &&  $json_response->result) {
            return array(
              'result' => 'success',
              'redirect' => $json_response->data->url
            );
        }
       
      } catch (HttpException $ex) {
          wc_add_notice($ex->getMessage(), 'error' );
          if( $this->debug == 'yes' ) {
            $this->log->add( $this->id, 'Mobbex error: ' . $ex->getMessage() . '' );
          }
      }




    }
   
    
   
    return array(
        'result' => 'failure',
        'redirect' => ''
    );
    
  }

  /**
   * Process refunds.
   * WooCommerce 2.2 or later
   *
   * @access public
   * @param  int $order_id
   * @param  float $amount
   * @param  string $reason
   * @return bool|WP_Error
   */
  
  public function process_refund( $order_id, $amount = null, $reason = '' ) {

    $payment_id = get_post_meta( $order_id, '_transaction_id', true );
    $response = ''; // TODO: Use this variable to fetch a response from your payment gateway, if any.

    if( is_wp_error( $response ) ) {
      return $response;
    }

    if( 'APPROVED' == $refund['status'] ) {

      // Mark order as refunded
      $order->update_status( 'refunded', __( 'Payment refunded via Gateway Name.', 'woocommerce-mobbex-gateway' ) );

      $order->add_order_note( sprintf( __( 'Refunded %s - Refund ID: %s', 'woocommerce-mobbex-gateway' ), $refunded_cost, $refund_transaction_id ) );

      if( $this->debug == 'yes' ) {
        $this->log->add( $this->id, 'Gateway Name order #' . $order_id . ' refunded successfully!' );
      }
      return true;
    }
    else {

      $order->add_order_note( __( 'Error in refunding the order.', 'woocommerce-mobbex-gateway' ) );

      if( $this->debug == 'yes' ) {
        $this->log->add( $this->id, 'Error in refunding the order #' . $order_id . '. Gateway Name response: ' . print_r( $response, true ) );
      }

      return true;
    }

  }

  /**
   * Get the transaction URL.
   *
   * @TODO   Replace both 'view_transaction_url'\'s. 
   *         One for sandbox/testmode and one for live.
   * @param  WC_Order $order
   * @return string
   */
  public function get_transaction_url( $order ) {
    
      $this->view_transaction_url = 'https://mobbex.com/p/checkout/view/%s';
   

    return parent::get_transaction_url( $order );
  }

} // end class.

?>