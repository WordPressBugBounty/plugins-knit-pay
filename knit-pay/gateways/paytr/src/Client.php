<?php

namespace KnitPay\Gateways\Paytr;

use Exception;

/**
 * Title: PayTR API Client
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.86.0.0
 * @since   8.86.0.0
 */
class Client {

	const CONNECTION_TIMEOUT = 10;

	private $api_endpoint;

	public function __construct() {
		$this->set_endpoint();
	}

	private function set_endpoint() {
		$this->api_endpoint = 'https://www.paytr.com/odeme/api/';
	}

	public function get_endpoint() {
		return $this->api_endpoint;
	}

	public function get_token( $data ) {
		$endpoint = $this->get_endpoint() . 'get-token';

		$response = wp_remote_post(
			$endpoint,
			[
				'body'    => $data,
				'timeout' => self::CONNECTION_TIMEOUT,
			]
		);
		$result   = wp_remote_retrieve_body( $response );

		$result = json_decode( $result );
		if ( isset( $result->status ) && 'failed' === $result->status ) {
			throw new Exception( $result->reason );
		}

		if ( isset( $result->status ) && 'success' === $result->status ) {
			return $result->token;
		}

		throw new Exception( 'Something went wrong. Please try again later.' );
	}
}
