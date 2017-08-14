<?php
/*
Plugin Name: Yourpay Payment Platform
Description: Tired of paying the card fee per transaction? Surcharge it today!
Text Domain: yourpay.io
Version: 3.0.61
Author: Yourpay
Author URI: http://www.yourpay.io/
*/

add_action('wp_enqueue_scripts', 'wptuts_scripts_load_yourpay' );
add_action('woocommerce_cart_calculate_fees','WC_Add_Yourpay_Fees::surcharge_fee');

function wptuts_scripts_load_yourpay()
{
    wp_register_script( 'yourpay-script', plugins_url( '/js/fees.js', __FILE__ ), array(), null, false );
    wp_enqueue_script( 'yourpay-script' );
}
class WC_Add_Yourpay_Fees
{
        function surcharge_fee() {
            global $woocommerce;
            if ( is_admin() && ! defined('DOING_AJAX') )
                return;
           if(WC()->session->chosen_payment_method == "yourpay" && get_option( 'yourpay_token' ) != "") { $PaymentData = new WC_Yourpay2_0(); $request['function'] = "customer_fee";$request['token'] = get_option( 'yourpay_token' ); $result = json_decode(json_decode($PaymentData->v4requestresponse($request))); $subtotal = $woocommerce->cart->subtotal; $fee_percentage = $result->cardfee/10000; $fee = ($subtotal * $fee_percentage); $woocommerce->cart->add_fee('Kortgebyr', $fee, true, 'standard');}
            
        }
}
