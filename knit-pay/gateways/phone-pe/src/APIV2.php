<?php
namespace KnitPay\Gateways\PhonePe;

use Exception;

class APIV2 {
	const CONNECTION_TIMEOUT = 10;

	private $api_endpoint;

	private $auth_host_url;

	private $config;

	private $access_token;

	public function __construct( $config, $test_mode ) {
		$this->config = $config;

		$this->set_endpoint( $test_mode );
	}

	private function set_endpoint( $test_mode ) {
		if ( $test_mode ) {
			$this->api_endpoint  = 'https://api-preprod.phonepe.com/apis/pg-sandbox';
			$this->auth_host_url = 'https://api-preprod.phonepe.com/apis/pg-sandbox/v1/oauth/token';
			return;
		}
		$this->api_endpoint  = 'https://api.phonepe.com/apis/pg';
		$this->auth_host_url = 'https://api.phonepe.com/apis/identity-manager/v1/oauth/token';
	}

	public function create_transaction_link( $json_data ) {
		$sub_url = '/checkout/v2/pay';

		$response = wp_remote_post(
			$this->api_endpoint . $sub_url,
			[
				'headers' => $this->get_headers(),
				'body'    => $json_data,
				'timeout' => self::CONNECTION_TIMEOUT,
			]
		);

		$result = wp_remote_retrieve_body( $response );

		$result = json_decode( $result );

		if ( isset( $result->redirectUrl ) ) {
			return $result->redirectUrl;
		}

		throw new Exception( 'Something went wrong. Please try again later.' );
	}

	public function get_payment_status( $id ) {
		$sub_url = "/checkout/v2/order/{$id}/status";

		$endpoint = $this->api_endpoint . $sub_url;

		$response = wp_remote_get(
			$endpoint,
			[
				'headers' => $this->get_headers(),
				'timeout' => self::CONNECTION_TIMEOUT,
			]
		);

		$result = wp_remote_retrieve_body( $response );

		$result = json_decode( $result );

		return $result;
	}

	private function get_headers() {
		if ( empty( $this->access_token ) ) {
			$this->generate_access_token();
		}

		return [
			'Content-Type'  => 'application/json',
			'Authorization' => 'O-Bearer ' . $this->access_token,
		];
	}

	private function generate_access_token() {
		$payload = [
			'client_id'      => $this->config->client_id,
			'client_secret'  => $this->config->client_secret,
			'grant_type'     => 'client_credentials',
			'client_version' => $this->config->client_version,
		];

		$response = wp_remote_post(
			$this->auth_host_url,
			[
				'headers' => [
					'Content-Type' => 'application/x-www-form-urlencoded',
				],
				'body'    => $payload,
				'timeout' => self::CONNECTION_TIMEOUT,
			]
		);

		$result = wp_remote_retrieve_body( $response );

		$result = json_decode( $result );
		
		$this->access_token = $result->access_token;
	}
}

