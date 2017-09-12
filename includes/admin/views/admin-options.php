<h3><?php _e( 'Mobbex', 'woocommerce-mobbex-gateway' ); ?></h3>

<div class="gateway-banner updated">
  <img src="<?php echo WC_Gateway_Mobbex()->plugin_url() . '/assets/images/logo.png'; ?>" />
  <p class="main"><strong><?php _e( 'Getting started', 'woocommerce-mobbex-gateway' ); ?></strong></p>
  <p><?php _e( 'A payment gateway description can be placed here.', 'woocommerce-mobbex-gateway' ); ?></p>

  <p class="main"><strong><?php _e( 'Mobbex Status', 'woocommerce-mobbex-gateway' ); ?></strong></p>
  <ul>
    <li><?php echo __( 'Debug Enabled?', 'woocommerce-mobbex-gateway' ) . ' <strong>' . $this->debug . '</strong>'; ?></li>
  </ul>

  <?php if( empty( $this->api_key ) ) { ?>
  <p><a href="http://www.mobbex.com/" target="_blank" class="button button-primary"><?php _e( 'Get your Api Key', 'woocommerce-mobbex-gateway' ); ?></a> <a href="http://www.mobbex.com/" target="_blank" class="button"><?php _e( 'Learn more', 'woocommerce-mobbex-gateway' ); ?></a></p>
  <?php } ?>
</div>

<table class="form-table">
  <?php $this->generate_settings_html(); ?>
  <script type="text/javascript">
 
  </script>
</table>
