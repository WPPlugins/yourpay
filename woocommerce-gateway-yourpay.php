<?php
/*
Plugin Name: Yourpay Payment Platform
Plugin URI: http://www.yourpay.io
Description: Full WooCommerce payment gateway for VISADankort, VISA and Mastercards.
Version: 3.0.61
Author: Yourpay
Author URI: http://www.yourpay.io/
Text Domain: yourpay.io
*/
	define('yourpayDirectory', dirname(__FILE__) . '/');
	/**
 	* Gateway class
 	**/
        if ( class_exists( 'WooCommerce' ) ) {

            class WC_Yourpay2_0 extends WC_Payment_Gateway
            {	
                    public function __construct()
                    {
                            global $woocommerce;

                            $supports[] = "products";
                            $supports[] = 'refunds';
                            $supports[] = 'default_credit_card_form';

                            $this->id = 'yourpay';
                            $this->method_title = 'Yourpay';
                            $this->icon = 'https://payments.yourpay.se/img/kortlogoer.png';
                            $this->has_fields = true;
                            $this->supports = $supports;

                            // Load the form fields.
                            $this->init_form_fields();

                            // Load the settings.
                            $this->init_settings();

                            // Define user set variables
                            $this->enabled = $this->settings["enabled"];
                            $this->title = $this->settings["title"];
                            $this->yp_token = $this->settings["yp_token"];
                            $this->yp_merchantid = $this->settings["yp_merchantid"];
                            $this->yp_inpage = $this->settings["yp_inpage"];

                            $this->yp_autocapture = $this->settings["yp_autocapture"];
                            $this->yp_language = $this->settings["yp_language"];

                            // Actions
                            add_action('init', array(&$this, 'check_callback'));
                            add_action('yourpay-callback', array(&$this, 'successful_request', ));

                            add_action('add_meta_boxes', array( &$this, 'yourpay_meta_boxes' ), 10, 0);

                            add_action('woocommerce_api_' . strtolower(get_class()), array($this, 'check_callback'));
                            add_action('wp_before_admin_bar_render', array($this, 'yourpay_action', ));
                            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                            add_action('woocommerce_receipt_yourpay', array($this, 'receipt_page'));

                            add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
                            add_action( 'admin_notices', array( $this, 'admin_notices' ) );
                            add_action( 'plugins_loaded', array( $this, 'init' ), 0 );
                    }
                    public static function filter_load_instances($methods)
                    {
                        require_once('platforms/instance.php');
                        require_once('platforms/viabill.php');
                        require_once('platforms/resurs.php');
                        $methods[] = 'WC_Yourpay_ViaBill';
                        $methods[] = 'WC_Yourpay_Resurs';                    
                        return $methods;
                    }
                    public function process_refund( $order_id, $amount = null, $reason = '') {
                        $transactionId = get_post_meta($order_id, 'Transaction ID', true);
                        $timeid = get_post_meta($order_id, 'timeid', true);
                        $MerchantNumber = get_post_meta($order_id, 'MerchantNumber', true);
                        $data = $this->_RefundPayment($MerchantNumber, $timeid, $transactionId, $amount);
                        if($data->result == "1")
                            return TRUE;
                        else
                            return FALSE;
                    }

                    public function plugin_action_links( $links ) {
                            $addons = ( class_exists( 'WC_Subscriptions_Order' ) || class_exists( 'WC_Pre_Orders_Order' ) ) ? '_addons' : '';
                            $plugin_links = array(
                                    '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_yourpay' . $addons ) . '">' 
                                . __( 'Settings', 'yourpay' ) . '</a>',
                                    '<a href="http://www.yourpay.io">' . __( 'Support', 'yourpay' ) . '</a>',
                                    '<a href="http://www.yourpay.dk/">' . __( 'Docs', 'yourpay' ) . '</a>',
                            );
                            return array_merge( $plugin_links, $links );
                    }                
                    public function init() {
                            if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
                                    return;
                            }
                    }
                    function _GetToken() {
                        return get_option('yourpay_token',array());
                    }
                    function _GetMerchantData($token = "") {

                            $request['function'] = "customer_data";
                            $request['token'] = $token;

                            $result = json_decode(json_decode($this->v4requestresponse($request)));

                            return $result;
                    }
                    public function _GetResursPaymentMethods($token) {                
                                    $request['function'] = "resurs_getpaymentmethods";
                                    $request['token'] = $token;
                                    return json_decode(json_decode($this->v4requestresponse($request)));

                    }
                    function _RefundPayment($MerchantNumber, $timeid, $TransID, $amount)
                    {
                            $request['function']    = "refund_payment";
                            $request['merchantid']  = $MerchantNumber;
                            $request['timeid']      = $timeid;
                            $request['paymentid']   = $TransID;
                            $request['amount']      = str_replace(array(".",","),"",$amount);

                            $result = json_decode(json_decode($this->v4requestresponse($request)));


                            return $result;    

                    }                
                    function _ValidateLogin($Username, $password)
                    {
                               $request['function'] = "validatelogin";
                               $request['username'] = $Username;
                               $request['password'] = $password;

                               $result = json_decode($this->v4requestresponse($request));

                            return $result;    

                    }
                    function _GetPaymentData($TransID, $timeid, $MerchantNumber) {

                                if(strlen($timeid) < 1) {
                                    $login  = json_decode($this->_ValidateLogin($this->yp_username, $this->yp_password));

                                    if($login->sessionkey == "incomplete")
                                        return $login;

                                    $request['function'] = "payment_data";
                                    $request['uid'] = $login->uid;
                                    $request['sessionkey'] = $login->sessionkey;
                                    $request['paymentid'] = $TransID;

                                    $result = json_decode(json_decode($this->v4requestresponse($request)));

                                } else {

                                    $request['function']    = "payment_data";
                                    $request['merchantid']  = $MerchantNumber;
                                    $request['timeid']      = $timeid;
                                    $request['paymentid']   = $TransID;

                                    $result = json_decode(json_decode($this->v4requestresponse($request)));

                                }

                            return $result;
                    }
                    function _CapturePayment($TransID, $Amount, $order) {

                                $transactionId = get_post_meta($order->id, 'Transaction ID', true);
                                $timeid = get_post_meta($order->id, 'timeid', true);
                                $MerchantNumber = get_post_meta($order->id, 'MerchantNumber', true);

                                    $data["function"]       = "capture_payment";
                                    $data['amount']         = $Amount;
                                    $data['merchantid']     = $MerchantNumber;
                                    $data['timeid']         = $timeid;
                                    $data['paymentid']      = $TransID;

                                    $result                 = json_decode($this->v4requestresponse($data));

                          return $result;            
                    }
                    function _DeletePayment($username, $password, $TransID) {
                                $data["function"]    = "delete_payment";
                                $data['token']       = $this->yp_token;
                                $data['transid']     = $TransID;
                                $result              = json_decode($this->v4requestresponse($data));


                        return $result;            
                    }
                    function v4requestresponse($data)
                    {

                        $url = "https://webservice.yourpay.dk/v4/".$data['function'];
                        $fields_string = array();
                        foreach($data as $key=>$value){
                            $fields_string[$key] = urlencode($value);
                        }

                        $ch = curl_init();

                        curl_setopt($ch, CURLOPT_URL, $url);
                        curl_setopt($ch, CURLOPT_POST, 1);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields_string));
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        $server_output = curl_exec ($ch);
                        curl_close ($ch);
                        return json_encode($server_output);
                    }        
                    public function v4pluginsresponse($data)
                    {

                        $url = "https://webservice.yourpay.dk/plugins/".$data['function'];
                        $fields_string = array();
                        foreach($data as $key=>$value){
                            if(strlen($value)>0)
                                $fields_string[$key] = urlencode($value);
                        }
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $url);
                        curl_setopt($ch, CURLOPT_POST, 1);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields_string));
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        $server_output = curl_exec ($ch);
                        curl_close ($ch);
                        return ($server_output);
                    }        
                    function _RequestPayment($data) {

                        $data["function"]               = $data["function"];
                        $Data                           = json_decode($this->v4requestresponse($data));
                        return $Data;

                    }

                function woocommerce_api_callback() {
                    echo "Yes!";
                }
                function init_form_fields()
                    {
                    $statuses = wc_get_order_statuses();
                    foreach($statuses as $value)
                        $status_array[$value] = $value;

                    $arrayfields = array(
                                    'enabled' => array(
                                                                    'title' => __( 'Enable/Disable', 'woocommerce'), 
                                                                    'type' => 'checkbox', 
                                                                    'label' => __( 'Enable Yourpay', 'woocommerce'), 
                                                                    'default' => 'yes'
                                                            ), 
                                    'yp_merchantid' => array(
                                                                    'title' => __( 'Merchant number', 'yourpay'), 
                                                                    'type' => 'text',  
                                                                    'default' => ''
                                                            ),
                                    'title' => array(
                                                                    'title' => __( 'Title', 'Yourpay' , 'yourpay'), 
                                                                    'type' => 'text', 
                                                                    'default' => __( 'Yourpay', 'yourpay')
                                                            ),
                                    'yp_token' => array(
                                                                    'title' => __( 'Yourpay Token', 'yourpay'), 
                                                                    'type' => 'text',  
                                                                    'default' => ''
                                                            ),
                                    'yp_language' => array(
                                                                    'title' => __( 'Language', 'woocommerce'), 
                                                                    'type' => 'select', 
                                                                    'label' => __( 'Enable Yourpay', 'woocommerce'), 
                                                                    "options" => array(
                                                                        "da-dk" => "Danish", 
                                                                        "en-gb" => "English", 
                                                                        "sv-se" => "Swedish", 
                                                                        "nb-no" => "Norwegian", 
                                                                        "sk-sk" => "Slovak", 
                                                                        "de-de" => "German", 
                                                                        "nl-nl" => "Dutch", 
                                                                        "fr-fr" => "French", 
                                                                        "pl-pl" => "Polish", 
                                                                        "es-es" => "Spanish", 
                                                                        "fo-fo" => "Faroese"
                                                                        ),

                                                            ), 
                                    'yp_inpage' => array(
                                                                    'title' => __( 'Inpage', 'woocommerce'), 
                                                                    'type' => 'checkbox', 
                                                                    'label' => __( 'Inpage payment form', 'woocommerce'), 
                                                                    'default' => 'no',
                                                                    'description' => 'If enabled, credit card form on the checkout, instead of credit card fields hovered over the website.'
                                                            ), 
                                    'yp_autocapture' => array(
                                                                    'title' => __( 'Instant Capture', 'woocommerce'), 
                                                                    'type' => 'checkbox', 
                                                                    'label' => __( 'Instant Capture', 'woocommerce'), 
                                                                    'default' => 'no',
                                                                    'description' => 'Capture payment instantly'
                                                            ), 
                                            'yp_capture_on_stage' => array(
                                                                            'title' => __( 'Capture at Specific Stage', 'woocommerce'), 
                                                                            'type' => 'checkbox', 
                                                                            'label' => __( 'Capture transaction automatially if it reach stage defined below', 'woocommerce'), 
                                                                            'default' => 'yes'
                                                                    )
                                            );
                            $arrayfields['yp_capture_stage'] = array(
                                                                            'title' => __( 'Capture Stage', 'woocommerce'), 
                                                                            'type' => 'select', 
                                                                            'label' => __( 'Choose stage where transaction should autocapture', 'woocommerce'), 
                                                                            "options" => $status_array,

                                                                    );                
                    include_once ABSPATH . 'wp-admin/includes/plugin.php';

                    $this->form_fields = $arrayfields;
                } 
                    public function generateurl($key, $value) {

                        if(!is_object($value) && !is_array($value))
                            return $key."=".$value."&";

                    }
                public function admin_notices() {
                        if ( $this->enabled == 'no' ) {
                                return;
                        }
                }
                    public function admin_options()
                    {
                            $plugin_data = get_plugin_data(__FILE__, false, false);
                            $version = $plugin_data["Version"];
                            echo '<h3>' . 'Yourpay' . ' v' . $version . '</h3>';
                            echo '<table class="form-table">';
                                    $this->generate_settings_html();
                            echo '</table>';

                            if($this->yp_token != "")
                                update_option("yourpay_token", $this->yp_token, true);

                    }
                    function payment_fields()
                    {
                            if($this->yp_inpage == 'yes') {

                                $rg = is_user_logged_in() ? get_user_meta( get_current_user_id(), '_yourpay_rg_id', true ) : 0;
                                $tcardno = is_user_logged_in() ? get_user_meta( get_current_user_id(), '_yourpay_tcardno_id', true ) : 0;
                                $display = "";
                                $merchantdata = $this->_GetMerchantData($this->yp_token);                            
                                $cardno = "";
                                $exp    = "";
                                $cvc    = "";

                                if($this->yp_merchantid == 
                                        $merchantdata->merchantid) {echo "<script>jQuery(function() {jQuery('#yourpay-card-number').val(5105105105105100);jQuery('#yourpay-card-expiry').val('12/30');jQuery('#yourpay-card-cvc').val(123);});</script>";}

                                if($rg != 0) {
                                    $display = "style='display:none;'";
                                ?>
                                            <p class="form-row form-row-wide">
                                                                    <label for="yourpay_card_<?php echo $rg; ?>">
                                                                            <input type="radio" id="yourpay_card_<?php echo $rg; ?>" name="yourpay_rg_id" 
                                                                                   value="<?php echo $rg; ?>" checked />
                                                                            <?php printf( __( 'Use %s as you used last time', 'yourpay' ), 
                                                                                    $tcardno); ?>
                                                                    </label>
                                                    <label for="new">
                                                            <input type="radio" id="new" name="yourpay_rg_id" value="0" />
                                                            <?php _e( 'Use a new credit card', 'yourpay' ); ?>
                                                    </label>
                                            </p>
                                <?php
                                }
                                echo '<fieldset '.$display.'>';
                                ?>
                                        <div class="yourpay_new_card"
                                                data-description=""
                                                data-amount="<?php echo WC()->cart->total; ?>"
                                                data-name="<?php echo sprintf( __( '%s', 'yourpay' ), get_bloginfo( 'name' ) ); ?>"
                                                data-label="<?php _e( 'Confirm and Pay', 'yourpay' ); ?>"
                                                data-currency="<?php echo strtolower( get_woocommerce_currency() ); ?>"
                                                data-image="http://www.yourpay.io/img/compressed/yourpay-logo.png"
                                                >
                                                <?php $this->credit_card_form( array( 'fields_have_names' => true ) ); ?>

                                        </div>
                                <?php         
                                echo '</fieldset>';
                                echo '<script>jQuery(function() {jQuery("input[name=\'yourpay_rg_id\']").click(function() { if(jQuery(this).attr("value") == 0) { jQuery(".yourpay_new_card").parent().slideDown("slow"); } else { jQuery(".yourpay_new_card").parent().slideUp("slow"); };});});</script>';
                            }

                    }
        public function getPersonOrder($orderdata, $details) {
            return "$details=".$orderdata->$details."&";
        }    

                public function generate_yourpay_form($order_id)
                    {
                            global $woocommerce;

                            if (isset($_SERVER['HTTPS']) &&
                                ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1) ||
                                isset($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
                                $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
                              $protocol = 'https://';
                            }
                            else {
                              $protocol = 'http://';
                            }
                            $order = new WC_Order($order_id);
                            $customer = new WC_Customer($order_id);

                            if(strlen($this->yp_merchantid) < 1) {
                                  return "Please fill out Yourpay Merchant ID ";
                                exit;
                            }

                            $merchantid = $this->yp_merchantid;
                            $orderTotal = ($order->order_total * 100);
                            $currency = $this->get_iso_code(get_option('woocommerce_currency'));
                            $time = time();
                            $cartid = $order->id;
                            $customerName = $order->billing_last_name . "," . $order->billing_first_name;
                            $customer_email = $order->billing_email;
                            $version = 109;
                            $splitpayment = 0;
                            $wsdl = "";
                            $urlprepare = $this->get_return_url($order);
                            $language = $this->yp_language;
                            $design = 1;
                            $ct = "off";
                            $autocapture = "";
                            $yourpay_payment_form_url = "https://payments.yourpay.se/betalingsvindue.php";

                            include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
                            if(isset($this->yp_autocapture) && $this->yp_autocapture == "yes") {
                                $autocapture = '<input type="HIDDEN" name="autocapture" value="yes" />';
                            }
                            $callbackurl = add_query_arg('wooorderid', $order_id, add_query_arg ('wc-api', 'wc_yourpay2_0', $this->get_return_url( $order )));
                            $checksum = sha1($cartid.$version.$merchantid.$splitpayment.$orderTotal.$currency.$time.$wsdl);
                            $subscriptiondetails = "";

                            $customerdata = array("billing_email","billing_first_name", "billing_last_name", "billing_company", "billing_address_1", "billing_address_2", "billing_city", "billing_state", "billing_postcode", "billing_country","shipping_first_name", "shipping_last_name", "shipping_company", "shipping_address_1", "shipping_address_2", "shipping_city", "shipping_state", "shipping_postcode", "shipping_country");
                            $yourpay_payment_form_url .= "?";
                            foreach($customerdata as $value) {$yourpay_payment_form_url .= $this->getPersonOrder($order,$value);}
                            $order_item = $order->get_items(); $yourpay_payment_form_url .= "total_products=".count($order_item)."&"; $i = 1;
                            foreach($order_item as $product ) {$yourpay_payment_form_url .= "product_".$i."_id=" . $product["product_id"]."&";$yourpay_payment_form_url .= "product_".$i."_name=" . $product["name"]."&";
                            $yourpay_payment_form_url .= "product_".$i."_qty=" . $product["qty"]."&";$yourpay_payment_form_url .= "product_".$i."_total=" . $product["line_total"]."&";$yourpay_payment_form_url .= "product_".$i."_tax=" . $product["line_tax"]."&"; $i++;}


                                $form = '<form name="yourpay_form" class="yourpay_form overflow" action="'.$yourpay_payment_form_url.'" method="post">
                                            <input type="HIDDEN" name="version" value="'.$version.'" />
                                            <input type="HIDDEN" name="ShopPlatform" value="woocommerce" />
                                            <input type="HIDDEN" name="MerchantNumber" value="'.$merchantid.'" />
                                            <input type="HIDDEN" name="split" value="'.$splitpayment.'" />
                                            <input type="HIDDEN" name="amount" value="'.$orderTotal.'" />
                                            <input type="HIDDEN" name="CurrencyCode" value="'.$currency.'" />
                                            <input type="HIDDEN" name="time" value="'.$time.'" />
                                            <input type="HIDDEN" name="cartid" value="'.$cartid.'" />
                                            <input type="HIDDEN" name="customername" value="'.$customerName.'" />
                                            <input type="HIDDEN" name="designurl" value="" />
                                            <input type="HIDDEN" name="checksum" value="'.$checksum.'" />
                                            <input type="HIDDEN" name="accepturl" value="'.($urlprepare).'" />
                                            <input type="HIDDEN" name="callbackurl" value="'.($callbackurl).'" />                                            
                                            <input type="HIDDEN" name="lang" value="'.$language.'" />
                                            <input type="HIDDEN" name="designtype" value="'.$design.'" />
                                            <input type="HIDDEN" name="ct" value="'.$ct.'" />
                                            <input type="HIDDEN" name="customer_email" value="'.$customer_email.'" />
                                            <input type="HIDDEN" name="customer_email" value="'.$orderdata->billing_phone.'" />
                                            '.$autocapture.'
                                            <input type="HIDDEN" name="viewtype" value="overflow" />';

                                        if ( is_plugin_active( 'yourpay-woocommerce-subscriptions/woocommerce-subscriptions-yourpay.php' ) ) {
                                            $form .= '<input type="HIDDEN" name="ccrg" value="1" />';
                                        }

                                            foreach($order as $key=>$value) {
                                                if(!is_object($value))
                                                    $form .= '<input type="HIDDEN" name="'.$key.'" value="'.$value.'" />';
                                                elseif(is_object($value))
                                                    foreach($value as $subkey=>$subvalue) {
                                                        if(!is_object($subvalue))
                                                            $form .= '<input type="HIDDEN" name="'.$subkey.'" value="'.$subvalue.'" />';
                                                    }
                                            }
                                $form .= '</form>';
                            return $form;
                    }
                    function process_payment($order_id)
                    {
                        global $woocommerce;

                        $order        = new WC_Order( $order_id );

                        if(isset($this->yp_inpage) && $this->yp_inpage == 'yes') {
                            $cardno       = isset( $_POST['yourpay-card-number'] ) ? wc_clean( $_POST['yourpay-card-number'] ) : '';
                            $cardexp      = isset( $_POST['yourpay-card-expiry'] ) ? wc_clean( $_POST['yourpay-card-expiry'] ) : '';
                            $cardcvc      = isset( $_POST['yourpay-card-cvc'] ) ? wc_clean( $_POST['yourpay-card-cvc'] ) : '';
                            $rg           = isset( $_POST['yourpay_rg_id'] ) ? wc_clean( $_POST['yourpay_rg_id'] ) : '';

                            $DataRequest['function']        = "process_payment";
                            $DataRequest['merchantid']      = $this->yp_merchantid;
                            $DataRequest['orderid']         = $order->id;
                            $DataRequest['amount']          = ($order->order_total * 100);
                            $DataRequest['currency']        = $this->get_iso_code(get_option('woocommerce_currency'));

                            if(isset($this->yp_autocapture) && $this->yp_autocapture == "yes") {
                                $DataRequest['autocapture'] = "yes";
                            }                        

                            if(isset($rg) && strlen($rg) > 5 && $rg != "0") {
                                $DataRequest['rg']              = $rg;
                                $DataRequest['cardno']          = 0;
                                $DataRequest['ccrg']            = 0;
                                $DataRequest['expmonth']        = 0;
                                $DataRequest['expyear']         = 0;
                                $DataRequest['cvc']             = 0;
                             } else {
                                $DataRequest['cardno']          = $cardno;
                                $DataRequest['ccrg']            = 1;
                                $DataRequest['expmonth']        = substr($cardexp, 0, 2);
                                $DataRequest['expyear']         = substr($cardexp, -2, 2);
                                $DataRequest['cvc']             = $cardcvc;
                            }
                            $DataRequest['cardholder']      = $order->billing_last_name . "," . $order->billing_first_name;    
                            $DataRequest['customer_email']  = $order->billing_email;
                            $DataRequest["customer_phone"] = $order->billing_phone;
                            $DataRequest['ycallback']       = add_query_arg('wooorderid', $order_id, add_query_arg ('wc-api', 'wc_yourpay2_0', $this->get_return_url( $order )));
                            $DataRequest['shopplatform']    = "woocommerce";

                            foreach($order as $key=>$value) {
                                if(!is_object($value))
                                    $DataRequest[$key] = $value;
                                elseif(is_object($value))
                                foreach($value as $subkey=>$subvalue) {
                                    if(!is_object($subvalue))
                                        $DataRequest[$subkey] = $subvalue;
                                }
                            }                        

                            $jsondata = $this->_RequestPayment($DataRequest);


                                $payment = json_decode($jsondata);
                                if($payment->status == "ACK") {
                                    if(isset($payment->ccrg) && $payment->ccrg != "") {
                                        update_user_meta( get_current_user_id(), '_yourpay_rg_id', $payment->ccrg);
                                        update_user_meta( get_current_user_id(), '_yourpay_tcardno_id', $payment->cardno);
                                    }

                                    return array(
                                            'result'   => 'success',
                                            'redirect' => $this->get_return_url( $order )
                                    );
                                    exit;
                                }
                                else {
                                    throw new Exception("Payment Failed! Try again");
                                }                            

                            die();
                        }

                            return array(
                                    'result' 	=> 'success',
                                    'redirect'	=> $order->get_checkout_payment_url( true )
                            );
                    }

                    function receipt_page( $order )
                    {
                            global $woocommerce;
                            wp_enqueue_script('yourpay', WP_PLUGIN_URL.'/yourpay/yourpay.js');
                            wp_enqueue_style('yourpay',  WP_PLUGIN_URL.'/yourpay/yourpay.css');
                            echo translate("Please wait a momemnt, while the payment window is being initialised")."<br />";
                            echo "<input type='button' value='".translate("Click here to open payment window")."' class='startpayment'>";

                            if($this->yp_inpage == 'no') {
                                echo $this->generate_yourpay_form($order, $this);                            
                            } else 
                                $this->process_payment($order);
                    }
                    function check_callback()
                    {
                            $_GET = stripslashes_deep($_GET);
                            do_action("yourpay-callback", $_GET);                        
                    }

                    function successful_request( $posted )
                    {
                            global $product;
                            $order = new WC_Order((int)$posted["wooorderid"]);
                            $var = "";
                            $wsdl = $this->yp_encryptioncode;
                            $shaprint = sha1($posted['tid'].$wsdl);
                            $order->add_order_note(__('Callback performed', 'yourpay'));
                            update_post_meta((int)$posted["wooorderid"], 'Transaction ID', $posted["tid"]);
                            update_post_meta((string)$posted["wooorderid"], 'Card no', $posted["tcardno"]);
                            update_post_meta((string)$posted["wooorderid"], 'timeid', $posted["time"]);
                            update_post_meta((string)$posted["wooorderid"], 'MerchantNumber', $posted["MerchantNumber"]);
                            if ( (string)$posted["ccrg"] ) {
                                update_post_meta((string)$posted["wooorderid"], 'subscription_rg_code', $posted["ccrg"]);                                    
                            }

                                include_once ABSPATH . 'wp-admin/includes/plugin.php';
                                if ( is_plugin_active( 'yourpay-woocommerce-subscriptions/woocommerce-subscriptions-yourpay.php' ) ) {

                                    require_once( ABSPATH . '/wp-content/plugins/yourpay-woocommerce-subscriptions/subscription_functions.php' );

                                    WC_Yourpay_add_subscription_product($order);
                                }

                            if($order->status !== 'completed' && isset($posted['tid']))
                            {
                                $order->payment_complete();
                                status_header(200);
                            } 
                            echo "OK";
                            die();

                            exit;
                    }
                    public function payment_scripts() {
                        echo "Yes!";
                        if ( ! is_checkout() ) {
                                return;
                        }

                    }


                    public function yourpay_meta_boxes()
                    {
                            add_meta_box( 
                                    'yourpay-payment-actions', 
                                    __('Yourpay', 'yourpay'), 
                                    array(&$this, 'yourpay_meta_box_payment'), 
                                    'shop_order', 
                                    'side', 
                                    'high'
                            );
                    }

                    public function yourpay_action()
                    {
                            global $woocommerce;

                            if(isset($_GET["yourpay_action"]))
                            {
                                    $order = new WC_Order($_GET['post']);
                                    $transactionId = get_post_meta($order->id, 'Transaction ID', true);
                                    try
                                    {
                                            switch($_GET["yourpay_action"])
                                            {
                                                    case 'capture':
                                                            $capture = $this->_CapturePayment($transactionId, $_GET['amount'] * 100, $order);
                                                            break;
                                                    case 'delete':
                                                            $delete = $this->_DeletePayment($this->yp_username, $this->yp_password, $transactionId);
                                                            break;
                                            }
                                    }
                                    catch(Exception $e)
                                    {
                                            echo $this->message("error", $e->getMessage());
                                    }
                            }
                    }

                    public function yourpay_meta_box_payment()
                    {
                            global $post, $woocommerce;

                            $order = new WC_Order($post->ID);
                            $transactionId = get_post_meta($order->id, 'Transaction ID', true);
                            $timeid = get_post_meta($order->id, 'timeid', true);
                            $MerchantNumber = get_post_meta($order->id, 'MerchantNumber', true);
                            if(strlen($transactionId) > 0)
                            {
                                    try
                                    {
                                            $transaction = $this->_GetPaymentData($transactionId, $timeid, $MerchantNumber);
                                            if(isset($transaction->sessionkey) && $transaction->sessionkey == "incomplete") {
                                                    echo "Could not authenticate user!";                                            
                                            }
                                            elseif(!is_wp_error($transaction))
                                            {

                                                if(isset($transaction->TransID)) {
                                                    $amount = $transaction->amount-$transaction->amount_captured;
                                                    if($this->yp_capture_on_stage == "yes" && strtolower($this->yp_capture_stage) == strtolower($order->status) && $transaction->time_captured == 0) {
                                                        $data = $this->_CapturePayment($transaction->TransID, ($transaction->amount-$transaction->amount_captured), $order);
                                                        $transaction = $this->_GetPaymentData($transactionId, $timeid, $MerchantNumber);
                                                    }
                                                    echo '<p>';
                                                    echo '<strong>' . _e('Transaction ID', 'yourpay') . ':</strong> ' . $transaction->TransID;
                                                    echo '</p>';
                                                    echo '<p>';
                                                    echo '<strong>' . _e('Authorized amount', 'yourpay') . ':</strong> ' . get_option('woocommerce_currency') . ' ' . number_format($transaction->amount / 100, 2, ".", "");
                                                    echo '</p>';
                                                    echo '<p>';
                                                    echo '<strong>' . _e('Captured amount', 'yourpay') . ':</strong> ' . get_option('woocommerce_currency') . ' ' . number_format($transaction->amount_captured / 100, 2, ".", "");
                                                    echo '</p>';
                                                    if($transaction->time_captured == 0 && $transaction->time_deleted == 0)
                                                    {
                                                                            echo '<p>';
                                                                                    echo get_option('woocommerce_currency') . ' <span><input type="text" value="' . number_format(($transaction->amount-$transaction->amount_captured) / 100, 2, ".", "") . '" id="yourpay_amount" name="yourpay_amount" /></span>';
                                                                            echo '</p>';
                                                                            echo '<a class="button" onclick="javascript:location.href=\'' . admin_url('post.php?post=' . $post->ID . '&action=edit&yourpay_action=capture') . '&amount=\' + document.getElementById(\'yourpay_amount\').value">';
                                                                                    echo _e('Capture Payment', 'yourpay');
                                                                            echo '</a>';
                                                                            echo '<br />';
                                                                            echo '<br />';
                                                                            echo '<hr />';
                                                                            echo '<br />';
                                                                            echo _e('The reservation on your customers creditcard be discarded and the transaction will not be able to capture afterwards.', 'yourpay');;
                                                                            echo '<br />';
                                                                            echo '<a class="button" onclick="javascript:location.href=\'' . admin_url('post.php?post=' . $post->ID . '&action=edit&yourpay_action=delete') . '&amount=\' + document.getElementById(\'yourpay_amount\').value">';
                                                                                    echo _e('Delete Payment', 'yourpay');
                                                                            echo '</a>';
                                                                    echo '<br />';
                                                    } elseif($transaction->time_captured != 0 && $transaction->time_deleted == 0) {
                                                        echo "<br /><br />";
                                                        echo "<b>Amount captured: ".date("H:i:s, d-m-y", $transaction->time_captured)."</b><br />";
                                                    } elseif($transaction->time_deleted != 0) {
                                                        echo "<b>Transaction Deleted: ".date("H:i:s, d-m-y", $transaction->time_deleted)."</b><br />";
                                                    }                                                

                                                } else {

                                                    echo $transaction->status;
                                                }

                                            }
                                    }
                                    catch(Exception $e)
                                    {
                                            echo $this->message("error", $e->getMessage());
                                    }
                            }
                            else
                                    echo "No transaction was found.";
                    }

                    private function message($type, $message) {
                            return '<div id="message" class="'.$type.'">
                                    <p>'.$message.'</p>
                            </div>';
                    }

                    public function get_iso_code($code)
                    {
                            switch(strtoupper($code))
                            {
                                    case 'DKK':
                                            return '208';
                                            break;
                                    case 'EUR':
                                            return '978';
                                            break;
                                    case 'SEK':
                                            return '752';
                                            break;
                            }
                            return '208';
                    }
            }
            function add_yourpay_gateway($methods) 
            {
                    $methods[] = 'WC_Yourpay2_0';

                    return apply_filters('woocommerce_yourpay_load_instances', $methods);
            }

            function init_yourpay_gateway()
            {
                    $plugin_dir = basename(dirname(__FILE__ ));
                    load_plugin_textdomain('yourpay', false, $plugin_dir . '/languages/');
            }

            add_filter('woocommerce_payment_gateways', 'add_yourpay_gateway');
            add_filter('woocommerce_yourpay_load_instances', 'WC_Yourpay2_0::filter_load_instances');        
            add_action('plugins_loaded', 'init_yourpay_gateway');

            function WC_Yourpay2_0() 
            {
                return new WC_Yourpay2_0();
            }

            if (is_admin())
                add_action('load-post.php', 'WC_Yourpay2_0');

            add_filter( 'woocommerce_get_price_html', 'custom_price_message' );
            function custom_price_message($price) {
                global $woocommerce;
                $available_gateways = $woocommerce->payment_gateways->get_available_payment_gateways();
                if($available_gateways['yourpay-resurs']) {
                    wp_enqueue_script( 'script', 'https://payments.yourpay.se/js/pricetags.js', array ( 'jquery' ), 1.1, true);
                }
                return $price;
            }

            register_activation_hook(__FILE__, 'nht_plugin_activate');
            add_action('admin_init', 'nht_plugin_redirect');

            function nht_plugin_activate() {
            add_option('nht_plugin_do_activation_redirect', true);
            }

            function nht_plugin_redirect() {
            if (get_option('nht_plugin_do_activation_redirect', false)) {
                delete_option('nht_plugin_do_activation_redirect');
                wp_redirect("admin.php?page=yourpay_setup");
             }
            }
            /**
             * Register a custom menu page.
             */
            function wpdocs_register_yourpay_setup(){
                add_submenu_page(
                    null,
                    __( 'Yourpay Setup', 'textdomain' ),
                    'custom menu',
                    'manage_options',
                    'yourpay_setup',
                    'yourpay_setup_page'
                ); 
            }
            add_action( 'admin_menu', 'wpdocs_register_yourpay_setup' );
            /**
             * Display a custom menu page
             */
            function yourpay_setup_page(){
                $completed = 0;
                if(isset($_GET['contactName'])) {
                    $CustomerData['function']       = "create_merchant";
                    $CustomerData['CompanyVAT']     = $_GET['vat'];
                    $CustomerData['PersonalName']   = $_GET['contactName'];
                    $CustomerData['PersonalEmail']  = get_option('admin_email');
                    $CustomerData['CompanyDetails'] = "Created through WooCommerce";
                    $CustomerData['CompanyShop']    = "WooCommerce";
                    $CustomerData['CompanyCity']    = "";
                    $CustomerData['CompanyPhone']   = "";
                    $CustomerData['CompanyAddress'] = "";
                    $CustomerData['CompanyDomain']  = $_SERVER[HTTP_HOST];
                    $CustomerData['CompanyName']    = $_SERVER[HTTP_HOST];
                    $CustomerData['psp']            = "0";
                    $ypObject = new WC_Yourpay2_0();
                    $CreatedMerchant = $ypObject->v4requestresponse($CustomerData);
                    $_GET['merchant_token'] = $CreatedMerchant->token;
                }
                if(isset($_GET['merchant_token'])) {
                    $ypObject = new WC_Yourpay2_0();
                    $MerchantData = $ypObject->_GetMerchantData($_GET['merchant_token']);


                    if(isset($MerchantData->merchantid)) {

                        if($MerchantData->prod_merchantid != 0) {
                            $MerchantId = $MerchantData->prod_merchantid;                        
                            $completed  = 2;
                        }
                        else {
                            $MerchantId = $MerchantData->merchantid;
                            $completed  = 1;
                        }
                        $woocommerce_yourpay_settings = get_option( 'woocommerce_yourpay_settings', array() );
                        $woocommerce_yourpay_settings['yp_token'] = $_GET['merchant_token'];
                        $woocommerce_yourpay_settings['yp_merchantid'] = $MerchantId;
                        $woocommerce_yourpay_settings['yp_inpage'] = "yes";
                        $woocommerce_yourpay_settings['yp_autocapture'] = "no";
                        update_option( 'woocommerce_yourpay_settings', $woocommerce_yourpay_settings );
                    }
                }

                wp_register_style('yourpay_setup_css', plugins_url('css/setup.css',__FILE__ ));
                wp_enqueue_style('yourpay_setup_css');

                echo "<div class='yourpay grayedout'></div>";
                echo "<div class='yourpay outer'>";
                echo "<div class='yourpay middle'>";
                echo "<div class='yourpay inner'>";
                    if($completed == 1) {
                        echo "<div class='yourpay setup'>";
                            echo "<div class='yourpay setup-start'>";
                            echo "<img src='data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAc1UlEQVR4Xu1deZwcRdmut2p2k40Kq6CfKOJBlhiT7a7eIeBNEAFFEA9QBBEPEDzwQPBE5PDGA1QOFS+8BRUVkA8EAqKIuNtVs1kwGMQA4udFQEKEzFS93+8Zu/PrtLM7m5me3R126p8c01Vd9dbTb1W9x1MkemVeS4Dm9eh7gxc9AMxzEPQA0APAPJfAPB9+TwP0ADDPJTDPh9/TAD0AzHMJzPPh9zRADwDzXALzfPg9DdADwDyXwDwffk8D9AAwzyUwz4ff0wA9AMw/CSxbtuzhExMT9wshuNHoFy9evGDHHXekVatWPfBQl8680QBRFD2OmV8qhHi5EGJPIcRRxpjzGk1wGIbfJaJDvPffIaIflkqly0ZHRzc+FMHwkAaA1vpJzPwyTDoRPVMIcT8RXcfM+zLzAdbaixtNahAE35ZSHiqEuE4I8Szv/b+J6OdSyh/29/dfcsMNN/zroQKGhyQAoig63Dl3mpTyiUKIe4QQP8WXvHDhwivuv//+naSUvxdCRMYYM4kGOIWZD65UKk9bsWLFY2u12ku99y9n5pVSSieEiJ1zR42Pj493OxC6BgBhGH6MiLY1xry5mdC11t8SQhzGzK+s1WoXTUxMbErrRFH0XGa+Rin1uNHR0b80aktrjXecZozZLvv7brvttt0DDzzwOWgH7/2LK5XKz6bqS7lc7qtWqxdJKS8yxny5Wb9n4/euAIDW+mAhxA8gIGYesdbGTQS/fbVavYmIfmqtPTL7bBiGryCi7w0ODvavWrWq1qidKIrwtV9YrVYXZMGzZMmSRyxYsGA1Ed1orUWfGm4i0za11u8TQnwU/yaix8dxfNdsTPJU75zzAAiC4MnMbEql0oXe+6cS0YKhoaHdL7jgAqjiSUs6icz8Amvt/2Y0wLHOuZMqlcqjJ6schuGzieiX3vsnVCqVO9PnwjA8B0uD937Z6tWr/zrV+7XWQ0KIcSI6A3WEEOuGhob2btbvmQbInAbAsmXL+vv6+n4phHiYUmq3Wq0GMMRSyncbY85oJiyt9feFEM9USi0fHR29F89rrT8shHiJMWb5ZPWTybtFSrnr2NjYKJ6LomgvZv4FEb0qjuPvNXk3aa2vJKLtpZRl55z23v9KKXVaHMenNev3TP4+pwGgtT7de/8WIlphrZ2AYMIwPJWIjiOiZXEcr2uiBR7tnLtJSvljY8wbEwB8mYieEsfxXpPV3X333bd58MEHAZgXGWMuTVT/uJRyzBiDY+SUqj8Mw9cT0Xne+2dUKpUbkn6jz6cT0Z5xHF87k5PclUtAFEUvZOZL8+f1lStXLrznnnusEOJWY8yLmk1Gun/A0c9ae3kURT91zt1XqVQOm0IwFAQBjoxvsdZ+LQiCs6SUh/T19S278cYb/28qgS5fvvx/SqXSzcz8TWvt2zPPQitg06iVUnp0dPQfcwEEc1IDhGH4eGa2UsrLjTGYqC2+uCiK9mDmVcx8iLUWan7KorW+wHu/28DAwPDGjRt/oZS6zhhzXJM1/DYhxJeY+TdEdBVOFcaY7zR7F4xIUspnbdy4cdmaNWvuyz5fLpe3d87h6IkT6AHNwNvsXUX8PicBoLX+Er58Zr5SCHGRUurKsbExnN03A0FrjWPVi6vV6tKJiYm7pxJGEASPEULcpJS60Dn3QiI621r7iSYA+A02cUKIvbz3lUqlAitis13/fkKIS/JGJuxl+vv7n+6934uITsJ7pZQvHBsbu6yISWynjTkJgOXLlz9BKXUQEWGd3kMI8XDn3F8ABGa+qlQqXblp06b7lFJQtRfnj3qNBBKG4Stx/Et+e50x5utNAPATAEwIsV4ptWwym0HaBvwLSqkJKeVvhoaGDr311lux8UP/AaBnSykXCSHu9N5fiXFsu+22F8wFX8OcBEBObfY551YADM65vaSUzxBC9DPzWiKCoeaRQoj9jTGXNPkSKAzDK9DOVGbgtI0gCG7EKYCZD7fWwrDUbJn5kRACWuJXQoinJf2CZrqKiK703l9lrf1DMy3S7D1F/z7nAZAfcLlcXlSr1XBOr39dQohysqZGzYSTOIQu996/IHu+b1QviqJDmPlVxpiXNJs0fP19fX319Z6ZL8OEY/my1mKz6pv1azZ/7zoA5IW1bNmyRy1cuFDO9q4ajqdqtXpX1nI4mxM73Xd3PQCmO9Dec40l0APAPEdGDwA9AMxzCczz4fc0QA8A81wC83z4c0YDwFwrpazH7W3YsOHatWvXPvhQmhs4sdavX/9cIhpQSv1qto+tqWznBACiKDqWmT8FC1/SsTullAePjY3BHt/1BQEmzPx9KeXjEmPRA1LKd8ZxfO5sD27WARBF0Yudcz+SUr55YGDgm/fdd982pVLpYwjaqNVqS5tF3sy2AJu9H9bHWq12s1Lq+977E/v6+jY4514rhPg8Ee0fx/HPm7XRyd9nDACwlAkhVjLzv733l4+Pj6/HwLTWcLWuM8a8Lh3oypUrS3ffffc6KeVnjTHQDHhukJmXe+/vGh8f/2MnhdJq2+VyeedarbbDwoULK2nouNb6A977o5YsWbJzNhwsCT3f3hizL94Hi2Z/f/8+zLxAKXX16Ojo7a32Y2vqzQQA4IQ5mYhO9N5D9ZWcc/jztdbaHwdB8Ccp5VnGmNOzHY+iCK7gP8RxfIzW+h3MjKjghckzFy1YsOCIuRKfXy6Xt3XOwWG0P/rnvd+YhK2dFYbheUT0hHSi0zEmAaNvMMYsToJWvool0HuPQNUFRHSytfYjzfwQWzPZjZ7tOAC01kd5788kosOstT9ZuXJl//r1649n5pOYuayUupSZP2Ot/Wy2g2EYXk5E65j5Cmb+FpYIIsKziAuEUFcbYxBsOesFUUbM/BQiOgrazHt/ADN/AfsY7z2cSY+uVCqIXtpctNbvFkIc470/SAhxg5TyAxs2bDhz7dq1Va01ws6+hfbiOD6/kwPsOADCMLyZiH5mjMGAswJA1s2E935/KeUnjTFn5gDwcyJC+NUQJhuaIP09DdBk5h2ttX/upICatZ0sbbcx83OstRhTvWitvyaEQGLKXUS0TRzHiC3Ijv947/1bpZRX4zljzPNyvyPodU9jTNisD+383nEABEFQVUq9IY9krfXZQoilzPxUKeVH4zj+fE4AFzPz3cy8h5Ty8+leAM8gYKRUKt2O2IDZPimkIeT5RJMoit7LzEcy841YuowxiBXYXMIwPE5KiaXtdmYezcUPIvj19UKIs6y1A+1McLO6MwGAGhFhvd8iqCIMQywLI0KIXYQQpxpjzsoB4Cfe+w3YOEopT8+GgQdBsKOU8o65BIC+vr4dsgGjWusTENEspfwtM0trLVR9VgO8QwhxPKKEmPk6ay3+nv0dJ4VzjTHpvqfZXLb0+6wBQGv9GWZ+BjMvVkqdFMfxOTkB/IiINjHznsx8mrX2C+nv3QCAMAzfSUTvIqIbmLlmjHlldnxRFL2tVqu9VymFbKErjTHv6WoAaK1fRESnOud2VkrBgHNcHMc3BUHQUAMg5t85t4dSamdmfr+19os5AVyAf3vv91RKfTALkG4AACbYe/8+ZsYGb6MxBtnG2SXgrTgZCSGQn/hzY8z7JwPAyMhIUKvVPi2EWIGTEepls51a+vSTSoVogDAMkW59iZQS0bwxdr5Qz7VaLZRS3tZoCYii6ONCiL2xexZCINNni+TJKIq+hzMxACClPCH7ezcAAAmm3vtTpJRIDFlvjDk8N8H4/WSlFDa6P4nj+IONAOC9XwqZSimvEUIgnX1X7/0bhBD7VCoV2FDaKoUAIIqia5n5NmPMEejNwQcfrNasWTOulEISxvGNAIAULWbe33v/5FKpBG3xlexIYChRSj2CmZ/HzG+z1uKcXC/dAIAwDI8moo8jr4CZ/1qpVLCmby5RFB3jnIMM/qqU+oEx5pRGABBCnA0ZWGuxX6rHF+Lj8N5vb619fluzj6TVdhtAfa01EijPzBpzkJdHRDD6HDjJJvAUIsJ594nJBOPYlFWR5zPzdkT0PCnl0dlTRDcAIIqiNzDzZwEAbFjjOMZXu7lorZGqBi349ySLCDmL2d/rm0AiupyZ/2WMeXX6o9YaywUYTp7c7vwVCYAzskc1rTWyaAZgFJlkCTjJe/8qZgZhw5uNMd/ICeBrRLRDEgr+WmPMt7tJA2itX+u9P1spdT0zI42tnpuYmcQjvfefklIiRewrxhj4P/4LAEKIXwgh/plq1+SDm5MAyGuAb4LQwTm33yRLAGzkR8BMKqV8YxzH38xpgPOY+YlSSmTTHJrNyO0SDXA4LJbe++uJ6GZr7Zty40MCKYw9/xRCnGOM+WQjADDz1UT0F2MM7AL1Av+CEOL1xpid54oGuAPerewggiBA5g1y8PedZAl4DxEdLYR4fDKYzV84BhVF0bnOORiJ9iCig+M4vrCbNEAURdBusH38mohMHMfHNphgHG3XY6mw1n5mEgBcC5O4MQZm5nqJouiD3vvXWGvBQdBWKWQJCIJgnVLqnDiOsaalnfyK9x7MXHtPogFgCoVQHqeUek0cx9/NjgQZuTAUEdHTQfQEx1E3ASBhIkHiKk4BvzHGwPCzuURR9Brn3LnMfI9S6hN5UziWEOwBYCSSUq7NmcJPcs69ulKpwIjWVikKAH9SSp2bBQCSN6HCsYOdRAPUDSVCiB2Y+dB8lm8Yhp8jomcD8Hk+nm5YAsIwfBmIqXCEI6Kr4zjGWDeXMAxfDQ4BIcS9eUMXHkoBIIS4Xghxc5YbSWv9ISHEocaYJW3NfoGngHoqdXYjAxUORw6MPZNsAo/13r+fiB4rhHiFMaZu+EmL1vqzzrl9lFLIs6sTNXSTBtBaH4jMZhBUKKUuaeAMA9EUlsl/KaVOzEcHpQAgot8KIWx2CdFanwwCLGstbARtlUI0gNYaARrnGWPqhEgoUOGIcxBCII/vv3wBiaHkVCkljnoHxXGMryX7hSAQBEfIxSm5Q5cBAO5fOLTWCiEutNaCMGpzQe5hEkOwIW/oymoAOIpASpVdQsIwhM3gFXMJALcmR5nNAIAKl1Jq59wzJ1kCjvbef1IptU1+jU8E8Anw8TDzE4jo+XEcI0AkBdecdwaNjIy8wHsPl/YdzHy+MQZm3yzA62xlCIJl5rdnDV05AMAK+OvsEgIApDyGbX3+BS4BQPnXjDGIYKkXrTXYsVYw8+6TaACcgwESuDtB2oR8/M1Fa/1R7/2RUkqcJOAXX9VNANBa7y2EQCby34noHGst1u2sBjiImcFc8m8p5Zsa2EHqm0DnXEUpdY0x5oS08lzVAFgCNhszoij6NDODZnXXSQAAQ8mXESLWiHQxiqLTnHPHSim3zQdbdMkmcE9Qy3jv71VKfSaO41NzGqC+SUQIGMLjsoaurAYgIpBjXRHH8XszH9fc2wMw8xeztCta60/CkYNd/CSbQBhK0nCn/yJ4SDY6sBUsBM9vHMfYDXfNEpAykjLzA0T04ax2TCYYoWL1o20j6rnMMfD3RIRNJIw/qXY9WQhxiDHmqXNiCYAdIB+1A2pXKeU+zrlwEg0A92jd+ENE++XDo2HsYGYMVAohdjPG3NhNANBaI8kFbCGeiD6QPSJjHAiHZ+Z02Wt0Ckp9Abd47y+y1ta5hZK6H3TOHT6X7AB/zkftaK0/wswvQij3JJtAbILqDF95Ns/kC4G9u76n8N6XK5XKWDcBIAiC3cEXlPQZ7u4top7DMNwfsZLJ+LcwdGWXAOfcrUqpC4wx+BhSACDc7I3GGLjS2yqFHAODIPiHlPIkYwzi/OolIXQEy/bSSZaAOh9v8vg+xpgrsiMJwxDqv25ZlFKGY2NjlW4CwMjISNl7/7tkgt/VwNRbZxTD70R0YBzHP82OP2MI+hMRfTvLMIpoI+/9CePj4/VMo3ZKUQDYqJQ6JuuyhbXKe48wqF0m0QD7EFGdwzf/hScAQsRMPVDUe/+USqUCY1O9dMkmEONekwDgmHzE08jICGjj0n3NFqcc1MnsAe4gIpywNh+x4Up2zp0+Pj6+bTuTXwdfuw2ARnVgYAAXKGxhrUvW8MO894sbAQDZP/fccw/Os/9McgK24OADNXu1WsVG8pY8p183AACyTfz2T3LOvTvNhMrIG7+fwMwP32WXXU7Jk0hnNMCfiejLOTN7fQM5MDCw6Prrr/93O3PYNgBSJCMWMJuylbgsQdW+EzMfMR2qtekOpEsAMN3hNHwuAQDiJP+W97SWy+WnOufAkdiUOr9ZJ9oGANyezrnzlyxZsjCLYq01bACrpJRIA4vGxsZgLCqkzAcAYJKr1SqYyvvgFLPWwidQLwmL+oPM/HJrLfgJWy5tAyBNcIjjeKd8L6IoemKpVHqwGcHy1vZ+awGAmzvwjtHR0ep03rU1z6eJIfm8gOm8p9kzyCyGoaxRoqjWGvcVnJLdeDdrr9HvbQMg4d/fzxiDoMUZKdMBwMjIyGKkXiUJm/XIGe/9H5RSyDP8VP4WsCAIHkZESF87FA4oPJ+wkf7Me//57CY0O8hOAmAqYQ4PD0+USqXvtXv/QNsASPz2w8YYWP1mpKQAyFsI8fKETv5U7z34+W+TUsLhYpCdA4MSoo9wtu7v71+ZgiBh+rwWN4RIKb8K79t/5p/BPgq3Lf7/9Gq1enKeCDIIgudIKa/thAaYSphBEPxaSvmrrI+gFeG3DYCEtXsHY0w9NXomSjLJuA3s6KwTBVz9iXFliVLq+KGhoa/md9cJYcPvSqUSIpjqt3fA7yCEOKJara7IE1LgtHLvvfcixw8nktVKKZzZ/56OM8l+PqOvr29wuktMETIKggB2k1sqlcpb2mmvCAAgnHswn/zYTqemUzcJGXsZMx9WqVSuHhkZGfbe/9h770ql0n5TbTrTtDRrLYinceYeQ/h11uGS70MQBEuklJd67+9OjrU3aa2f773HHYPfyYd8TWcM7Tyjtb6UiG7Phoq10l7bAEDwJzx2Mw0AkEY75+BLwJkYhFILEH1TKpX2bnY7F5YtkExba58FoYEZnIiuySdoNgAB4hAQl4BYvPo7EewxODh4+ExTvwdBgEysO40xCKxtubQNgORyBywBuAFjxsvw8PCwlHJIKbVubGwM/oJmlzoMeu8nEh99PRkjcT2/BjeKTIN1RGqtywhUEUKsSe8ymumBg0BDSrkmH228tf0oAgBnENHwVJcwbW2nOvA8RVEEWvkD4UUTQmwaGBjYNcPjMyiEqDubkKUDL12lUsHdhFOCqQP9nHaTWmvcpnZ9PtZw2g0kDxYBAHip4M/fdWtf3unncbSDj4KZkZSxMwimlFI/qlarH85v9srl8g7VavVEXDCtlNoBR0YpJTJ7vjQXL47WWuMuAuQUbo7CakWebQMgDMO3MvMJlUoFdChzpmitX+KcO5uIBonoG8z89UqlAmtas69aRlG0OzO/DreFENE/AKDJLpqerQEPDw/fJaU8Je9k2tr+FAGAlyK40RiD2L65cDsGnCxY2xFP8K2+vr4TWrVE4sjovf80rpJPrG6Ixm0GoK2dg61+PnGkYRN6QDZcfqsbKsIbGIYhQr7GarXaTqtXr0aK2KwWBJMKIRBLgHzDLVLOW+1YFEVvYmZkKp2cj+1rtc126iVWTixRTxsbG7u5nbba1gDJcQxcPvvmgzra6VgrdcMwhDYCtcyRRU1+2o8EBMjVP2C2lwNEEzHzj/v6+ha1a3xqGwAQkNZ6DYIW8nFvrUxiq3WSq91vllJenM2kbbW9RvW01shg3ts5t7SBf7/IV03ZFgJmcUNZERRyhQAgMQZtN1u2gASEX2VmXAq51BgDM3HhJQEZonSR6bNFunfhL5uiwcQG8Md2rYB4RSEASDJdz3nUox613UxbxDCITAj2tK6SbWeyMFacKEBtPxschTjaSin/mQTZNL02t9lYCwEAvoxNmzb9jYhemg9ubNaBdn9fvHjxgkWLFiF96g5jzAtmYJeOU8ZVzPzIUqm0ot01eGvHH0XRQc657wwMDGw/Datl0+YLAUCigsFgxTO9DCTBp+/p6+sbHh0dRY5ix0sSkmWJ6EMzve9JOJQ3GGNeVsRAiwQAwpwvRiLI+Pg4Ll3ueImiKHTO4YrX92f5iTr+4v+EvZ8Ivj4QXs+UPyANNc8ny7Yz3sIAkETBIsz53plQxQjiKJVKCNy4e5dddnlu3u/fjlCmUxdhY86563AFjJTy6TNgLoYTCp5IaYxZWdRSVyQARBIhjHSo4/KUJ9MR6lY8I8Mw/AERgUU0mqnLFfL9Gx4eBkX8GOIEjDGHFTUpjeQAennvPez+u2ezpLZCZg0fLRQAyY78vSBAVEq9Ls/81W5nk/rw7H3OOQcixn2ttaBbn7WSsqQqpc6M4xiEz4WbihPOwS8x8/H5exXaHXjhAEiWgk8IIU4gou9KKY8sSj0mNnBcILEHsmOttT9oVwBF1Nda4+sHI9jlQ0ND+xW1HCXL3NdBqIkM4ziOkSBaKMA6AYC6TLXWa0ECSUTg/AcZ9Gaq11aEnhz3cNIAd/DvjDFPb6WdTtXRWoMjeVgp9Qsp5QHtHg+R/uW9h1ML3sxbi6CDaTT2jgEgiiJQmR0hpfw+M/+9XdWVRO4CRE8joounit/r1CRP1S5IraCZiGjtwMDAEe2mbCFtTAgBcgzwL38hnx5X1Bg7BgCtdT37tVqtPmJiYgLOokJKwkv8sfwFE4U03kYjSJAhorcWkbKddmN4ePiRSikEoSLOEZSxhZeOAQBXqDnnkA4WGWNMQT2nIAg2SSnBkbcFrVxB7bfcTML7B8rXR7TcSK5iyjHQSVd7xwCQ5q81IoBqVUBpJnKRhpBW+5KvhwszYAirVqsL8skjrb4jDMNXgm42n3fZanszugfAy0AcAXqUdsOW0o6nl0UR0Yo4juvkC3OlpCliRPSYbOJIO/3TWr8dpyljzI7ttDNV3Y5pgAQAt0gpv54lN2hnICMjI0u992DeXDo6Ovr7dtoquq7WWoMWNp8m3857EpaVA4vw+0/Wj44CYHh4+Le4BjV/IVKrQsnYwp8Ux/G6VtvpRL0wDOuMIM65oChfSHqzmjHmOZ3oM9rsKAC01lfji203fy0dPDgHhBDX1Wq1x861S6XL5fJOzrl1RS5PSd7lTvlrZ4sEQ0cBEEXRZcwMP/1mrvt2Oh8EAa6PgUPkkZ2K+mm1fytWrHhstVrFDWDPNsbAH9J2SULQtjHGgHi6I6WjAEjy1/6WvRm8nVGk/LtF2xba6VNat1wub++cQ9bwfxE+tdp+GIbfJaIFRfn+G/Wj4wDApUj5G7NaFUh61BocHByYjdCzqfqN6+1x+0eRRhsAQErZH8cxLtfqSOkoAJDCLIT4a1EaIGXXVEr1t2trL1qaqY2iEellq+9KL97qZOZ1RwEAEgMp5Z+K2gOklzAMDg72rVq1qtaqYDtRL/FV3FckAMIwPJ+IsN/pWOZ1RwGgtb4GrBoFngLqt3DMZQBIKV84NjZ2WREgC8MQV8rsZK3dp4j2ZnwPADsA+HOaES9Md3CpBijS3Drddzd7LtUARQJAa/0FIgriOH5us/e3+nunNcA4UpiyTNetdhT1UoLluQyAIpcArTVuTcFtKeA26EjpKACiKALR4Xn5q+FbHQk2WosWLdonf79Qq+0VXA/5AgfhgqiiIqC01u9ILoeqcxl1onQUAGDzWrVqFdKYCw1j6oQg5mibtHjx4v61a9dChh0pHQVAR3rca7RQCfQAUKg4u6+xHgC6b84K7XEPAIWKs/sa6wGg++as0B73AFCoOLuvsR4Aum/OCu1xDwCFirP7GusBoPvmrNAe9wBQqDi7r7EeALpvzgrtcQ8AhYqz+xrrAaD75qzQHvcAUKg4u6+xHgC6b84K7XEPAIWKs/sa+3/hP7FiZR5MZgAAAABJRU5ErkJggg=='>";
                            echo "<h2>Du er oprettet hos Yourpay!</h2>";
                            echo "Vi skal dog bruge lidt mere information om dig, inden din webshop kan gå i luften!<p />";
                            echo "Log på dit Yourpay-kontrolpanel:<br /><h3><a href='https://admin.yourpay.dk'>https://admin.yourpay.dk</a></h3><br />for at gennemføre oprettelsen.";
                            echo "<input type='button' value='Luk beskeden' class='btn btn-success yourpayclose'>";
                            echo "</div>";
                        echo "</div>";
                    }
                    elseif($completed == 2) {
                        echo "<div class='yourpay setup'>";
                            echo "<div class='yourpay setup-start'>";
                            echo "<img src='data:image/png;base64,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'>";
                            echo "<h2>Din Webshop er godkendt hos Yourpay!</h2>";
                            echo "Vi ved alt hvad vi har behov for indtil videre - du kan derfor gå igang med at sælge på din webshop.<p />";
                            echo "<input type='button' value='Luk beskeden' class='btn btn-success yourpayclose'>";
                            echo "</div>";
                        echo "</div>";                    
                    }
                    else {
                        echo "<div class='yourpay setup'>";
                            echo "<div class='yourpay setup-start'>";
                                echo "<form method='GET'>";
                                echo "<input type='HIDDEN' name='page' value='yourpay_setup'>";
                                echo "<img style='min-height:128px' src='data:image/png;base64,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'>";
                                echo "<br />";
                                echo "<h3>Jeg har allerede en Yourpay aftale, som jeg ønsker at koble på min WooCommerce Webshop</h3>";
                                echo "Indtast Merchant Token:<br />";
                                echo "<input type='text' id='merchant_token' name='merchant_token' value=''><br />";
                                echo "<input type='submit' value='Gem Token' class='btn btn-lg btn-success'>";
                                echo "</form>";
                            echo "</div>";
                            echo "<div class='yourpay setup-start'>";
                                echo "<form method='GET'>";
                                echo "<input type='HIDDEN' name='page' value='yourpay_setup'>";
                                echo "<img style='min-height:128px' src='data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAa7ElEQVR4Xu1deXxU1fX/nvsmCyK4oeivrVZRATMzAbGuZLIhKkomWMG1Wq3VutWf1UISUNMqJKhVa+vPre7aqlHJoqICWUHcUsjMREWxLrXuaAEFksy75/e5A4kzkzczb95MIGTm/TlzzrnnnPt9993lnHMJ6SelPUApbX3aeKQBkOIgSAMgDYAU90CKm58eAdIASHEPpLj56REgDYAU90CKm58eAdIASHEPpLj56REgDYAU90CKm58eAdIASHEPpLj56REgDYAU90CKm58eAdIASHEPpLj56REgDYAU90CKm5+0ESCnYNq+mt92jQ7eT5BW422rrQPAKe7fQW9+UgDw04KC7BH+3VZBYFyfxYwXOYMu9zXWvj/ovZDCCiYFAE5XaSWDr+/nR4kuaFiwkdbf9GFz85YU9vOgNT1hAExwnXKILjUvBLIiWcmS3oMmL/O11C8ZtJ5IUcUSBQA5XO4XAUw14z+CfFLT6XerVtR/aoY+TTPwHkgIAHZX6SwCPxmnmhsJfO2eYsOdzc3N/jh50+RJ9oBlABx81Ekjh2Vp7wDafpZ0Im6nHm2a55VFX1riTzMlxQOWAWCfXHo7Cb4yQS3u9LbWXZ6gjDR7Ah6wBIBcV8lECX4TECKBtsGMpb62uuMTkZHSvJWVApXXM0CW91ssAKBSOFyrXgFwVMLOJ5rjbam9KWE5fQIqhbOg/UhmMVUyHS7Ah0BiTylYE0zrQfgXgVZC8mLP8olvAJUyeW1vP0mO8qZxBFbKn6ZB/0AXtjN98wvftKJB3ABwuNwXA7jbSmMhPIy1G7X1jmTsD4wrnrFXRo9+CUC/BrC/Sd0+BuMh0rT7PM3PfmKSZ4eSOee1HEh+/3XMdC4Efhh9GWv2zCZ7c2Vh3JPquADgPHbGPrrwrxFC7J6oJ5h4aqL7As6CU38s/fIaFvxrAexiRScp4RcaPwHi+d7mhnesyBhonty5y37ELOZJXb9QCGEzao+JzvcuKHwoXl3iAoDd5X6YgHPjbSScXu0HeFobzjCSk5tXeqTOOEsAP5EkvwDw/Chtw0tBS0ayF5RMIp0ulaSfI6BlJKrPVn4pGWKOr7XuluTIS1yK85qX9iFbRhkTLgUib7SplnTID5H1zdjOylnd8bRsGgCOvBn5INkcj/AItBttOo8L3wwqKCiwfS13+zMhYGzIQ4yPJOF5Ih4JyceAxJgk6BHaBnDHpuHds9cuXtyVbNnxypt00ZsZ/r02XqeDrxLAcPP8fKmnqvgu8/QwVyAiJ2dmpthjy2oIMT4e4cZDFa70tdTdEd7HDpf7UQBnJyo/hF/icxbyYwL7Wdr2ZugHCSG0YBop5XoSdIGvtf5Zo7Zz5izZX5B2IjGOgMCBEhgpdO4hm/iUJTqY9EZvVfGriczEw9t1lDf+gYDr4vUFA5/uuiX74JW3HbvZLK+pEcDhcpcBqDIrNCKdjlV7Zaw/MnwH0OFyzwFQnbB8df4s4YNG94K51tda9+9gmTkFM3fVZFcRpDiPhV4K1tqJ9NM9rQ0fBNPNnPmUtmbMXrMIdDkTjo2pF+MjCNyJ7p7/89xywvcx6WMQOMoblxFQZEUOAVd3VBXdapY3JgAmFJT+tEfqbwmIYWaFRqBjwXR0R1vt68H/2wtKjmA/vxr+Zsbflv4KoM33ttYtNhOHYC8qHcNfZPy7s7Mm5Js5oWzpzyTTIxD0w9G2WWUkviDiykPfX3dfTc0s3Sxb/xFg2TwC3WCFX5L8useffdCamyZvNMMfEwB21/R6gphuRlg0Gma+y9dWH/J9P/ikk7KGbcpsByPHonwGy8WsaTf7mmtbzHR8tHacFU1XQuebIJBpUZ+tbMRvs07l3oVFKigm7ueoyhdGbu7KVqPSnnEzKwameZ7qwvlmeKMCwJE33Q0StWYERaORkF9mCG3s6uba/wbTOVylNwA8z5CX8TsW5AfzWQR5ZNCu40ZmvAbCSyzlU53LGz5OVL9Jc5bs1gPtAQicmqisUH75JpO4bWMmPfthZWFIPIRa08Mvixn40FtduLT/KNA4m4CFVvSRwHrijAO91XnfxuKPCADn1KnDecuwt+LYWInYFjP9wtdW+1gwQc7kGbmAfFMI9FvXSin/3rm84ZzeNzqgy6YRo/3Em99uc36RzB08R1njJCI8BeCgWM6y/L+U34G0NkAqf9qYxLEE/QhABPzPoGpvVWF5sPxJlQ279Gwe/i8IjLbULvF8z4Ji45crSGBEADjyShaCaLalxoMbkGjyLK8rDh6e1ZJvnb77KyD+mYH8TzVBOeGjRaJ6GPHnViy7gnW6JeEhPwnKsURp+CcjoB9T+IrJVGsS+F709BzkueWEqKethgBwFsyw6365yujtNNX6NiIJvUcIcobvsDnyS34Hpj8ZyWLmU3xt9c/H0068tIFv7Oas+yHotHh5B45efi6ytPGrKwv7PpMHX/FC1i7Dst+Dhp9YaZeBW71VRVdH4zUCAOVMLmkRgvKsNBrMw4wFvra6ucG/bV1VcGeErdvHva11augfsCe3rGUik14DIOmbSYkqzcA93qqi3wTLcZY1XQji+yzK7iLBYzrmF/8nEn8/ADjySn8J4gctNtjHJnX5YfYW5LS3N2wK/iI48qY/BxLTwuWzxNdZNjm+vbnh60TbjsTvLGu8FAS1Ro4YvzhQbZuSKyGZcKS3uqi9l17tCvbsteFtq4CVwF2+qqJ+u6u98kMAoE7VbF3yHRIYZUrhKERGQ7kzr+QMJvqH4dBPdK6vpVbtBib9UUP+pq7s+wiYlXThSRZIjFc6qouOCxbrKGs6m4hDJtFmm5VS+kVGxqGeG/NDNrsMAeDIc98LgjpSTfRZ5G2tC1lSOSafvAdge9twVitpiXd57QmJruONlJ5Q0TSB2V/D0A5O1Kjtxc/Ms7zVxeozFXjUzuQ7B43ykMBhFnV42FNV9Esj3r4RwJE/4xiwVIEeCT1S4nsSGB++DetwTb8bECqWIPSR2MKZZB+IBJLc8qaLWeq3Q4jshIza/szvZ6wbOb793iN6ept2ljX+HISnLamiPi2CcrxVhf2OuwMACCzLeOSbYMq11EAQEwO/Dz9SVUe8kvhVtUcWLp8Icz0tdQsSbTeYf+zs5SOytO57AJyZTLnbUxYTXe5dUHjnD20yOcob2wk00YoeDDzlrSo6vZ//1Q/2vJLfEFFcx4hGSqiDmKzNow9vb7+3D7kBcPXs9jo09Fdcyrflt9kTwvfjrRjYy2OvWOpkFjUacGgicnY4r8QX0HvGBB8uOcobpxFgeYmsS0zoXFjUEWxb4I105LtrwEh4TSyZJ3e21a8IbsCe7/4tMf5s7FDO97bWtybL2c6KpjMk6w8kenAlAZ2I6gXL15loJDOfaPXNS9C2uZ6qoqDRkclZ3rwC4GOsyJVMDb7qwpJ+AIiY2xdXK/SAt7X2V8EsKmNYyIw1AEb2E8V40NtWd0FcTUQhdpY1nQjm50Ni5awI13mFsInLVy8oXB3Mvu3tU6Ok2ZhDK62H8Kg9fc7qPqiz8sRvev/IrVhayCwaExB+tKeq6LVe/sAIoM7Jhex+xmyKV//OlOsyNYwLX8NHDiGT32QKjE3Wmj+n8qlMbdOotVZ3zAL2qCFX0BxPVcEjkYI7HGVtexB310CQ2treLg8RL+xYUKziMfqeROIFACz1VBX1heIHT8pUnt+ZkLg13gMIBv/K11r/QMjQ73IfS0DI56Dvf6KLvS219ybLg7nlTacwuMGKPDXca6C7bNI/r33h8etjyQiArWuUWqKFDKWx+Kz+LyU2saQxnTcXft4rw1G+9BiCsLxiE0yFq6sLA+F9/WblEwpKd9cl1DHtZUb/hxuiOtnTOtEVekJXKRz5/3wdTJP6jxb0hrdtwtFJPdErt3Z0qjZdSNBl4cN9rM4qqGyyfdPFfwNwXizaZPxPoL92VBVeETIKlC19jkicbEm+zis8NxXlqZEu8mng5NJJEKzi/4+I1IiUUhdCTPS21nmDaZyukvMYZBSibBgVZMmIIKZtW7xBS6boEiXwpQDNjjbcx9aJyVnWeDOIoh62xJZjgkKiW7PxoavmF3/US73tTOOfJrgNSZh4mndB8eKoASEzZ87U1nzZc5Gu61VCiN0M3v5bPK11vw/+PVAtRI54FxD9TrAIfL+ntf5Cq0pH4jusrOlgG+mqzaj2xDvcm9Ezt3xZGYMSj5eM0RgBD3ZUFYVMmp1zlqn5iKXVm2Q84qsuOi9mSJjSy15cMpr8uAVMP5zUsXxfatkTOptrvgt5+/PdlzLD6G3cQH5xyEBlAzsrmu4Cc8hJWrBeVod7MyBwli+7BOA7YwHQjKyIo61amkptondhft9oO7F86WE64LPULqHKs6CowhQAepVy5rvzwDgLRN/q1H1HZ/MLfROTbTSU4yp5R4D6bcIQ82xPW/3NiTghGu+2yZk6xTwrhE7yOyD80VNd9EQyQ7fDdcmtaDpHZ35IACFh58m1l1Z6qgqOC7bDWd74CIBfxNUO0+IM9p+pJr1xASBWIypVi6UeEood4JH4fKNt/YHJyAOMpYN9btMRQsrJzCrlC693zC8OiUKOxZ/I/7nljaeyxD8GNsIoNPnDPm/ZGOi0xgzwAqMgaG7vCkDZmlwABGL3hq0LrxckITdnZ2bv1760JuYyK5EOGAy8EyoaT5YMtacyIDEHUsr/ZojMQ1dVub7qG5nnNN4LEfUUt31rpHDBS+GjYFIBoBRyuNwqw2ZGv84gvtrbUm86YWEwdKZVHXLLlk3ViWsT3ZKO2D7R3Z4FhZf0/m+f2/IT4dfXho88DHQK5ms7qotqI33+kg4Ap6ukhEH94+ElPs/cLMeERQhZ9fGg53OUNU1h0usHAgQSsieLMg9oX+D6rG8UqGj8PRiBWgsEfa0k7fpx7339ZKwElaQDQJ3+faPvtpYJB4T3EoH+4GmtrRz0vZckBRUIiPWGgYhHIMLpHQuKVDh73+Oc1zhW6Bi1exa9ZrZWQNIBsO0zoHYR/9rfj7JbspjY2Van4uNT4smtaDyBGWpETN6cQEIKojGrqws/TNSJAwIAlfK1y3eZa4xGASmlN3szjk6VT4HqIOfcZdOl5GcERFJqGTDRIu+CwqRkMQ0IAAJG55X+nImNQ5gIT4/bJ/OMmpoaywmUiSJ/e/PnljWdzsx/T/i4GpJBdIRnQbHlbeBg2wcMAGou4shz14IinJox7vO2TfxNMg+FtnenxttegjH+geYk+AlfVXHSQt0GEgBbt5C7aDUE9o3grMfluswLkhkSFm+nbG96R1nTHCK2VAuBJTbY2D9+1U1Tk1Zqd0ABEPgU5LvzdB2NkdLMmHm5Dtust9ue7VvSbO9O2d7t5ZY13sqEq+Jt12ohqGjtDDgAAiBwuc9l4OEIimwE07nettqE09DjdeiOo1exfU0qQaZflG4knXpP75Kt83YBQGBpmFdyOYj+EmKAqqhhE1M8zYt8yTZssMsLJH4OH7YExLFzMAmtmzZumbr2L9OSXsBquwFg68rAfSGTvGdrsQfZLQmTO1sa3gjuLOWYXUdkT5HMh4DE53omnuusLAw5ch5snauqpkPytSQwkoCnhvdkXrdyZU3MQk05lS/uaevSXouWtSSB14dnbTn+tcppGwbC7u0KgAAICkpOZElPMvhWX2v9H4KNyq1onCV1eTsJEVSBXH5OgtxGp3q5cxpzWGAOSzmFgWFCQwuEmOO5sUhFIm+XJ3fyjEOl8L8dUjeZsVLTaJqZGge55UsP1SVei1B8c2mXnnmq2Xo/Vgze7gBQSh5WUHKwLXPLZ56XX+6rqOWIEtenQrg4q3t8cHi0s7zpPEj97vBt1kAotUR+eAKEFeeY4bG73KcT8EQ4LYFXa5w1dVVbTd+pXSR5Ww+P6IXgI10VB7hHFq4yu6VrRlcjmh0CgHBFnOVL1XewJVpkC4Ov9VYV37i1iOKG2xhQ282Gjw68u3cW5Qy08wJzm4Lp4yCFSt/u/0i8IzL1KR2Nz0XMz+9lCnoB1oFxsae6SB0pD/gzKABgKs5d8jKp8dmAeFowJsfyjGB55OrqKSHzi1g8Vv93utx/ZuC3Rvw68IENsji8FqERraOi8Srq7nk8vKzL2ONKRmSD9+9Y0dBpVcdIfDscAIFETur+r4kt0o+lhCYEfmTGCQwUeKuKVOm4AX8CwbNfdN3DoJDMqKCGP2WIKb7WRcYjRRQN1aVcPZKeEUIcIJmnhafeJWrcDgeAo2zpQUQiqXcLqgikDGQeIKnnDJZiJsD7MLDCJv3XJnMXLdT5lcLpWnUnA4aBqaoCiiZ4akdr/SqTnUZOl/sXvPVUdYTikcAmIeD2Ntf1KytnUmY/sh0OALXsy94l+xshrJV7NzKcQEv84APCM4RVLV2N6bhkHKNGcLjqtNsjfQ5UXWKh2U7ytixaGa3DVHKOZPkoM51iMLHoJhKneVrqLGVChcvb4QBQCuVWLKtmJlUvODmPhIzySXnUU1WUcMn7KIoqENzEwDVGNKqAhiZkiae1IVqCJ9knl94W6U6mwB0HJE/ztjVYqkQarNegAIBKtVrXxbeylJf0XoigkjjMRLrGixhVS9e3YMre8fIp+kmTLspYb/v3sLWvLY61KUN2l/sGAkIqpPW1KdHFEKf5li96LhqQ7PnuhcQISbwJkSFQ5Guts5wjqGQNCgD0GjXp6qZRPRnSCeIelmJPEkj++YCE9Cwsijt235HvPl/q8jaVIUUSTTKTfh2rrI0j330dGCGbXb22qreYBM7xtdZFu3cxKpCk5E+ys7PsiURbDyoABL8NiVTGivZ2q0hZb1WRPZ4RYFLB9FFb/Pg8uKK5mtQJkid42hqiBmY4Xe65DNwYoT1m8IXhmdXhtNGApMrse1vrQsrMxmPboAVAzpzGXE0gpEhDPIZFomXwRd6q4rgKL6rKqSxlSALsNvkbAT4lVpUTu8t9DQERs6KY6Le+ltrQg7IwAxx5pdeC+I8Gk8Jv9hIbR1u9hXXQAkAZai9f+qKAUOXjkvNIPOtZWHhavCli6sYU2qP7P0b1E9WSk6CV+lprX46mZPRSOapAHl/hbas3CKTtk0oOV+mTAM80aMcZnqFt1mGDGgCqjHu30J6ioMup1SRO6MiGELuaNTKYTjK/RxD3y+yvbovngiW7q+RUAtcYX5Ypuwk009NaXx8VBDGKcTG40tdar95yw4sgcwpKJwjJ/fYRiGmKp612mRV/DGoA9BrkmLPMRUR2ED4B+FCAEk4yJcm1HQuL+2cwRfGiw1VyNsCPGIFA1UrQiM7xtNX3OxgKFml3lVxAIFVcIpLvH8/8Xl5kFDXtLJh+NEvRbw+BJI72LK/rq/sTDxB2CgD0GqQqZr598KhPBbBPPEZGojUqmxZLrip3qzM/FuGKGwbxxd6W+qhzDHte6TlE+sMRr95l+T5Dqxzhz3imN65gYt7MvXuo50UCHx4yokn4/cx7rllRb+qKmHD7dioAbM2HF0k7EAkvyRqr83v/d+a7p7OOmvAk2B++1rGvxI0RJhcQFai6SlgJUkfccopRkQ6wfMHb1mCtVMxg2weI1QHJXxlIl6dqSlvoED1jPFjPJxZveJfX9lXtDtdt2z2Kajs2sE/f782S/CfP8sNnRwt7d+a5H2GKM7e/X0uJ1VrcqUaAY656Zdj32VvUPTgJp1lJyV/xsHU/Dp4IqomeilbqjWBmYL6vte7aSJMyh6qjRPpLILGXEQiY8MR3tP78SHURIibSxnoTtv1vdBGXSdYfBqt4GXY0vaNs2aNEQaVqghRSWbNm06/C79dT27zdu3z2WXhnbnXy4ZdHepNzj5ueI0ksjZj7wFhJJM82igeIemlWTEdTTeb3+5wdXJY3JovRSGWFaUfy9F6rytCvBMTW4Vdd3Ai6EaxfBiEmxNKPgZfHrf16WnDqdM4xM/cUGd3rIvA+nvn96PMjOVtFBbFftEW8Z0GiC0I+BLb9Y/OuW17N+nhX1kb1/ErX+Y54r+WR4K80okpPS52qWmq4XIxlf/D/O9UnIFjxn1Y2Ze+2RYwH9XzfUVX8ntrccVYsewtMsa63fXL4luzzja5Xtee5lxBhiqEDGfUbtfWnRxrO7S73sQy9OdZl1mq5CMEyCt2dzHwvQajZvgOC94fOkjX6SDAv3yA2vJzMUjs7LQCMOsk5p/FvEIgUldPFRFeHlmAPlaJqG0PaWoyKXG2lpMZuXZZGWnLF3O2L8WpK4D+aXxw+UJXUjJofUgBQ9QIF8er+N26zl9h2Xkd1fsxonInHlfxPt4amSCBQ0b4Qtume5mc/CXdoYD6gCYtJLrIboONjnSvEM7yboR1SAFAGO8ob8wHcSYC6IsZHoPv2yML94RHCqqKZkN3DVrc+9164o1RSK3fxEiGEw8iJ6iZUjenKsftm1fSmuOfkuQ8TLJ+2csO6BL4l4tN9LfVLzHRaMmmGHADMOCeQnKLzY5JhI6Kzje4pVEfA3VKo/XVnRJmBCuPoYMjdCUJdgmngT/0zSI2MVgmBEDEhHpOi50aDmotmTEmYJqUAoCqXZG/KvCEsyoYJ9MexozNuCC9YkYQr9JiEPNbT3PCq+rT0aMIuwPsy8Wbo4l+Zm/fxJLqMSxQBKQMAZ970w5nF4xAwvBY+EOUjcF7vZVf2vJKTmflxw+1Xs14nXO9tqTM4wzcrYODpUgYAkyZN36V7GD0IQRHvDgyEXQMvMbAfAUcn4n4KbAXXq3i+hNfqiegRizdlALDNEeTIK51nHFkTy1XqcEauhyYWgTErwtW3qoTLu4J5djIidmNrlDhFqgEg4DFHXmkpWH80nqASFcSp2Xi6p7n+xcAVO/qWYpB2BEHux0ySwR9omtbW0Zz7ys5U9yglAaBAoNbsfpt4RjDGxnyPpPwOmjbL21K7OCbtTkaQsgBQ/RSYFwzXygG+MuKxLvFzUhP/GysEfCfr9z51UxoAvV5Q2bcZGk0jwlFSl6M1QZuZ8JbQtec6li96d2ftXDN6pwFgxktDmCYNgCHcuWZMSwPAjJeGME0aAEO4c82YlgaAGS8NYZo0AIZw55oxLQ0AM14awjRpAAzhzjVjWhoAZrw0hGnSABjCnWvGtDQAzHhpCNOkATCEO9eMaWkAmPHSEKZJA2AId64Z09IAMOOlIUyTBsAQ7lwzpqUBYMZLQ5gmDYAh3LlmTEsDwIyXhjBNGgBDuHPNmJYGgBkvDWGa/wcRKmUX9u9YQQAAAABJRU5ErkJggg=='>";
                                echo "<br />";
                                echo "<h3>Jeg har ikke en Yourpay aftale, opret den med det samme!</h3>";
                                echo "Virksomhedens CVR-nummer:<br />";
                                echo "<input type='text' id='vat' name='vat' value=''><br />";
                                echo "Kontaktperson:<br />";
                                echo "<input type='text' id='contactName' name='contactName' value=''><br />";
                                echo "Kontaktperson telefonnummer:<br />";
                                echo "<input type='text' id='contactPhone' name='contactPhone' value=''><br />";
                                echo "<input type='submit' value='Opret aftale' class='btn btn-lg btn-success'>";
                                echo "</form>";
                            echo "</div>";
                        echo "</div>";

                    }
                echo "</div>";
                echo "</div>";
                echo "</div>";
                /*
                */
            }
            
        }
