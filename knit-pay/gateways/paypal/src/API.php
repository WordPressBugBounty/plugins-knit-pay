<?php

namespace KnitPay\Gateways\Paypal;

use Exception;

/**
 * Title: Paypal API Client
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 8.94.0.0
 * @since 8.94.0.0
 */
class API {
	private $config;

	public function __construct( $config ) {
		$this->config = $config;

		if ( 'test' === $this->config->mode ) {
			$this->api_base_url = 'https://api-m.sandbox.paypal.com/v2/checkout/';
		} else {
			$this->api_base_url = 'https://api-m.paypal.com/v2/checkout/';
		}
	}

	public function create_order( $data ) {
		$endpoint = $this->api_base_url . 'orders';

		$response = wp_remote_post(
			$endpoint,
			[
				'body'    => wp_json_encode( $data ),
				'headers' => $this->get_request_headers(),
			]
		);
		$result   = wp_remote_retrieve_body( $response );

		$result = json_decode( $result );

		if ( isset( $result->message ) ) {
			throw new Exception( trim( $result->message ) );
		}

		if ( isset( $result->id ) ) {
			return $result;
		}

		throw new Exception( 'Something went wrong. Please try again later.' );
	}

	public function get_order_details( $transaction_id ) {
		$endpoint = $this->api_base_url . 'orders/' . $transaction_id;

		$response = wp_remote_get(
			$endpoint,
			[
				'headers' => $this->get_request_headers(),
			]
		);
		$result   = wp_remote_retrieve_body( $response );

		$result = json_decode( $result );

		if ( ! isset( $result->status ) ) {
			throw new Exception( 'Something went wrong. Please try again later.' );
		}

		if ( $transaction_id === $result->id ) {
			return $result;
		}

		throw new Exception( 'Something went wrong. Please try again later.' );
	}

	private function get_request_headers() {
		return [
			'Authorization' => 'Basic ' . base64_encode( $this->config->client_id . ':' . $this->config->client_secret ),
			'Content-Type'  => 'application/json',
		];
	}
}
