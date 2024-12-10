<?php

namespace KnitPay\Gateways\HdfcSmartGateway;

use Exception;

class API {
	const CONNECTION_TIMEOUT = 10;

	/**
	 * @var Config
	 */
	private $config;

	/**
	 * @var string
	 */
	private $api_endpoint;

	public function __construct( $config ) {
		$this->config = $config;

		if ( $this->config->mode == 'live' ) {
			$this->api_endpoint = 'https://smartgateway.hdfcbank.com/';
		} else {
			$this->api_endpoint = 'https://smartgatewayuat.hdfcbank.com/';
		}
	}

	public function create_session( $data ) {

		$endpoint = $this->api_endpoint . 'session';

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

		if ( ! isset( $result->status ) ) {
			throw new Exception( 'Something went wrong. Please try again later.' );
		}

		if ( 'NEW' === $result->status ) {
			return $result->payment_links->web;
		} else {
			throw new Exception( $result->error_info->developer_message );
		}

		throw new Exception( 'Something went wrong. Please try again later.' );
	}

	public function get_order_status( $order_id ) {
		$endpoint = $this->api_endpoint . 'orders/' . $order_id;

		$response = wp_remote_get(
			$endpoint,
			[
				'headers' => $this->get_request_headers(),
				'timeout' => self::CONNECTION_TIMEOUT,
			]
		);
		$result   = wp_remote_retrieve_body( $response );

		$result = json_decode( $result );

		if ( 'error' === $result->status ) {
			throw new Exception( $result->error_info->developer_message );
		} elseif ( isset( $result->status ) ) {
			return $result;
		}

		throw new Exception( 'Something went wrong. Please try again later.' );
	}

	private function get_request_headers() {
		return [
			'Authorization' => 'Basic ' . base64_encode( $this->config->api_key . ':' ),
			'Content-Type'  => 'application/json',
			'x-merchantid'  => $this->config->merchant_id,
		];
	}
}
