<?php
namespace KnitPay\Gateways\Zaakpay;

use Pronamic\WordPress\Pay\Core\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;

/**
 * Title: Zaakpay Gateway
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 6.66.0.0
 * @since 6.66.0.0
 */
class Gateway extends Core_Gateway {
	private $config;
	private $endpoint_url;

	const LIVE_URL = 'https://api.zaakpay.com';

	const STAGING_URL = 'https://zaakstaging.zaakpay.com';

	/**
	 * Initializes an Zaakpay gateway
	 *
	 * @param Config $config
	 *            Config.
	 */
	public function init( Config $config ) {
		$this->set_method( self::METHOD_HTML_FORM );

		// Supported features.
		$this->supports = [
			'payment_status_request',
		];

		$this->endpoint_url = self::LIVE_URL;
		if ( self::MODE_TEST === $config->mode ) {
			$this->endpoint_url = self::STAGING_URL;
		}

		$this->config = $config;
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
		$payment_currency = $payment->get_total_amount()
			->get_currency()
			->get_alphabetic_code();
		if ( isset( $payment_currency ) && 'INR' !== $payment_currency ) {
			$currency_error = 'Zaakpay only accepts payments in Indian Rupees. If you are a store owner, kindly activate INR currency for ' . $payment->get_source() . ' plugin.';
			throw new \Exception( $currency_error );
		}

		$payment->set_transaction_id( $payment->key . '_' . $payment->get_id() );

		$payment->set_action_url( $this->endpoint_url . '/api/paymentTransact/V8' );
	}

	/**
	 * Get output inputs.
	 *
	 * @see Core_Gateway::get_output_fields()
	 *
	 * @param Payment $payment
	 *            Payment.
	 *
	 * @return array
	 */
	public function get_output_fields( Payment $payment ) {
		$merchant_identifier = $this->config->merchant_identifier;
		$secret_key          = $this->config->secret_key;

		$customer        = $payment->get_customer();
		$billing_address = $payment->get_billing_address();
		
		$data = [
			'amount'             => $payment->get_total_amount()->get_minor_units()->format( 0, '.', '' ),
			'buyerEmail'         => $customer->get_email(),
			'currency'           => $payment->get_total_amount()->get_currency()->get_alphabetic_code(),
			'merchantIdentifier' => $merchant_identifier,
			'orderId'            => $payment->get_transaction_id(),
			'productDescription' => $payment->get_description(),
			'returnUrl'          => $payment->get_return_url(),
		];
		
		if ( ! is_null( $customer->get_name() ) ) {
			$data['buyerFirstName'] = $customer->get_name()->get_first_name();
			$data['buyerLastName']  = $customer->get_name()->get_last_name();
		}
		
		if ( ! is_null( $billing_address ) ) {
			$data['buyerPhoneNumber'] = $billing_address->get_phone();
		}

		ksort( $data );
		$all_parameters = build_query( $data ) . '&';

		$data['checksum'] = hash_hmac( 'sha256', $all_parameters, $secret_key );
		
		return $data;
	}

	/**
	 * Update status of the specified payment.
	 *
	 * @param Payment $payment
	 *            Payment.
	 */
	public function update_status( Payment $payment ) {
		if ( PaymentStatus::SUCCESS === $payment->get_status() ) {
			return;
		}
		
		$merchant_identifier = $this->config->merchant_identifier;
		$secret_key          = $this->config->secret_key;
		
		$endpoint = $this->endpoint_url . '/checkTxn?v=5';
		
		$api_data             = [
			'data' => wp_json_encode(
				[
					'merchantIdentifier' => $merchant_identifier,
					'orderDetail'        => [ 'orderId' => $payment->get_transaction_id() ],
					'mode'               => '0',
				]
			),
		];
		$api_data['checksum'] = hash_hmac( 'sha256', $api_data['data'], $secret_key );

		$response = wp_remote_post(
			$endpoint,
			[
				'body'    => $api_data,
				'timeout' => 10,
			]
		);
		$result   = wp_remote_retrieve_body( $response );
		
		$result = json_decode( $result );
		if ( ! ( isset( $result->success ) && $result->success ) ) {
			return;
		}
		
		$order = end( $result->orders );

		$payment->set_status( Statuses::transform( $order->txnStatus ) );
		$payment->add_note( '<strong>Zaakpay Response:</strong><br><pre>' . print_r( $result->orders, true ) . '</pre><br>' );
		
		if ( PaymentStatus::SUCCESS === $payment->get_status() ) {
			$payment->set_transaction_id( $order->orderDetail->txnId );
		}
	}
}
