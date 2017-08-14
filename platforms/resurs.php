<?php

class WC_Yourpay_Resurs extends WC_Yourpay_Instance {
    
    public function __construct() {
        global $woocommerce;

        $supports[] = "products";
        $supports[] = 'refunds';
			
	$this->id = 'yourpay-resurs';
	$this->title = 'Resursbank Finansering';
	$this->icon = 'https://payments.yourpay.se/img/resursbank_logo.png';
	$this->has_fields = false;
        $this->supports = $supports;
        $this->description = "Payment Integration of Resurs Bank.";

	// Load the form fields.
	$this->init_form_fields();
			
	// Load the settings.
	$this->init_settings();
			
	// Define user set variables
	$this->enabled  = $this->settings["enabled"];
	
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_receipt_yourpay_resurs', array($this, 'receipt_page'));
			
    }
		public function admin_options()
		{
			echo '<h3>Resurs Bank</h3>';
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
                'label' => __( 'Enable Resurs payment', 'woocommerce' ), 
                'default' => 'no'
            )
        );
    }
    function process_payment($order)
    {
        global $woocommerce;
        $orderdata = new WC_Order((int)$order);
        $url = "https://payments.yourpay.se/betalingsvindue.php?method=resurs";
        $merchantdata = $this->_GetMerchantData(get_option('yourpay_token',array()));
        $callbackurl = urlencode(add_query_arg('wooorderid', $order, add_query_arg ('wc-api', 'wc_yourpay2_0', $this->get_return_url( $orderdata ))));
        $orderTotal = ($orderdata->order_total * 100);
        $currency = $this->get_iso_code(get_option('woocommerce_currency'));
        $cartid = $orderdata->id;
        $customerName = $orderdata->billing_last_name . "," . $orderdata->billing_first_name;
        $version = 207;
        $merchantid = $merchantdata->merchantid;
        $time = time();
        
        $checksum = sha1($cartid.$version.$merchantid."0".$orderTotal.$currency.$time);
        
        $urlparts["accepturl"]   = $this->get_return_url($orderdata);
        $urlparts["callbackurl"] = $callbackurl;
        $urlparts["version"]     = $version;
        $urlparts["ShopPlatform"] = "woocommerce";
        $urlparts["split"] = 0;
        $urlparts["amount"] = $orderTotal;
        $urlparts["CurrencyCode"] = $currency;
        $urlparts["time"] = $time;
        $urlparts["cartid"] = $cartid;
        $urlparts["customername"] = $customerName;
        $urlparts["designurl"] = time();
        $urlparts["checksum"] = $checksum;
        $urlparts["merchantid"] = $merchantid;
        $urlparts["customer_email"] = $orderdata->billing_email;
        $urlparts["customer_phone"] = $orderdata->billing_phone;
        $urlparts["yourpay_resurs_state"] = $_POST['resurs_text'];

        $url .= "&MerchantNumber=".$merchantdata->prod_merchantid."&";
        foreach($urlparts as $key=>$value)
            $url .= "$key=$value&";
        
        $customerdata = array("billing_first_name", "billing_last_name", "billing_company", "billing_address_1", "billing_address_2", "billing_city", 
            "billing_state", "billing_postcode", "billing_country","shipping_first_name", "shipping_last_name", "shipping_company", "shipping_address_1", 
            "shipping_address_2", "shipping_city", "shipping_state", "shipping_postcode", "shipping_country");
        foreach($customerdata as $value) {$url .= $this->getPersonOrder($orderdata,$value);}
        $order_item = $orderdata->get_items(); $url .= "total_products=".count($order_item)."&"; $i = 1;
        foreach($order_item as $product ) {
            $product_data = wc_get_product($product["product_id"]);
            $url .= "product_".$i."_id=" . $product["product_id"]."&";
            $url .= "product_".$i."_name=" . $product["name"]."&";
            $url .= "product_".$i."_qty=" . $product["qty"]."&";
            $url .= "product_".$i."_total=" . $product_data->get_price_excluding_tax()."&";
            $url .= "product_".$i."_tax=" . ($product_data->get_price()-$product_data->get_price_excluding_tax())."&"; $i++;
        }
                    
        $orderdata = new WC_Order((int)$order);
        foreach($orderdata as $key=>$value)
            $url .= $this->generateurl($key,$value);
        foreach($orderdata->post as $key=>$value)
            $url .= $this->generateurl($key,$value);
        
	return array(
            'result' 	=> 'success',
            'redirect'	=> $url
        );
    }
    function receipt_page( $order )
    {
        echo "Yes!";
    }    
    function payment_fields()
    {
        global $woocommerce;  
        $orderTotal = number_format($woocommerce->cart->total,2,".","");
    
        $methods = $this->_GetResursPaymentMethods($this->_GetToken());
        
    echo '<div class="yourpay_resursbank">';
    
    foreach($methods as $value) {
        if(!isset($value->id))
            continue;
        echo '<input type="radio" name="resurs_text" class="resurs_text" value="'.$value->id.'"> '.$value->descriptor.' - <a target="_BLANK" href="'.$value->legals[2]->url.$orderTotal.'">Prisinformation</a> ';
        echo '<br />';
    }
    echo '
    </div>';    
    echo "<script>jQuery(function() {jQuery('.resurs_text').change(function(){jQuery(\"#yourpay_resurs_state\").val(jQuery(this).val());});});</script>";
    }

}
add_filter( 'woocommerce_single_product_summary', 'bbloomer_price_prefix_suffix', 100, 2 );
 
function bbloomer_price_prefix_suffix($product ){
    global $post, $woocommerce;
    $product = new WC_Product($post->ID); 
    /*
    $token = get_option('yourpay_token',array());
    $YourpayPayments = new WC_Yourpay2_0();
    $methods = $YourpayPayments->_GetResursPaymentMethods($token);
    */
    
    wp_enqueue_script( 'yourpay-resurskit', 'https://payments.yourpay.se/js/resurs.js', array(), '1.0' );

    $price = "";
    if(isset($product->price) && number_format($product->price,2,".","") > 1) {
        $price = "<div class='ResursBank' data-price='".number_format($product->price,2,".","")."' data-period='36'></div>";
    }
    echo $price;
}
function my_custom_checkout_field( $checkout ) {
	woocommerce_form_field( 'yourpay_resurs_state', array( 
		'type' 			=> 'text', 
		'class' 		=> array('input-hidden'), 
		)
        );
        echo "<style>#yourpay_resurs_state {display:none;}</style>";
}    
add_action('woocommerce_after_order_notes', 'my_custom_checkout_field');
