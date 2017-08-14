<?php
/*
class WC_Yourpay_ViaBill extends WC_Yourpay_Instance {
    
    public function __construct() {
        global $woocommerce;

        $supports[] = "products";
        $supports[] = 'refunds';
			
	$this->id = 'yourpay-viabill';
	$this->title = 'Yourpay Viabill';
	$this->icon = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__ )) . '/cards.png';
	$this->has_fields = false;
        $this->supports = $supports;

	// Load the form fields.
	$this->init_form_fields();
			
	// Load the settings.
	$this->init_settings();
			
	// Define user set variables
	$this->enabled = $this->settings["enabled"];
	$this->yp_token = $this->settings["yp_token"];
        
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
			
    }
		public function admin_options()
		{
			echo '<h3>Viabill</h3>';
			echo '<table class="form-table">';
				$this->generate_settings_html();
			echo '</table>';
		}
    
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __( 'Enable', 'woocommerce' ),
                'type' => 'checkbox', 
                'label' => __( 'Enable ViaBill payment', 'woocommerce' ), 
                'default' => 'no'
            )
        );
    }
   
    
    public function filter_cardtypelock( )
    {
        return 'viabill';
    }
}
*/
