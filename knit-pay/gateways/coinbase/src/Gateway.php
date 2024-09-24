<?php
namespace KnitPay\Gateways\Coinbase;

use Pronamic\WordPress\Pay\Core\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Core\PaymentMethod;
use Pronamic\WordPress\Pay\Core\PaymentMethods;
use Pronamic\WordPress\Pay\Payments\Payment;
use Exception;


/**
 * Title: Coinbase Commerce Gateway
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 8.77.0.0
 * @since 8.77.0.0
 */
class Gateway extends Core_Gateway {

	/**
	 * Initializes an Coinbase Commerce gateway
	 *
	 * @param Config $config
	 *            Config.
	 */
	public function init( Config $config ) {
		$this->set_method( self::METHOD_HTTP_REDIRECT );

		// Supported features.
		$this->supports = [
			'payment_status_request',
		];

		Coinbase_API_Handler::$api_key = $config->api_key;
		Coinbase_API_Handler::$log     = get_class( $this ) . '::dont_log';

		$this->register_payment_methods();
	}

	private function register_payment_methods() {
		$this->register_payment_method( new PaymentMethod( PaymentMethods::BITCOIN ) );
	}

	/**
	 * Start.
	 *
	 * @see Core_Gateway::start()
	 *
	 * @param Payment $payment
	 *            Payment.
	 */
	public function start( Payment $payment ) {
		
		// Create a new charge.
		$result = Coinbase_API_Handler::create_charge(
			$payment->get_total_amount()->number_format( null, '.', '' ),
			$payment->get_total_amount()->get_currency()->get_alphabetic_code(),
			$this->get_notes( $payment ),
			$payment->get_return_url(),
			null,
			$payment->get_description(),
			$payment->get_return_url()
		);
		
		if ( ! $result[0] ) {
			throw new Exception( 'Something went wrong. Please try again later.' );
		}
		
		$charge = $result[1]['data'];
		
		$payment->set_meta( 'coinbase_charge_id', $charge['code'] );
		$payment->set_transaction_id( $charge['code'] );
		$payment->set_action_url( $charge['hosted_url'] );
	}

	/**
	 * Update status of the specified payment.
	 *
	 * @param Payment $payment
	 *            Payment.
	 */
	public function update_status( Payment $payment ) {
		$charge_details = Coinbase_API_Handler::send_request( 'charges/' . $payment->get_meta( 'coinbase_charge_id' ) );
		
		if ( ! $charge_details[0] ) {
			throw new Exception( 'Something went wrong. Please try again later.' );
		}
		
		$timeline = $charge_details[1]['data']['timeline'];
		$payment->add_note( '<strong>' . __( 'Coinbase Charge Timeline:', 'knit-pay-lang' ) . '</strong><br><pre>' . print_r( $timeline, true ) . '</pre>' );
		$last_update = end( $timeline );
		$payment->set_status( Statuses::transform( $last_update ) );
	}
	
	private function get_notes( Payment $payment ) {
		$notes = [
			'knitpay_payment_id' => $payment->get_id(),
			'knitpay_extension'  => $payment->get_source(),
			'knitpay_source_id'  => $payment->get_source_id(),
			'knitpay_order_id'   => $payment->get_order_id(),
			'knitpay_version'    => KNITPAY_VERSION,
			'website_url'        => home_url( '/' ),
		];
		
		$customer      = $payment->get_customer();
		$customer_name = substr( trim( ( html_entity_decode( $customer->get_name(), ENT_QUOTES, 'UTF-8' ) ) ), 0, 45 );
		if ( ! empty( $customer_name ) ) {
			$notes = [
				'customer_name' => $customer_name,
			] + $notes;
		}
		
		return $notes;
	}
	
	/**
	 * don't log
	 */
	public static function dont_log( $message, $level = 'info' ) {
	}
}
