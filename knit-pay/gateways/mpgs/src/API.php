<?php

namespace KnitPay\Gateways\MPGS;

use Exception;

/**
 * Title: MPGS API
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.81.0.0
 * @since   8.81.0.0
 */
class API {

	const CONNECTION_TIMEOUT = 10;
	
	const API_VERSION = 66;

	private $config;

	public function __construct( $config ) {
		$this->config = $config;
	}

	private function get_endpoint() {
		return $this->config->mpgs_url . '/api/rest/version/' . self::API_VERSION . '/merchant/' . $this->config->merchant_id;
	}

	public function initiate_checkout( $data ) {
		$endpoint = $this->get_endpoint() . '/session';

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
		if ( isset( $result->result ) && 'ERROR' === $result->result ) {
			throw new Exception( $result->error->explanation );
		}

		if ( isset( $result->session ) ) {
			return $result;
		}

		throw new Exception( 'Something went wrong. Please try again later.' );
	}

	public function get_order( $id ) {
		$endpoint = $this->get_endpoint() . "/order/{$id}";
		
		$response = wp_remote_get(
			$endpoint,
			[
				'headers' => $this->get_request_headers(),
				'timeout' => self::CONNECTION_TIMEOUT,
			]
		);
		$result   = wp_remote_retrieve_body( $response );
		
		$result = json_decode( $result );
		if ( isset( $result->result ) && 'SUCCESS' === $result->result ) {
			return $result;
		}
		
		if ( isset( $result->error ) ) {
			throw new Exception( $result->error->explanation );
		}
		
		throw new Exception( 'Something went wrong. Please try again later.' );
	}

	private function get_request_headers() {
		return [
			'Authorization' => 'Basic ' . base64_encode( 'merchant.' . $this->config->merchant_id . ':' . $this->config->auth_pass ),
		];
	}
}
