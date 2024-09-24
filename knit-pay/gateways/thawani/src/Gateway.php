<?php
namespace KnitPay\Gateways\Thawani;

use Pronamic\WordPress\Pay\Core\Gateway as Core_Gateway;
use Exception;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;


/**
 * Title: Thawani Gateway
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 6.70.0.0
 * @since 6.70.0.0
 */
class Gateway extends Core_Gateway {
	private $test_mode;
	private $api;
	private $config;

	/**
	 * Initializes an Thawani gateway
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

		$this->test_mode = 0;
		if ( self::MODE_TEST === $config->mode ) {
			$this->test_mode = 1;
		}

		$this->config = $config;
		$this->api    = new API( $config->secret_key, $this->test_mode );
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
		$thawani_session = $this->api->create_session( $this->get_payment_data( $payment ) );

		$payment_currency = $payment->get_total_amount()->get_currency()->get_alphabetic_code();
		if ( isset( $payment_currency ) && $thawani_session->currency !== $payment_currency ) {
			throw new \Exception( 'Thawani Currency and Payment Currency are not same.' );
		}
		
		$payment->set_transaction_id( $thawani_session->invoice );
		$action_url = sprintf( $this->api->get_endpoint() . 'pay/%s?key=%s', $thawani_session->session_id, $this->config->publishable_key );
		$payment->set_action_url( $action_url );
	}

	/**
	 * Get Payment Data.
	 *
	 * @param Payment $payment
	 *            Payment.
	 *
	 * @return array
	 */
	private function get_payment_data( Payment $payment ) {
		$return_url = $payment->get_return_url();
		
		// @see https://docs.thawani.om/docs/thawani-ecommerce-api/a2a9e57e10521-create-session
		return [
			'client_reference_id' => $payment->key . '_' . $payment->get_id(),
			'mode'                => 'payment',
			'products'            => [
				[
					'name'        => $payment->get_description(),
					'quantity'    => 1,
					'unit_amount' => $payment->get_total_amount()->get_minor_units()->format( 0, '.', '' ),
				],
			],
			'success_url'         => $return_url,
			'cancel_url'          => $return_url,
			'metadata'            => $this->get_notes( $payment ),
		];
	}

	/**
	 * Update status of the specified payment.
	 *
	 * @param Payment $payment
	 *            Payment.
	 */
	public function update_status( Payment $payment ) {
		if ( PaymentStatus::SUCCESS === $payment->get_status() || PaymentStatus::EXPIRED === $payment->get_status() ) {
			return;
		}

		$session = $this->api->get_session_by_invoice( $payment->get_transaction_id() );

		if ( isset( $session->payment_status ) ) {
			$payment_status = $session->payment_status;

			$payment->set_status( Statuses::transform( $payment_status ) );
			$payment->add_note( '<strong>Thawani Session:</strong><br><pre>' . print_r( $session, true ) . '</pre><br>' );
		}
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
}
