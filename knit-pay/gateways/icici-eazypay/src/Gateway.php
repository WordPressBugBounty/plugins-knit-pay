<?php
namespace KnitPay\Gateways\IciciEazypay;

use Pronamic\WordPress\Pay\Core\PaymentMethod;
use Pronamic\WordPress\Pay\Core\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;

/**
 * Title: ICICI Eazypay Gateway
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 6.62.0.0
 * @since 6.62.0.0
 */
class Gateway extends Core_Gateway {

	const DEFAULT_BASE_URL = 'https://eazypay.icicibank.com/EazyPG?';

	/**
	 * Constructs and initializes an ICICI Eazypay gateway
	 *
	 * @param Config $config
	 *            Config.
	 */
	public function __construct( Config $config ) {
		parent::__construct( $config );
		
		$this->config = $config;

		$this->set_method( self::METHOD_HTTP_REDIRECT );

		// Supported features.
		$this->supports = [
			'payment_status_request',
		];

		$this->register_payment_methods();
	}

	private function register_payment_methods() {
		$this->register_payment_method( new PaymentMethod( PaymentMethods::CREDIT_CARD ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::ICICI_EAZYPAY ) );
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
		$reference_no    = substr( wp_rand( 1000000000, 9999999999 ) . '_' . $payment->get_id(), -15 );
		$amount          = $payment->get_total_amount()->number_format( null, '.', '' );
		$sub_merchant_id = 45;
		$paymode         = 9; // 9 for all payment modes.

		$return_url = $this->config->static_return_url;
		if ( empty( $return_url ) ) {
			$return_url = $payment->get_return_url();
		}

		$billing_address = $payment->get_billing_address();
		$customer        = $payment->get_customer();
		$customer_phone  = '';
		if ( ! empty( $billing_address ) && ! empty( $billing_address->get_phone() ) ) {
			$customer_phone = $billing_address->get_phone();
		}

		// Replacements.
		$replacements = [
			'{reference_no}'    => $reference_no,
			'{amount}'          => $amount,
			'{phone}'           => $customer_phone,
			'{customer_phone}'  => $customer_phone,
			'{customer_email}'  => $customer->get_email(),
			'{customer_name}'   => substr( trim( ( html_entity_decode( $customer->get_name(), ENT_QUOTES, 'UTF-8' ) ) ), 0, 20 ),
			'{purpose}'         => $payment->get_description(),
			'{order_id}'        => $payment->get_order_id(),
			'{sub_merchant_id}' => $sub_merchant_id,
		];

		$mandatory_fields = strtr( $this->config->mandatory_fields, $replacements );
		$optional_fields  = strtr( $this->config->optional_fields, $replacements );

		if ( pronamic_pay_plugin()->is_debug_mode() ) {
			$this->encrypt = false;
			$url_param     = [
				'merchantid'         => $this->config->merchant_id,
				'mandatory fields'   => $this->encrypt( $mandatory_fields ),
				'optional fields'    => $this->encrypt( $optional_fields ),
				'returnurl'          => $this->encrypt( $return_url ),
				'Reference No'       => $this->encrypt( $reference_no ),
				'submerchantid'      => $this->encrypt( $sub_merchant_id ),
				'transaction amount' => $this->encrypt( $amount ),
				'paymode'            => $this->encrypt( $paymode ),
			];
			$url           = self::DEFAULT_BASE_URL . build_query( $url_param );
			$payment->add_note( $url );
		}

		$this->encrypt = true;
		$url_param     = [
			'merchantid'         => $this->config->merchant_id,
			'mandatory fields'   => $this->encrypt( $mandatory_fields ),
			'optional fields'    => $this->encrypt( $optional_fields ),
			'returnurl'          => $this->encrypt( $return_url ),
			'Reference No'       => $this->encrypt( $reference_no ),
			'submerchantid'      => $this->encrypt( $sub_merchant_id ),
			'transaction amount' => $this->encrypt( $amount ),
			'paymode'            => $this->encrypt( $paymode ),
		];
		$url           = self::DEFAULT_BASE_URL . build_query( $url_param );
		
		$payment->set_transaction_id( $reference_no );
		$payment->set_action_url( $url );
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
		
		if ( filter_has_var( INPUT_POST, 'Response_Code' ) ) {
			$redirect_response_code = filter_input( INPUT_POST, 'Response_Code', FILTER_SANITIZE_STRING );
			$payment->add_note( 'Redirect Response Code: ' . $redirect_response_code );

			$transformed_response_status = Statuses::transform_response_code( $redirect_response_code );
			if ( ! empty( $transformed_response_status ) ) {
				$payment->set_status( $transformed_response_status );
				return;
			}
		}
		
		$transaction_details = null;
		
		$endpoint = add_query_arg(
			[
				'merchantid'    => $this->config->merchant_id,
				'pgreferenceno' => $payment->get_transaction_id(),
			],
			'https://eazypay.icicibank.com/EazyPGVerify'
		);
		
		$response = wp_remote_get( $endpoint );
		
		$result = wp_remote_retrieve_body( $response );
		parse_str( $result, $transaction_details );
		
		if ( ! is_array( $transaction_details ) ) {
			throw new \Exception( 'Invalid Transaction Details Response.' );
		}
				
		if ( PaymentStatus::SUCCESS === Statuses::transform( $transaction_details['status'] ) ) {
			$payment->set_transaction_id( $transaction_details['ezpaytranid'] );
		}
		
		$payment->set_status( Statuses::transform( $transaction_details['status'] ) );
		
		$note = '<strong>Transaction Details:</strong><br><pre>' . print_r( $transaction_details, true ) . '</pre>';
		$payment->add_note( $note );
	}
	
	private function encrypt( $data = '' ) {
		if ( ! $this->encrypt ) {
			return $data;
		}

		$cipher = 'AES-128-ECB';
		$key    = $this->config->encryption_key;

		$encrypted = openssl_encrypt( $data, $cipher, $key, OPENSSL_RAW_DATA );
		return base64_encode( $encrypted );
	}
}
