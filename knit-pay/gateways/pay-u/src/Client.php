<?php
namespace KnitPay\Gateways\PayU;

use Exception;
use WP_Error;

/**
 * Title: PayU Client
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 5.4.0
 * @since 5.4.0
 */
class Client {
	const CONNECTION_TIMEOUT = 10;

	const LIVE_URL = 'https://secure.payu.in';

	const TEST_URL = 'https://test.payu.in';

	const API_LIVE_URL = 'https://info.payu.in';

	const API_TEST_URL = 'https://test.payu.in';


	/**
	 * merchant_key.
	 *
	 * @var string
	 */
	private $merchant_key;

	private $merchant_salt;

	private $mode;

	private $payment_server_url;

	private $api_url;

	/**
	 * Construct and initialize an PayU Client
	 *
	 * @param Config $config
	 */
	public function __construct( Config $config, $test_mode = false ) {
		$this->merchant_key  = $config->merchant_key;
		$this->merchant_salt = $config->merchant_salt;
		$this->mode          = $config->mode;

		$this->set_payment_server_url( self::LIVE_URL );
		$this->set_api_url( self::API_LIVE_URL );
		if ( $test_mode ) {
			$this->set_payment_server_url( self::TEST_URL );
			$this->set_api_url( self::API_TEST_URL );
		}
	}

	public function verify_payment( $transaction_id ) {
		$command = 'verify_payment';

		$hash_str = "{$this->merchant_key}|{$command}|{$transaction_id}|{$this->merchant_salt}";
		$hash     = strtolower( hash( 'sha512', $hash_str ) ); // generate hash for verify payment request

		$data = [
			'key'     => $this->merchant_key,
			'command' => $command,
			'var1'    => $transaction_id,
			'hash'    => $hash,
		];

		$response = wp_remote_post(
			$this->get_api_url() . '/merchant/postservice?form=2',
			[
				'body'    => $data,
				'timeout' => self::CONNECTION_TIMEOUT,
			]
		);

		if ( $response instanceof WP_Error ) {
			throw new Exception( $response->get_error_message() );
		}

		$result = wp_remote_retrieve_body( $response );

		$result = json_decode( $result );
		if ( isset( $result->status ) && 1 === $result->status ) {
			return $result->transaction_details->$transaction_id;
		}

		if ( isset( $result->msg ) ) {
			$error = trim( $result->msg );
			if ( pronamic_pay_plugin()->is_debug_mode() ) {
				$error = $error . '<pre>' . print_r( $data, true ) . '</pre>';
			}
			throw new Exception( $error );
		}
		throw new Exception( 'Something went wrong. Please try again later.' );
	}
	
	public function test_connection() {
		$command    = 'get_Transaction_Details';
		$start_date = '2020-10-20';
		$end_date   = '2020-10-20';

		$hash_str = "{$this->merchant_key}|{$command}|{$start_date}|{$this->merchant_salt}";
		$hash     = strtolower( hash( 'sha512', $hash_str ) ); // generate hash for verify payment request

		$data = [
			'key'     => $this->merchant_key,
			'command' => $command,
			'var1'    => $start_date,
			'var2'    => $end_date,
			'hash'    => $hash,
		];

		$response = wp_remote_post(
			$this->get_api_url() . '/merchant/postservice?form=2',
			[
				'body'    => $data,
				'timeout' => self::CONNECTION_TIMEOUT,
			]
		);

		if ( $response instanceof WP_Error ) {
			throw new Exception( $response->get_error_message() );
		}

		$result = wp_remote_retrieve_body( $response );

		$result = json_decode( $result );
		if ( isset( $result->status ) && 1 === $result->status ) {
			return true;
		}

		return false;
	}

	public function cancel_refund_transaction( $transaction_id, $token_id, $amount ) {
		$command = 'cancel_refund_transaction';
		
		$hash_str = "{$this->merchant_key}|{$command}|{$transaction_id}|{$this->merchant_salt}";
		$hash     = strtolower( hash( 'sha512', $hash_str ) ); // generate hash for verify payment request
		
		$data = [
			'key'     => $this->merchant_key,
			'command' => $command,
			'var1'    => $transaction_id,
			'var2'    => $token_id,
			'var3'    => $amount,
			'hash'    => $hash,
		];
		
		$response = wp_remote_post(
			$this->get_api_url() . '/merchant/postservice?form=2',
			[
				'body'    => $data,
				'timeout' => self::CONNECTION_TIMEOUT,
			]
		);
		
		if ( $response instanceof WP_Error ) {
			throw new Exception( $response->get_error_message() );
		}
		
		$result = wp_remote_retrieve_body( $response );
		
		$result = json_decode( $result );
		if ( isset( $result->status ) && 1 === $result->status ) {
			return $result->request_id;
		}
		
		if ( isset( $result->msg ) ) {
			throw new Exception( trim( $result->msg ) );
		}
		throw new Exception( 'Something went wrong. Please try again later.' );
	}

	/**
	 * Set the payment server URL
	 *
	 * @param string $url
	 *            an URL
	 */
	public function set_payment_server_url( $url ) {
		$this->payment_server_url = $url;
	}

	public function get_payment_server_url() {
		return $this->payment_server_url;
	}

	public function set_api_url( $url ) {
		$this->api_url = $url;
	}

	public function get_api_url() {
		return $this->api_url;
	}
}
