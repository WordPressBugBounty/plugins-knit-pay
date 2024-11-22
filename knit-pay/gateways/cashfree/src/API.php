<?php

namespace KnitPay\Gateways\Cashfree;

use Exception;

class API {

	const CONNECTION_TIMEOUT = 30;

	private $api_endpoint;

	private $config;

	public function __construct( $config, $test_mode ) {
		$this->config = $config;

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

		$result = $this->create_connection( $endpoint, 'POST', $data );

		if ( isset( $result->payment_session_id ) ) {
			return $result->payment_session_id;
		}

		throw new Exception( 'Something went wrong. Please try again later.' );
	}

	public function get_order_details( $id ) {
		$endpoint = $this->get_endpoint() . 'orders/' . $id;

		$result = $this->create_connection( $endpoint, 'GET' );

		if ( isset( $result->order_status ) ) {
			return $result;
		}

		throw new Exception( 'Something went wrong. Please try again later.' );
	}
	
	public function get_order_data( $arg ) {
		$url    = isset( $arg->url ) ? $arg->url : $arg;
		$result = $this->create_connection( $url, 'GET' );

		if ( is_array( $result ) ) {
			return $result;
		}

		throw new Exception( 'Something went wrong. Please try again later.' );
	}

	private function create_connection( $url, $method, $data = [], $allow_retry = true ) {
		$args = [
			'method'  => $method,
			'headers' => $this->get_request_headers(),
			'timeout' => self::CONNECTION_TIMEOUT,
		];

		if ( $method === 'POST' ) {
			$args['body'] = wp_json_encode( $data );
		}

		$response = wp_remote_request( $url, $args );

		if ( 401 === wp_remote_retrieve_response_code( $response ) && $allow_retry ) {
			// Refresh access token.
			$integration = new Integration();
			$integration->refresh_access_token( $this->config->config_id );
			$this->config = $integration->get_config( $this->config->config_id );

			// Retry request.
			return $this->create_connection( $url, $method, $data, false );
		}

		$result = wp_remote_retrieve_body( $response );

		$result = json_decode( $result );
		if ( isset( $result->message ) ) {
			throw new Exception( $result->message );
		}

		return $result;
	}

	private function get_request_headers() {
		$headers = [
			'Accept'        => 'application/json',
			'Content-Type'  => 'application/json',
			'x-api-version' => '2022-09-01',
		];

		if ( $this->config->access_token ) {
			$headers['Authorization'] = 'Bearer ' . $this->config->access_token;
		} else {
			$headers['x-client-id']     = $this->config->api_id;
			$headers['x-client-secret'] = $this->config->secret_key;
		}

		return $headers;
	}
}
