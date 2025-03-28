<?php
namespace KnitPay\Gateways\PhonePe;

use Pronamic\WordPress\Pay\Core\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Core\PaymentMethod;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;
use Pronamic\WordPress\Pay\Payments\FailureReason;
use Exception;

/**
 * Title: PhonePe Gateway
 * Copyright: 2020-2025 Knit Pay
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

		if ( 'v2' === $config->api_version ) {
			$this->api = new APIV2( $config, $this->test_mode );
		} else {
			$this->api = new APIV1( $config, $this->test_mode );
		}

		$this->register_payment_methods();
	}

	private function register_payment_methods() {
		$this->register_payment_method( new PaymentMethod( PaymentMethods::CREDIT_CARD ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::UPI ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::DEBIT_CARD ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::NET_BANKING ) );
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
		$cust_id      = 'CUST_' . $payment->get_order_id() . '_' . $payment->get_id();
		$redirect_url = $payment->get_return_url();
		$callback_url = remove_query_arg( [ 'key', 'payment' ], $redirect_url );
		$callback_url = add_query_arg(
			[
				'kp_phonepe_webhook_payment_id' => $payment->get_id(),
				'key'                           => $payment->get_key(),
			],
			$callback_url
		);
		$amount       = $payment->get_total_amount()->get_minor_units()->format( 0, '.', '' );

		if ( 'v2' === $this->config->api_version ) {
			// @see: https://developer.phonepe.com/v1/reference/initiate-payment-standard-checkout/#Request-Parameters
			$data = [
				'merchantOrderId' => $payment->get_transaction_id(),
				'amount'          => $amount,
				// 'metaInfo' => [],
				'paymentFlow'     => [
					'type'         => 'PG_CHECKOUT',
					'message'      => $payment->get_description(),
					'merchantUrls' => [
						'redirectUrl' => $redirect_url,
					],
				],
			];

			$data['paymentFlow'] = array_merge( $data['paymentFlow'], PaymentMethods::transform( $payment->get_payment_method() ) );

			return wp_json_encode( $data );
		}

		// @see: https://developer.phonepe.com/v1/reference/pay-api
		$data = [
			'merchantId'            => $this->config->merchant_id,
			'merchantTransactionId' => $payment->get_transaction_id(),
			'merchantUserId'        => $cust_id,
			'amount'                => $amount,
			'redirectUrl'           => $redirect_url,
			'redirectMode'          => 'POST',
			'callbackUrl'           => $callback_url,
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

		if ( isset( $payment_status->state ) ) {
			// V2 API.
			if ( isset( $payment_status->errorContext ) ) {
				$failure_reason = new FailureReason();
				$failure_reason->set_message( $payment_status->errorContext->description );
				$failure_reason->set_code( $payment_status->errorContext->errorCode );
				$payment->set_failure_reason( $failure_reason );
			} elseif ( Statuses::COMPLETED === $payment_status->state ) {
				$payment->set_transaction_id( $payment_status->paymentDetails[0]->transactionId );
			}

			$payment->set_status( Statuses::transform( $payment_status->state ) );
			$payment->add_note( '<strong>PhonePe Response:</strong><br><pre>' . print_r( $payment_status, true ) . '</pre><br>' );
		} elseif ( isset( $payment_status->code ) ) {
			// V1 API.
			if ( Statuses::SUCCESS === $payment_status->code ) {
				$payment->set_transaction_id( $payment_status->data->transactionId );
			}

			$payment->set_status( Statuses::transform( $payment_status->code ) );
			$payment->add_note( '<strong>PhonePe Response:</strong><br><pre>' . print_r( $payment_status, true ) . '</pre><br>' );
		}
	}
}
