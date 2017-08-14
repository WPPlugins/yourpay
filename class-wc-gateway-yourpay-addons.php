<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Gateway_Yourpay_Addons extends WC_Yourpay2_0 {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();
		if ( class_exists( 'WC_Subscriptions_Order' ) ) {
			add_action( 'scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 3 );
		}
	}

	public function scheduled_subscription_payment( $amount_to_charge, $order, $product_id ) {
		$result = $this->process_subscription_payment( $order, $amount_to_charge );

		if ( is_wp_error( $result ) ) {
			WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $order, $product_id );
		} else {
			WC_Subscriptions_Manager::process_subscription_payments_on_order( $order );
		}
	}        
        
	public function process_subscription( $order_id, $retry = true ) {
		$order          = new WC_Order( $order_id );
		$yourpay_token  = isset( $_POST['subscription_rg_code'] ) ? wc_clean( $_POST['subscription_rg_code'] ) : '';
                $merchantid     = $this->settings["yp_merchantid"];

		// Use Yourpay CURL API for payment
		try {
			$post_data = array();
                        $post_data['MerchantID']    = $merchantid;


			// If not using a saved card, we need a token
			if ( empty( $yourpay_token ) ) {
				$error_msg = __( 'Please make sure your card details have been entered correctly and that your browser supports JavaScript.', 'woocommerce-gateway-yourpay' );

				throw new Exception( $error_msg );
			}

			$initial_payment = WC_Subscriptions_Order::get_total_initial_payment( $order );

                        wc_add_notice( __('Data:', 'woocommerce-gateway-yourpay') . ' "' . $initial_payment . '"', 'error' );
                        

			$payment_response = $this->process_subscription_payment( $order, $initial_payment );


			if ( isset( $payment_response ) && is_wp_error( $payment_response ) ) {

				throw new Exception( $payment_response->get_error_message() );

			} else {

				if ( isset( $payment_response->balance_transaction ) && isset( $payment_response->balance_transaction->fee ) ) {
					$fee = number_format( $payment_response->balance_transaction->fee / 100, 2, '.', '' );
					update_post_meta( $order->id, 'Yourpay Fee', $fee );
					update_post_meta( $order->id, 'Net Revenue From Yourpay', $order->order_total - $fee );
				}

				// Payment complete
				$order->payment_complete( $payment_response->id );

				// Remove cart
				WC()->cart->empty_cart();

				// Activate subscriptions
				WC_Subscriptions_Manager::activate_subscriptions_for_order( $order );

				// Return thank you page redirect
				return array(
					'result' 	=> 'success',
					'redirect'	=> $this->get_return_url( $order )
				);
			}

		} catch ( Exception $e ) {
			wc_add_notice( __('Error:', 'woocommerce-gateway-yourpay') . ' "' . $e->getMessage() . '"', 'error' );
			return;
		}
	}
        
        
        public function process_subscription_payment( $order = '', $amount = 0 ) {
            
		$order->add_order_note( sprintf( __( 'Yourpay subscription payment completed (Charge ID: %s)', 'woocommerce-gateway-yourpay' ), 123456 ) );
		add_post_meta( $order->id, '_transaction_id', 123456, true );
                return true;

                        
		$order_items       = $order->get_items();
		$order_item        = array_shift( $order_items );
		$subscription_name = sprintf( __( 'Subscription for "%s"', 'woocommerce-gateway-yourpay' ), $order_item['name'] ) . ' ' . sprintf( __( '(Order %s)', 'woocommerce-gateway-yourpay' ), $order->get_order_number() );

		$user_id         = $order->customer_user;
                $yourpay_token  = $order->subscription_rg_code;

		$currency            = strtolower( $order->get_order_currency() ? $order->get_order_currency() : get_woocommerce_currency() );
		$payment_args = array(
			'amount'      => $amount,
			'currency'    => $currency,
			'description' => $subscription_name,
			'customer'    => $stripe_customer,
			'expand[]'    => 'balance_transaction'
		);

		// Charge the customer
		$response = $this->yourpay_request( $payment_args, 'charges' );

		if ( is_wp_error( $response ) ) {
			return $response;
		} else {
			$order->add_order_note( sprintf( __( 'Stripe subscription payment completed (Charge ID: %s)', 'woocommerce-gateway-stripe' ), $response->id ) );
			add_post_meta( $order->id, '_transaction_id', $response->id, true );
			return true;
		}
	}
        
        public function yourpay_request( $request, $api = 'RebillingCustomer', $method = 'POST' ) {
		$response = wp_remote_post(
			array(
				'method'     => $method,
				'body'       => apply_filters( 'wc_stripe_request_body', $request, $api ),
				'timeout'    => 70,
				'sslverify'  => false,
				'user-agent' => 'WooCommerce ' . WC()->version
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'stripe_error', __( 'There was a problem connecting to the payment gateway.', 'woocommerce-gateway-yourpay' ) );
		}

		if ( empty( $response['body'] ) ) {
			return new WP_Error( 'stripe_error', __( 'Empty response.', 'woocommerce-gateway-yourpay' ) );
		}

		$parsed_response = json_decode( $response['body'] );

		// Handle response
		if ( ! empty( $parsed_response->error ) ) {
			return new WP_Error( isset( $parsed_response->error->param ) ? $parsed_response->error->param : 'yourpay_error', $parsed_response->error->message );
		} else {
			return $parsed_response;
		}
	}        
        

}
