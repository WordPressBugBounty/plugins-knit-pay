<?php

namespace KnitPay\Gateways\SumUp;

/**
 * Title: SumUp Client
 * Copyright: 2020-2026 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.91.0.0
 * @since   8.91.0.0
 */
class Client {
	/**
	 * Config
	 *
	 * @var Config
	 */
	private $config;

	/**
	 * API Endpoints
	 */
	const API_URL = 'https://api.sumup.com/v0.1/';

	/**
	 * Constructor.
	 *
	 * @param Config $config Config.
	 */
	public function __construct( Config $config ) {
		$this->config = $config;
	}

	/**
	 * Create Checkout Session
	 *
	 * @param array $data Payment data.
	 * @return array
	 * @throws \Exception
	 */
	public function create_checkout_session( $data ) {
		$api_url = self::API_URL . 'checkouts';

		$args = [
			'method'  => 'POST',
			'headers' => [
				'Authorization' => 'Bearer ' . $this->get_access_token(),
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			],
			'body'    => wp_json_encode( $data ),
		];

		$response = wp_remote_post( $api_url, $args );

		if ( is_wp_error( $response ) ) {
			throw new \Exception( $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( $status_code !== 200 && $status_code !== 201 ) {
			$error_message = isset( $data['message'] ) ? $data['message'] : 'Unknown error occurred';
			throw new \Exception( 'SumUp API Error (' . $status_code . '): ' . $error_message );
		}

		if ( empty( $data['id'] ) ) {
			throw new \Exception( 'Invalid response from SumUp API: Missing checkout ID' );
		}

		return $data;
	}

	/**
	 * Get Checkout Status
	 *
	 * @param string $checkout_id Checkout ID.
	 * @return array
	 * @throws \Exception
	 */
	public function get_checkout_status( $checkout_id ) {
		$api_url = self::API_URL . 'checkouts/' . $checkout_id;

		$args = [
			'method'  => 'GET',
			'headers' => [
				'Authorization' => 'Bearer ' . $this->get_access_token(),
			],
		];

		$response = wp_remote_get( $api_url, $args );

		if ( is_wp_error( $response ) ) {
			throw new \Exception( $response->get_error_message() );
		}

		$body = wp_remote_retrieve_body( $response );
		return json_decode( $body, true );
	}

	/**
	 * Get access token using client credentials
	 *
	 * @return string
	 * @throws \Exception
	 */
	private function get_access_token() {
		$token_url = 'https://api.sumup.com/token';

		$args = [
			'method'  => 'POST',
			'headers' => [
				'Content-Type' => 'application/x-www-form-urlencoded',
			],
			'body'    => [
				'client_id'     => $this->config->client_id,
				'client_secret' => $this->config->client_secret,
				'grant_type'    => 'client_credentials',
				'scope'         => 'payments',
			],
		];

		$response = wp_remote_post( $token_url, $args );

		if ( is_wp_error( $response ) ) {
			throw new \Exception( $response->get_error_message() );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( isset( $data['error'] ) ) {
			throw new \Exception( $data['error_description'] );
		}

		return $data['access_token'];
	}
}
