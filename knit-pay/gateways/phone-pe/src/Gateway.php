<?php
namespace KnitPay\Gateways\PhonePe;

use Pronamic\WordPress\Pay\Core\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Core\PaymentMethod;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;
use Exception;

/**
 * Title: PhonePe Gateway
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 8.73.0.0
 * @since 8.73.0.0
 */
class Gateway extends Core_Gateway {
	private $test_mode;
	private $config;
	private $api;

	/**
	 * Initializes an PhonePe gateway
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
		$this->api    = new API( $config->merchant_id, $config->salt_key, $config->salt_index, $this->test_mode );
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
		$payment->set_transaction_id( $payment->key . '_' . $payment->get_id() );
		$url = $this->api->create_transaction_link( $this->get_payment_data( $payment ) );

		$payment->set_action_url( $url );
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
		$cust_id = 'CUST_' . $payment->get_order_id() . '_' . $payment->get_id();

		// @see: https://developer.phonepe.com/v1/reference/pay-api
		$data = [
			'merchantId'            => $this->config->merchant_id,
			'merchantTransactionId' => $payment->get_transaction_id(),
			'merchantUserId'        => $cust_id,
			'amount'                => $payment->get_total_amount()->get_minor_units()->format( 0, '.', '' ),
			'redirectUrl'           => $payment->get_return_url(),
			'redirectMode'          => 'POST',
			// 'callbackUrl' => '', // TODO implement webhook
			'paymentInstrument'     => [
				'type' => 'PAY_PAGE',
			],
		];
		return wp_json_encode( $data );
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

		$payment_status = $this->api->get_payment_status( $payment->get_transaction_id() );

		if ( isset( $payment_status->code ) ) {
			if ( Statuses::SUCCESS === $payment_status->code ) {
				$payment->set_transaction_id( $payment_status->data->transactionId );
			}

			$payment->set_status( Statuses::transform( $payment_status->code ) );
			$payment->add_note( '<strong>PhonePe Response:</strong><br><pre>' . print_r( $payment_status, true ) . '</pre><br>' );
		}
	}
}
