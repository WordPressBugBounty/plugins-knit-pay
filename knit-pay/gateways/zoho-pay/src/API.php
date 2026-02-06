<?php

namespace KnitPay\Gateways\ZohoPay;

use Exception;

/**
 * Title: Zoho Pay API Client.
 * Copyright: 2020-2026 Knit Pay
 *
 * @author  Knit Pay
 * @version 9.0.0.0
 * @since   9.0.0.0
 */
class API {
	const CONNECTION_TIMEOUT = 30;
	private $config;

	public function __construct( $config ) {
		$this->config = $config;
	}

	private function get_endpoint() {
		return 'https://payments.zoho.in/api/v1/';
	}

	public function create_payment_link( $data ) {
		$endpoint = $this->get_endpoint() . 'paymentlinks';

		$result = $this->create_connection( $endpoint, 'POST', $data );

		if ( isset( $result->code ) && 0 === $result->code ) {
			return $result->payment_links;
		}

		throw new Exception( 'Something went wrong. Please try again later.' );
	}

	public function retrieve_payment_link( $transaction_id ) {
		$endpoint = $this->get_endpoint() . 'paymentlinks/' . $transaction_id;

		$result = $this->create_connection( $endpoint, 'GET' );

		if ( isset( $result->code ) && 0 === $result->code ) {
			return $result->payment_links;
		}

		throw new Exception( 'Something went wrong. Please try again later.' );
	}

	private function create_connection( $url, $method, $data = [], $allow_retry = true ) {
		$args = [
			'method'  => $method,
			'headers' => $this->get_request_headers(),
			'timeout' => self::CONNECTION_TIMEOUT,
		];

		if ( 'POST' === $method ) {
			$args['body'] = wp_json_encode( $data );
		}

		$url = add_query_arg( 
			[
				'account_id' => $this->config->account_id,
			],
			$url 
		);

		$response = wp_remote_request( $url, $args );

		if ( 401 === wp_remote_retrieve_response_code( $response ) && $this->config->access_token && $allow_retry ) {
			// Refresh access token.
			$integration = new Integration();
			$integration->refresh_access_token( $this->config->config_id );
			$this->config = $integration->get_config( $this->config->config_id );

			// Retry request.
			return $this->create_connection( $url, $method, $data, false );
		}

		$result = wp_remote_retrieve_body( $response );

		$result = json_decode( $result );
		if ( isset( $result->code ) && 0 !== $result->code ) {
			throw new Exception( $result->message );
		}

		return $result;
	}

	private function get_request_headers() {
		$headers = [
			'Content-Type'  => 'application/json',
			'Authorization' => 'Zoho-oauthtoken ' . $this->config->access_token,
		];

		return $headers;
	}
}
