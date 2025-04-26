<?php

namespace KnitPay\Gateways\Thawani;

use Exception;

class API {
	private $api_endpoint;

	private $secret_key;

	private $test_mode;

	public function __construct( $secret_key, $test_mode ) {
		$this->secret_key = $secret_key;
		$this->test_mode  = $test_mode;
	}

	public function get_endpoint() {
		if ( $this->test_mode ) {
			return 'https://uatcheckout.thawani.om/';
		}
		return 'https://checkout.thawani.om/';
	}
	
	private function get_api_endpoint() {
		return $this->get_endpoint() . 'api/v1/';
	}

	public function create_session( $data ) {
		$endpoint = $this->get_api_endpoint() . 'checkout/session';

		$response = wp_remote_post(
			$endpoint,
			[
				'body'    => wp_json_encode( $data ),
				'headers' => $this->get_request_headers(),
			]
		);
		$result   = wp_remote_retrieve_body( $response );

		$result = json_decode( $result );

		if ( isset( $result->data ) && isset( $result->data->error ) && is_array( $result->data->error ) ) {
			throw new Exception( wp_json_encode( $result->data->error[0]->message ) );
		} elseif ( isset( $result->detail ) ) {
			throw new Exception( $result->detail );
		}

		return $result->data;
	}

	public function get_session_by_invoice( $id ) {
		$endpoint = $this->get_api_endpoint() . 'checkout/invoice/' . $id;

		$response = wp_remote_get(
			$endpoint,
			[
				'headers' => $this->get_request_headers(),
			]
		);
		$result   = wp_remote_retrieve_body( $response );

		$result = json_decode( $result );
		
		if ( ! isset( $result->success ) || ! $result->success ) {
			throw new Exception( 'Unable to Fetch Session: ' . $result->description );
		}
		
		return $result->data;
	}

	private function get_request_headers() {
		return [
			'Content-Type'    => 'application/json',
			'thawani-api-key' => $this->secret_key,
		];
	}
}
