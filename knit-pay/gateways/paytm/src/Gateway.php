<?php
namespace KnitPay\Gateways\Paytm;

use Pronamic\WordPress\Pay\Core\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;
use Exception;
use paytm\paytmchecksum\PaytmChecksum;
use Pronamic\WordPress\Pay\Core\PaymentMethod;

/**
 * Title: Paytm Gateway
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 4.9.0
 * @since 4.9.0
 */
class Gateway extends Core_Gateway {
	private $config;
	private $api;

	const NAME = 'paytm';

	/**
	 * Initializes an Paytm gateway
	 *
	 * @param Config $config
	 *            Config.
	 */
	public function init( Config $config ) {
		$this->config = $config;

		$this->set_method( self::METHOD_HTML_FORM );

		// Supported features.
		$this->supports = [
			'payment_status_request',
		];

		$this->api = new API( $config->merchant_key, self::MODE_TEST === $config->mode );

		$this->register_payment_methods();
	}

	private function register_payment_methods() {
		$this->register_payment_method( new PaymentMethod( PaymentMethods::PAYTM ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::UPI ) );
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
			$currency_error = 'Paytm only accepts payments in Indian Rupees. If you are a store owner, kindly activate INR currency for ' . $payment->get_source() . ' plugin.';
			throw new \Exception( $currency_error );
		}

		$payment_data = $this->get_payment_data( $payment );
		$payment->set_transaction_id( $payment_data['body']['orderId'] );

		$paytm_transaction_token = $this->api->initiate_transaction( $payment_data );
		$payment->set_meta( 'paytm_transaction_token', $paytm_transaction_token );

		$merchant_id = $payment_data['body']['mid'];
		$order_id    = $payment_data['body']['orderId'];

		$action_url = $this->api->get_endpoint() . 'theia/api/v1/showPaymentPage';
		$action_url = add_query_arg(
			[
				'mid'     => $merchant_id,
				'orderId' => $order_id,
			],
			$action_url
		);
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
		$merchant_id  = $this->config->merchant_id;
		$merchant_key = $this->config->merchant_key;
		$website      = $this->config->website;

		$customer        = $payment->get_customer();
		$billing_address = $payment->get_billing_address();

		$cust_id = $customer->get_email();
		if ( empty( $cust_id ) ) {
			$cust_id = 'CUST_' . $payment->get_id();
		}

		// Create an array having all required parameters for creating checksum.
		// @see https://developer.paytm.com/docs/api/initiate-transaction-api/
		$paytmParams = [];

		$paytmParams['body'] = [
			'requestType' => 'Payment',
			'mid'         => $merchant_id,
			'websiteName' => $website,
			'orderId'     => $this->get_order_id( $payment ),
			'callbackUrl' => $payment->get_return_url(),
			'txnAmount'   => [
				'value'    => $payment->get_total_amount()->number_format( null, '.', '' ),
				'currency' => 'INR',
			],
			'userInfo'    => [
				'custId' => $cust_id,
				'email'  => $customer->get_email(),
			],
			'extendInfo'  => [
				'mercUnqRef' => $payment->get_description(),
				'comments'   => $payment->get_description(),
			],
		];

		if ( isset( $billing_address ) && ! empty( $billing_address->get_phone() ) ) {
			$paytmParams['body']['userInfo']['mobile'] = $billing_address->get_phone();
		}

		if ( null !== $customer->get_name() ) {
			$paytmParams['body']['userInfo']['firstName'] = $customer->get_name()->get_first_name();
			$paytmParams['body']['userInfo']['lastName']  = $customer->get_name()->get_last_name();
		}

		if ( ! empty( $payment->get_payment_method() ) && PaymentMethods::UPI === $payment->get_payment_method() ) {
			$paytmParams['body']['enablePaymentMode'] = [
				[
					'mode' => 'UPI',
				],
			];
		}

		// Generate checksum by parameters we have in body.
		$checksum = PaytmChecksum::generateSignature( wp_json_encode( $paytmParams['body'] ), $merchant_key );

		$paytmParams['head'] = [
			'signature' => $checksum,
		];

		return $paytmParams;
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
		return [
			'mid'      => $this->config->merchant_id,
			'orderId'  => $this->get_order_id( $payment ),
			'txnToken' => $payment->get_meta( 'paytm_transaction_token' ),
		];
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

		$merchant_id  = $this->config->merchant_id;
		$merchant_key = $this->config->merchant_key;

		// Create an array having all required parameters for status query.
		$requestParamList = [
			'body' => [
				'mid'     => $merchant_id,
				'orderId' => $this->get_order_id( $payment ),
			],
		];

		$requestParamList['head']['signature'] = PaytmChecksum::generateSignature( wp_json_encode( $requestParamList['body'] ), $merchant_key );


		$transaction_status = $this->api->get_transaction_status( $requestParamList );

		if ( $transaction_status->orderId !== $this->get_order_id( $payment ) ) {
			throw new \Exception( 'Something went wrong:<br><pre>' . print_r( $transaction_status, true ) . '</pre>' );
		}

		$note = '<strong>Paytm Parameters:</strong>';
		if ( isset( $transaction_status->txnId ) ) {
			$note .= '<br>Paytm txnId: ' . $transaction_status->txnId;
		}
		if ( isset( $transaction_status->bankTxnId ) ) {
			$note .= '<br>Paytm bankTxnId: ' . $transaction_status->bankTxnId;
		}
		$note .= '<br>Paytm orderId: ' . $transaction_status->orderId;
		$note .= '<br>Status: ' . $transaction_status->resultInfo->resultStatus;
		$note .= '<br>Message: ' . $transaction_status->resultInfo->resultMsg;

		$payment->add_note( $note );
		$payment->set_status( Statuses::transform( $transaction_status->resultInfo->resultStatus ) );

		if ( PaymentStatus::SUCCESS === $payment->get_status() ) {
			$payment->set_transaction_id( $transaction_status->txnId );
		}

		if ( PaymentStatus::OPEN === $payment->get_status() ) {
			$this->expire_old_payment( $payment );
		}
	}

	private function get_order_id( Payment $payment ) {
		if ( $payment->get_meta( 'paytm_order_id' ) ) {
			return $payment->get_meta( 'paytm_order_id' );
		}

		// Replacements.
		$replacements = [
			'{transaction_id}'      => $payment->key . '_' . $payment->get_id(),
			'{payment_description}' => $payment->get_description(),
			'{order_id}'            => $payment->get_order_id(),
			'{source_id}'           => $payment->get_source_id(),
		];
		
		$paytm_order_id = strtr( $this->config->order_id_format, $replacements );
		$paytm_order_id = str_replace( ' ', '_', $paytm_order_id );
		$paytm_order_id = preg_replace( '/[^a-zA-Z0-9_-]/s', '', $paytm_order_id ); // Remove special characters.
		$paytm_order_id = substr( trim( ( html_entity_decode( $paytm_order_id, ENT_QUOTES, 'UTF-8' ) ) ), 0, 29 );

		// Paytm UPI does not support more than 35 characters.
		$paytm_order_id = substr( $paytm_order_id, -35 );

		$payment->add_note( 'paytm_order_id: ' . $paytm_order_id );
		$payment->set_meta( 'paytm_order_id', $paytm_order_id );

		return $paytm_order_id;
	}

	private function expire_old_payment( $payment ) {
		// Make payment status as expired for payment older than 1 day.
		if ( DAY_IN_SECONDS < time() - $payment->get_date()->getTimestamp() && $this->config->expire_old_payments ) {
			$payment->set_status( PaymentStatus::EXPIRED );
		}
	}
}
