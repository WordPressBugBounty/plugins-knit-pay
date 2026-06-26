<?php

namespace KnitPay\Gateways\Tosla;

use KnitPay\Gateways\Gateway as BaseGateway;
use Pronamic\WordPress\Pay\Core\PaymentMethod;
use Pronamic\WordPress\Pay\Payments\FailureReason;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;
use KnitPay\Gateways\PaymentMethods;

/**
 * Title: Tosla Gateway
 * Copyright: 2020-2026 Knit Pay
 *
 * @author  Knit Pay
 * @version 9.6.0.0
 * @since   9.6.0.0
 */
class Gateway extends BaseGateway {
	private Config $config;
	private Client $client;

	/**
	 * Initializes a Tosla gateway.
	 *
	 * @param Config $config Config.
	 */
	public function init( Config $config ) {
		$this->config = $config;
		$this->client = new Client( $config );

		$this->set_method( self::METHOD_HTTP_REDIRECT );
		$this->default_currency     = 'TRY';
		$this->supported_currencies = [ 'TRY' ];

		$this->register_payment_method( new PaymentMethod( PaymentMethods::CARD ) );

		$this->supports = [
			'payment_status_request',
		];
	}

	/**
	 * Start payment.
	 *
	 * @see Core_Gateway::start()
	 *
	 * @param Payment $payment Payment.
	 */
	public function start( Payment $payment ) {
		$payment->set_transaction_id( uniqid() . wp_rand( 10000, 99999 ) );

		$post_data = $this->get_payment_data( $payment );

		$three_d_session_id = $this->client->create_three_d_payment( $post_data );

		$payment->set_action_url( $this->client->get_three_d_secure_url( $three_d_session_id ) );
	}

	/**
	 * Get payment data.
	 *
	 * @param Payment $payment Payment.
	 * @return array
	 */
	private function get_payment_data( Payment $payment ) {
		$total_amount = $payment->get_total_amount();
		$amount       = $total_amount->get_minor_units()->format( 0, '.', '' );
		$currency     = $total_amount->get_currency()->get_numeric_code();

		return [
			'amount'      => (int) $amount,
			'currency'    => (int) $currency,
			'orderId'     => $payment->get_transaction_id(),
			'callbackUrl' => $payment->get_return_url(),
		];
	}

	/**
	 * Update payment status.
	 *
	 * @param Payment $payment Payment.
	 */
	public function update_status( Payment $payment ) {
		$order_id = $payment->get_transaction_id();

		$result = $this->client->inquiry( $order_id );

		$payment_status = Statuses::transform( $result->RequestStatus );

		if ( PaymentStatus::SUCCESS !== $payment_status ) {
			$failure_reason = new FailureReason();

			$message = '';
			if ( ! empty( $result->BankResponseMessage ) ) {
				$message = $result->BankResponseMessage;
			} elseif ( ! empty( $result->ErrorMessage ) ) {
				$message = $result->ErrorMessage;
			}

			if ( ! empty( $message ) ) {
				$failure_reason->set_message( $message );
			}

			$payment->set_failure_reason( $failure_reason );
		}

		$payment->set_status( $payment_status );
		$payment->add_note( '<strong>Tosla Inquiry Response:</strong><br><pre>' . wp_json_encode( $result, JSON_PRETTY_PRINT ) . '</pre><br>' );
	}
}
