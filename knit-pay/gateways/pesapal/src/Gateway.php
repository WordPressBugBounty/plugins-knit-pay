<?php
namespace KnitPay\Gateways\Pesapal;

use KnitPay\Gateways\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;
use Pronamic\WordPress\Pay\Payments\FailureReason;
use Exception;

/**
 * Title: Pesapal Gateway
 * Copyright: 2020-2025 Knit Pay
 *
 * @author Knit Pay
 * @version 9.2.0.0
 * @since 9.2.0.0
 */
class Gateway extends Core_Gateway {
	/**
	 * Gateway configuration
	 * 
	 * @var Config
	 */
	private $config;
	
	/**
	 * API client instance
	 * 
	 * @var Client
	 */
	private $api_client;

	/**
	 * Initialize the gateway
	 *
	 * @param Config $config Gateway configuration
	 */
	public function init( Config $config ) {
		$this->config = $config;

		// Use HTTP redirect method - user is redirected to Pesapal's hosted payment page
		$this->set_method( self::METHOD_HTTP_REDIRECT );

		// Define supported features
		$this->supports = [
			'payment_status_request', // Can check payment status
		];
		
		// Initialize API client with configuration
		$this->api_client = new Client( $this->config );
	}

	/**
	 * Start a new payment
	 *
	 * @param Payment $payment Payment object containing all payment details
	 */
	public function start( Payment $payment ) {     
		// Generate unique transaction ID for this payment
		$transaction_id = $payment->key . '_' . $payment->get_id();
		$payment->set_transaction_id( $transaction_id );
			
		// Get formatted payment data for Pesapal
		$payment_data = $this->get_payment_data( $payment );
			
		// Create payment on Pesapal
		$gateway_response = $this->api_client->create_payment( $payment_data );
			
		// Extract payment URL from Pesapal response
		$payment_url = $gateway_response->redirect_url ?? null;
			
		if ( ! $payment_url ) {
			throw new Exception( 'Payment URL not received from Pesapal' );
		}
			
		// Set the redirect URL
		$payment->set_action_url( $payment_url );
			
		// Store Pesapal order tracking ID for later reference
		$order_tracking_id = $gateway_response->order_tracking_id ?? null;
		$payment->set_transaction_id( $order_tracking_id );
	}
	
	/**
	 * Get payment data formatted for Pesapal API
	 * 
	 * @param Payment $payment Payment object
	 * @return array Formatted payment data for Pesapal API
	 */
	private function get_payment_data( Payment $payment ) {
		// Extract payment details
		$amount          = $payment->get_total_amount();
		$customer        = $payment->get_customer();
		$billing_address = $payment->get_billing_address();
		
		// Build payment data array according to Pesapal API requirements
		$data = [
			'id'               => $payment->get_transaction_id(),
			'currency'         => $amount->get_currency()->get_alphabetic_code(),
			'amount'           => $amount->get_value(), // Pesapal accepts decimal amount
			'description'      => $payment->get_description(),
			'callback_url'     => $payment->get_return_url(),
			'cancellation_url' => add_query_arg( 'cancelled', true, $payment->get_return_url() ),
			'notification_id'  => $this->config->ipn_id ?? '',
			'billing_address'  => [
				'email_address' => $customer->get_email() ?? '',
				'phone_number'  => $billing_address ? $billing_address->get_phone() : '',
				'country_code'  => $billing_address ? $billing_address->get_country_code() : '',
				'first_name'    => $customer->get_name() ? $customer->get_name()->get_first_name() : '',
				'last_name'     => $customer->get_name() ? $customer->get_name()->get_last_name() : '',
				'line_1'        => $billing_address ? $billing_address->get_line_1() : '',
				'line_2'        => $billing_address ? $billing_address->get_line_2() : '',
				'city'          => $billing_address ? $billing_address->get_city() : '',
				'state'         => $billing_address ? $billing_address->get_region() : '',
				'postal_code'   => $billing_address ? $billing_address->get_postal_code() : '',
			],
		];
		
		return $data;
	}
	
	/**
	 * Update payment status
	 *
	 * @param Payment $payment Payment object to update
	 */
	public function update_status( Payment $payment ) {
		// Don't update if already successful
		if ( PaymentStatus::SUCCESS === $payment->get_status() ) {
			return;
		}

		if ( filter_has_var( INPUT_GET, 'cancelled' ) ) {
			$payment->set_status( PaymentStatus::CANCELLED );
			return;
		}
			
		// Get payment status from Pesapal
		$gateway_payment = $this->api_client->get_payment_status( $payment->get_transaction_id() );
			
		// Map Pesapal status to Knit Pay status
		$gateway_status = $gateway_payment->payment_status_description ?? 'UNKNOWN';
		$payment_status = Statuses::transform( $gateway_status );
			
		// Update payment status
		$payment->set_status( $payment_status );
			
		// Handle successful payments
		if ( PaymentStatus::SUCCESS === $payment_status ) {
			if ( ! empty( $gateway_payment->confirmation_code ) ) {
				$payment->set_meta( 'pesapal_confirmation_code', $gateway_payment->confirmation_code );
			}
		} elseif ( PaymentStatus::FAILURE === $payment_status ) {
			$failure_reason = new FailureReason();
			$failure_reason->set_message( $gateway_payment->description ?? '' );
			$payment->set_failure_reason( $failure_reason );
		}
		
		// Add detailed response in debug mode
		if ( pronamic_pay_plugin()->is_debug_mode() ) {
			unset( $gateway_payment->call_back_url );
			$payment->add_note( 
				'<details><summary>Pesapal Response</summary><pre>' . 
				print_r( $gateway_payment, true ) . 
				'</pre></details>' 
			);
		}   
	}
}
