<?php
namespace KnitPay\Gateways\Revolut;

use KnitPay\Gateways\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;
use Pronamic\WordPress\Pay\Payments\FailureReason;
use KnitPay\Utils as KnitPayUtils;

/**
 * Title: Revolut Gateway
 * Copyright: 2020-2026 Knit Pay
 *
 * @author Knit Pay
 * @version 8.98.0.0
 * @since 8.98.0.0
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

		// Revolut uses HTTP redirect to hosted payment page
		$this->set_method( self::METHOD_HTTP_REDIRECT );

		// Define supported features
		$this->supports = [
			'payment_status_request',
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
		// Prevent duplicate payment creation
		if ( PaymentStatus::SUCCESS === $payment->get_status() ) {
				return;
		}
			
		// Generate unique transaction ID for this payment
		$transaction_id = $payment->key . '_' . $payment->get_id();
		$payment->set_transaction_id( $transaction_id );
			
		// Get formatted payment data for the gateway
		$payment_data = $this->get_payment_data( $payment );
			
		// Create order on Revolut
		$gateway_response = $this->api_client->create_order( $payment_data );
			
		// Set the redirect URL
		$payment->set_action_url( $gateway_response->checkout_url );

		$payment->set_transaction_id( $gateway_response->id );
	}
	
	/**
	 * Get payment data formatted for Revolut API
	 *
	 * @param Payment $payment Payment object
	 * @return array Formatted payment data for Revolut API
	 */
	private function get_payment_data( Payment $payment ) {
		// Extract payment details
		$amount          = $payment->get_total_amount();
		$customer        = $payment->get_customer();
		$billing_address = $payment->get_billing_address();
		$customer_name   = KnitPayUtils::substr_after_trim( $customer->get_name(), 0, 100 );
		
		// Build order data matching EXACT Revolut API structure
		// https://developer.revolut.com/docs/merchant/create-order
		// Using API version 2025-10-16
		$data = [
			'amount'              => (int) $amount->get_minor_units()->format( 0, '', '' ),
			'currency'            => $amount->get_currency()->get_alphabetic_code(),
			'description'         => $payment->get_description(),
			'customer'            => [
				'email' => $customer->get_email(),
			],
			'merchant_order_data' => [
				'reference' => $payment->get_id(),
			],
			'redirect_url'        => $payment->get_return_url(),
		];

		if ( ! empty( $customer_name ) ) {
			$data['customer']['full_name'] = $customer_name;
		}
		
		// Optional: phone - Customer phone (not customer_phone)
		if ( $billing_address && $billing_address->get_phone() ) {
			$data['customer']['phone'] = $billing_address->get_phone();
		}
		
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
			
		// Get order status from Revolut
		$revolut_order = $this->api_client->retrieve_order( $payment->get_transaction_id() );
			
		// Handle failed payments
		if ( isset( $revolut_order->payments ) ) {
			$revolut_payment = end( $revolut_order->payments );

			$payment_status = Statuses::transform( $revolut_payment->state );
			
			// Update payment status
			$payment->set_status( $payment_status );

			// Add status update note
			$note  = sprintf( 'Revolut Payment Status: %s', $revolut_payment->state );
			$note .= sprintf( ' (Payment ID: %s)', $revolut_payment->id );
			$payment->add_note( $note );
				
			if ( isset( $revolut_payment->decline_reason ) ) {
				$failure_reason = new FailureReason();
				$failure_reason->set_code( $revolut_payment->decline_reason );
				$payment->set_failure_reason( $failure_reason );
			}           
		}
			
		// Add detailed response in debug mode
		if ( knit_pay_plugin()->is_debug_mode() ) {
			$payment->add_note(
				'<details><summary>Revolut Response</summary><pre>' .
				print_r( $revolut_order, true ) .
				'</pre></details>'
			);
		}
	}
}
