<?php

namespace KnitPay\Gateways\Cashfree;

use Exception;

class API {

	const CONNECTION_TIMEOUT = 10;

	private $api_endpoint;

	private $api_id;

	private $secret_key;

	public function __construct( $api_id, $secret_key, $test_mode ) {
		$this->api_id     = $api_id;
		$this->secret_key = $secret_key;

		$this->set_endpoint( $test_mode );
	}

	private function set_endpoint( $test_mode ) {
		if ( $test_mode ) {
			$this->api_endpoint = 'https://sandbox.cashfree.com/pg/';
			return;
		}
		$this->api_endpoint = 'https://api.cashfree.com/pg/';
	}

	public function get_endpoint() {
		return $this->api_endpoint;
	}

	public function create_order( $data ) {
		$endpoint = $this->get_endpoint() . 'orders';

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
		if ( isset( $result->message ) ) {
			throw new Exception( $result->message );
		}

		if ( isset( $result->payment_session_id ) ) {
			return $result->payment_session_id;
		}

		throw new Exception( 'Something went wrong. Please try again later.' );
	}

	public function get_order_details( $id ) {
		$endpoint = $this->get_endpoint() . 'orders/' . $id;

		$response = wp_remote_get(
			$endpoint,
			[
				'headers' => $this->get_request_headers(),
				'timeout' => self::CONNECTION_TIMEOUT,
			]
		);
		$result   = wp_remote_retrieve_body( $response );

		$result = json_decode( $result );
		if ( isset( $result->message ) ) {
			throw new Exception( $result->message );
		}

		if ( isset( $result->order_status ) ) {
			return $result;
		}

		throw new Exception( 'Something went wrong. Please try again later.' );
	}
	
	public function get_order_data( $arg ) {
		$url      = isset( $arg->url ) ? $arg->url : $arg;
		$response = wp_remote_get(
			$url,
			[
				'headers' => $this->get_request_headers(),
				'timeout' => self::CONNECTION_TIMEOUT,
			]
		);
		$result   = wp_remote_retrieve_body( $response );

		$result = json_decode( $result );
		if ( isset( $result->message ) ) {
			throw new Exception( $result->message );
		}

		if ( is_array( $result ) ) {
			return $result;
		}

		throw new Exception( 'Something went wrong. Please try again later.' );
	}

	private function get_request_headers() {
		return [
			'Accept'          => 'application/json',
			'Content-Type'    => 'application/json',
			'x-api-version'   => '2022-09-01',
			'x-client-id'     => $this->api_id,
			'x-client-secret' => $this->secret_key,
		];
	}
}
