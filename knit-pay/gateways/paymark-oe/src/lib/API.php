<?php

namespace KnitPay\Gateways\PaymarkOE;

use Exception;

class API {

	const CONNECTION_TIMEOUT = 10;

	private $consumer_key;

	private $consumer_secret;

	private $merchant_id_code;

	private $test_mode;

	public function __construct( $consumer_key, $consumer_secret, $merchant_id_code, $test_mode ) {
		$this->consumer_key     = $consumer_key;
		$this->consumer_secret  = $consumer_secret;
		$this->merchant_id_code = $merchant_id_code;
		$this->test_mode        = $test_mode;
	}

	public function get_open_plugin_url() {
		if ( $this->test_mode ) {
			return 'https://open.demo.paymark.co.nz/v1';
		}
		return $this->api_endpoint = 'https://open.paymark.co.nz/v1';
	}

	private function get_endpoint_url() {
		if ( $this->test_mode ) {
			return 'https://apitest.paymark.nz/';
		}
		return 'https://api.paymark.nz/';
	}

	private function get_api_endpoint_url() {
		return $this->get_endpoint_url() . 'openjs/v1/';
	}

	private function get_access_token() {
		$data               = [];
		$data['grant_type'] = 'client_credentials';

		$response = wp_remote_post(
			$this->get_endpoint_url() . 'bearer',
			[
				'body'    => $data,
				'headers' =>
					'Authorization:Basic ' . base64_encode( $this->consumer_key . ':' . $this->consumer_secret ),

			]
		);
		$result = wp_remote_retrieve_body( $response );
		if ( ! $result ) {
			throw new Exception( 'Something went wrong. Please try again later.' );
		}

		$result = json_decode( $result );
		if ( isset( $result->status ) && 'approved' === $result->status ) {
			return "Bearer $result->access_token";
		}

		throw new Exception( 'Something went wrong. Please try again later.' );
	}

	public function create_session( $data ) {
		$api_url  = $this->get_api_endpoint_url() . 'session';
		$api_data = wp_parse_args( $data, $this->get_api_data() );

		$response = wp_remote_post(
			$api_url,
			[
				'body'    => wp_json_encode( $api_data ),
				'headers' => [
					'Authorization' => $this->get_access_token(),
					'Accept'        => 'application/json',
					'Content-Type'  => 'application/json',
				],
				'timeout' => self::CONNECTION_TIMEOUT,
			]
		);
		$result   = wp_remote_retrieve_body( $response );

		$result = json_decode( $result );

		if ( isset( $result->error ) ) {
			throw new Exception( $result->error . ': ' . print_r( $result->messages, true ) );
		}

		return $result->id;
	}

	public function get_session( $session_id ) {
		$api_url = $this->get_api_endpoint_url() . 'session/' . $session_id;

		$response = wp_remote_get(
			$api_url,
			[
				'headers' => [
					'Authorization' => $this->get_access_token(),
					'Accept'        => 'application/json',
				],
				'timeout' => self::CONNECTION_TIMEOUT,
			]
		);
		$result   = wp_remote_retrieve_body( $response );

		$result = json_decode( $result );

		if ( isset( $result->status ) ) {
			return $result;
		}

		throw new Exception( 'Something went wrong. Please try again later.' );
	}

	public function get_payment( $session_id ) {
		$api_url = $this->get_api_endpoint_url() . 'payment?sessionId=' . $session_id;

		$response = wp_remote_get(
			$api_url,
			[
				'headers' => [
					'Authorization' => $this->get_access_token(),
					'Accept'        => 'application/json',
				],
				'timeout' => self::CONNECTION_TIMEOUT,
			]
		);
		$result   = wp_remote_retrieve_body( $response );

		$result = json_decode( $result );

		if ( isset( $result->status ) ) {
			return $result;
		}

		throw new Exception( 'Something went wrong. Please try again later.' );
	}

	private function get_api_data() {
		return [
			'merchantIdCode' => $this->merchant_id_code,
		];
	}
}
