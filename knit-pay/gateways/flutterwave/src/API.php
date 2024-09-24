<?php

namespace KnitPay\Gateways\Flutterwave;

use Exception;

class API {

	const CONNECTION_TIMEOUT = 10;

	private $secret_key;

	public function __construct( $secret_key ) {
		$this->secret_key = $secret_key;

		$this->api_endpoint = 'https://api.flutterwave.com/v3/';
	}

	public function get_payment_link( $data ) {

		$endpoint = $this->api_endpoint . 'payments';

		$response = wp_remote_post(
			$endpoint,
			[
				'body'    => $data,
				'headers' => $this->get_request_headers(),
				'timeout' => self::CONNECTION_TIMEOUT,

			]
		);
		$result = wp_remote_retrieve_body( $response );

		$result = json_decode( $result );
		if ( isset( $result->status ) && 'success' === $result->status ) {
			return $result->data->link;
		}

		if ( isset( $result->errors ) ) {
			throw new Exception( trim( reset( $result->errors )->message ) );
		}

		throw new Exception( 'Something went wrong. Please try again later.' );
	}

	public function get_transaction_details( $transaction_id ) {

		$endpoint = $this->api_endpoint . 'transactions/' . $transaction_id . '/verify';

		$response = wp_remote_get(
			$endpoint,
			[
				'headers' => $this->get_request_headers(),
				'timeout' => self::CONNECTION_TIMEOUT,
			]
		);
		$result   = wp_remote_retrieve_body( $response );

		$result = json_decode( $result );

		if ( ! isset( $result->status ) ) {
			throw new Exception( 'Something went wrong. Please try again later.' );
		}

		if ( 'error' === $result->status ) {
			throw new Exception( trim( $result->message ) );
		}

		if ( 'success' === $result->status ) {
			return $result->data;
		}

		throw new Exception( 'Something went wrong. Please try again later.' );
	}

	private function get_request_headers() {
		return [
			'Authorization' => 'Bearer ' . $this->secret_key,
			'Content-Type'  => 'application/json',
		];
	}
}
