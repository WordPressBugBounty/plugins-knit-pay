<?php

namespace KnitPay\Gateways\Tosla;

use Exception;

/**
 * Title: Tosla API Client
 * Copyright: 2020-2026 Knit Pay
 *
 * @author  Knit Pay
 * @version 9.6.0.0
 * @since   9.6.0.0
 */
class Client {
	private Config $config;

	const CONNECTION_TIMEOUT = 10;

	const TEST_API_ENDPOINT  = 'https://prepentegrasyon.tosla.com/api/Payment/';
	const LIVE_API_ENDPOINT  = 'https://entegrasyon.tosla.com/api/Payment/';

	public function __construct( Config $config ) {
		$this->config = $config;
	}

	/**
	 * Get API endpoint.
	 *
	 * @return string
	 */
	private function get_endpoint() {
		if ( Gateway::MODE_TEST === $this->config->mode ) {
			return self::TEST_API_ENDPOINT;
		}
		return self::LIVE_API_ENDPOINT;
	}

	/**
	 * Generate hash for authentication.
	 *
	 * Hash = base64_encode( sha512( apiPass + clientId + apiUser + rnd + timeSpan ) )
	 *
	 * @return array
	 */
	private function get_auth_params() {
		$rnd       = bin2hex( random_bytes( 12 ) ); // 24 hex chars
		$time_span = wp_date( 'YmdHis', null, new \DateTimeZone( 'Europe/Istanbul' ) );

		$hash_string = $this->config->api_pass . $this->config->client_id . $this->config->api_user . $rnd . $time_span;
		$hash        = base64_encode( hash( 'sha512', $hash_string, true ) );

		return [
			'clientId' => (int) $this->config->client_id,
			'apiUser'  => $this->config->api_user,
			'rnd'      => $rnd,
			'timeSpan' => $time_span,
			'hash'     => $hash,
		];
	}

	/**
	 * Create 3D Secure payment session.
	 *
	 * @param array $data Payment data.
	 * @return string ThreeDSessionId
	 * @throws Exception
	 */
	public function create_three_d_payment( $data ) {
		$endpoint = $this->get_endpoint() . 'threeDPayment';

		$body = array_merge( $this->get_auth_params(), $data );

		$json_body = wp_json_encode( $body );

		$response = wp_safe_remote_post(
			$endpoint,
			[
				'body'    => $json_body,
				'headers' => [
					'Content-Type' => 'application/json',
				],
				'timeout' => self::CONNECTION_TIMEOUT,
			]
		);

		if ( is_wp_error( $response ) ) {
			throw new Exception( esc_html( $response->get_error_message() ) );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$result        = wp_remote_retrieve_body( $response );

		if ( 200 !== $response_code || empty( $result ) ) {
			throw new Exception( 'Tosla API returned HTTP ' . $response_code . '. Please try again later.' );
		}

		$result = json_decode( $result );

		if ( ! is_object( $result ) ) {
			throw new Exception( 'Invalid response from Tosla. Please try again later.' );
		}

		if ( isset( $result->error ) && ! empty( $result->error ) ) {
			throw new Exception( esc_html( $result->error ) );
		}

		if ( ! empty( $result->ThreeDSessionId ) ) {
			return sanitize_text_field( $result->ThreeDSessionId );
		}

		throw new Exception( 'Something went wrong. Please try again later.' );
	}

	/**
	 * Inquiry payment status.
	 *
	 * @param string $order_id Order ID (transaction ID).
	 * @return object
	 * @throws Exception
	 */
	public function inquiry( $order_id ) {
		$endpoint = $this->get_endpoint() . 'inquiry';

		$body = array_merge(
			$this->get_auth_params(),
			[
				'orderId' => $order_id,
			]
		);

		$json_body = wp_json_encode( $body );

		$response = wp_safe_remote_post(
			$endpoint,
			[
				'body'    => $json_body,
				'headers' => [
					'Content-Type' => 'application/json',
				],
				'timeout' => self::CONNECTION_TIMEOUT,
			]
		);

		if ( is_wp_error( $response ) ) {
			throw new Exception( esc_html( $response->get_error_message() ) );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$result        = wp_remote_retrieve_body( $response );

		if ( 200 !== $response_code || empty( $result ) ) {
			throw new Exception( 'Tosla inquiry returned HTTP ' . $response_code . '. Please try again later.' );
		}

		$result = json_decode( $result );

		if ( ! is_object( $result ) ) {
			throw new Exception( 'Invalid inquiry response from Tosla. Please try again later.' );
		}

		if ( isset( $result->error ) && ! empty( $result->error ) ) {
			throw new Exception( esc_html( $result->error ) );
		}

		return $result;
	}

	/**
	 * Get the 3D Secure redirect URL.
	 *
	 * @param string $three_d_session_id ThreeDSessionId.
	 * @return string
	 */
	public function get_three_d_secure_url( $three_d_session_id ) {
		$base_url = self::LIVE_API_ENDPOINT;
		if ( Gateway::MODE_TEST === $this->config->mode ) {
			$base_url = self::TEST_API_ENDPOINT;
		}

		return $base_url . 'threeDSecure/' . $three_d_session_id;
	}
}
