<?php

namespace KnitPay\Gateways\MyFatoorah;

use Exception;

class API {

	const CONNECTION_TIMEOUT = 10;

	private $api_endpoint;

	private $api_token_key;

	public function __construct( $api_token_key, $test_mode ) {
		$this->api_token_key = $api_token_key;

		$this->get_endpoint( $test_mode );
	}

	private function get_endpoint( $test_mode ) {
		if ( $test_mode ) {
			$this->api_endpoint = 'https://apitest.myfatoorah.com';
			return;
		}
		$this->api_endpoint = 'https://api.myfatoorah.com';
	}
	
	/*
	 * @see https://myfatoorah.readme.io/docs/send-payment
	 */
	public function send_payment( $data ) {
		$data['NotificationOption'] = 'LNK';
		
		$endpoint = $this->api_endpoint . '/v2/SendPayment';
		
		$response = wp_remote_post(
			$endpoint,
			[
				'body'    => wp_json_encode( $data ),
				'headers' => $this->get_request_headers(),
				'timeout' => self::CONNECTION_TIMEOUT,
			]
		);
		$result   = wp_remote_retrieve_body( $response );
		
		$result = json_decode( $result );
		if ( isset( $result->IsSuccess ) && $result->IsSuccess ) {
			return $result->Data;
		}
		
		if ( isset( $result->ValidationErrors ) ) {
			$error = reset( $result->ValidationErrors );
			throw new Exception( $error->Name . ' - ' . $error->Error );
		}
		
		throw new Exception( 'Something went wrong. Please try again later.' );
	}

	public function execute_payment( $data ) {
		$endpoint = $this->api_endpoint . '/v2/ExecutePayment';

		$response = wp_remote_post(
			$endpoint,
			[
				'body'    => wp_json_encode( $data ),
				'headers' => $this->get_request_headers(),
				'timeout' => self::CONNECTION_TIMEOUT,
			]
		);
		$result   = wp_remote_retrieve_body( $response );

		$result = json_decode( $result );
		if ( isset( $result->IsSuccess ) && $result->IsSuccess ) {
			return $result->Data;
		}

		if ( isset( $result->ValidationErrors ) ) {
			$error = reset( $result->ValidationErrors );
			throw new Exception( $error->Name . ' - ' . $error->Error );
		}
		
		throw new Exception( 'Something went wrong. Please try again later.' );
	}

	public function get_payment_status( $keyId ) {
		$endpoint = $this->api_endpoint . '/v2/getPaymentStatus';
		
		// Fill POST fields array
		$post_fields = [
			'Key'     => $keyId,
			'KeyType' => 'invoiceId',
		];

		$response = wp_remote_post(
			$endpoint,
			[
				'body'    => wp_json_encode( $post_fields ),
				'headers' => $this->get_request_headers(),
				'timeout' => self::CONNECTION_TIMEOUT,
			]
		);
		$result   = wp_remote_retrieve_body( $response );

		$result = json_decode( $result );
		if ( isset( $result->IsSuccess ) && $result->IsSuccess ) {
			return $result->Data;
		}

		if ( isset( $result->Message ) ) {
			throw new Exception( $result->Message );
		}
		
		throw new Exception( 'Something went wrong. Please try again later.' );
		
	}

	private function get_request_headers() {
		return [
			'Authorization' => 'Bearer ' . $this->api_token_key,
			'Content-Type'  => 'application/json',
		];
	}
}
