<?php
namespace KnitPay\Gateways\SBIePay;

use Pronamic\WordPress\Pay\Core\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;
use Exception;

/**
 * Title: SBIePay Gateway
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 5.7.0
 * @since 5.7.0
 */
class Gateway extends Core_Gateway {
	private $config;
	private $endpoint_url;

	const LIVE_URL = 'https://www.sbiepay.sbi/';

	const TEST_URL = 'https://test.sbiepay.sbi/';

	/**
	 * Initializes an SBIePay gateway
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

		$this->endpoint_url = self::LIVE_URL;
		if ( self::MODE_TEST === $config->mode ) {
			$this->endpoint_url = self::TEST_URL;
		}
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
		$payment_currency = $payment->get_total_amount()->get_currency()->get_alphabetic_code();
		if ( isset( $payment_currency ) && 'INR' !== $payment_currency ) {
			$currency_error = 'SBIePay only accepts payments in Indian Rupees. If you are a store owner, kindly activate INR currency for ' . $payment->get_source() . ' plugin.';
			throw new Exception( $currency_error );
		}

		$payment->set_transaction_id( $payment->key . '_' . $payment->get_id() );

		$payment->set_action_url( $this->endpoint_url . 'secure/AggregatorHostedListener' );
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
		$encryption_key = $this->config->encryption_key;
		$merchant_id    = $this->config->merchant_id;

		$customer = $payment->get_customer();

		$amount         = $payment->get_total_amount()->number_format( null, '.', '' );
		$other_details  = $payment->get_description();
		$redirect_url   = $payment->get_return_url();
		$transaction_id = $payment->get_transaction_id();
		$cust_id        = $customer->get_email();
		if ( empty( $cust_id ) ) {
			$cust_id = 'CUST_' . $payment->get_id();
		}

		$request_parameter   = "$merchant_id|DOM|IN|INR|$amount|$other_details|$redirect_url|$redirect_url|SBIEPAY|$transaction_id|$cust_id|NB|ONLINE|ONLINE";
		$encrypted_parameter = AES128::encrypt( $request_parameter, $encryption_key );

		if ( pronamic_pay_plugin()->is_debug_mode() ) {
			$payment->add_note( 'Request Parameters: ' . $request_parameter );
			$payment->add_note( 'Encrypted Parameters: ' . $encrypted_parameter );
		}

		// Refer http://bhavanscollegedakor.org/images/uploads/content/SBIePay%20-%20Merchant%20Integration%20Document_v0.3.2%20-%20Copy.pdf
		return [
			'EncryptTrans' => $encrypted_parameter,
			'merchIdVal'   => $merchant_id,
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

		$transaction_id = $payment->get_transaction_id();

		try {
			if ( filter_has_var( INPUT_POST, 'encData' ) && filter_has_var( INPUT_POST, 'merchIdVal' ) ) {
				$order_status = $this->get_post_order_status( $payment );
			} else {
				$order_status = $this->get_api_order_status( $transaction_id );
			}
		} catch ( Exception $e ) {
			throw new Exception( $e->getMessage() );
		}

		$note = \sprintf(
			'%s<pre>%s</pre>',
			\__( 'Order Status:', 'knit-pay-lang' ),
			$order_status
		);
		$payment->add_note( $note );

		$order_status = explode( '|', $order_status );

		$this->update_payment( $payment, $order_status );
	}

	private function get_api_order_status( $transaction_id ) {
		$merchant_id = $this->config->merchant_id;

		$api_data = [
			'queryRequest' => "|$merchant_id|$transaction_id",
			'aggregatorId' => 'SBIEPAY',
			'merchantId'   => $merchant_id,
		];

		$response     = wp_remote_post(
			$this->endpoint_url . 'payagg/orderStatusQuery/getOrderStatusQuery',
			[
				'body'    => $api_data,
				'timeout' => 10,
			]
		);
		$order_status = wp_remote_retrieve_body( $response );

		if ( empty( $order_status ) ) {
			throw new \Exception( 'Response not received.' );
		}

		return $order_status;
	}

	private function get_post_order_status() {
		$encryption_key = $this->config->encryption_key;
		$merchant_id    = $this->config->merchant_id;

		$encrypted_order_status = \sanitize_text_field( \wp_unslash( $_POST['encData'] ) );
		$merchant_id_val        = array_key_exists( 'merchIdVal', $_POST ) ? \sanitize_text_field( \wp_unslash( $_POST['merchIdVal'] ) ) : '';

		if ( $merchant_id_val !== $merchant_id ) {
			throw new \Exception( 'Merchant ID missmatch.' );
		}

		$order_status = AES128::decrypt( $encrypted_order_status, $encryption_key );

		return $order_status;
	}

	public function update_payment( $payment, $status ) {
		/*
		 * Blank value inserted at 0th possition so that parameter number in integration document
		 * matches with parameter number in array.
		 */
		array_unshift( $status, '' );

		$payment->set_status( Statuses::transform( $status[3] ) );
	}
}
