<?php

namespace KnitPay\Gateways\Slydepay;

use Exception;

/**
 * Title: Slydepay API
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 6.67.0.0
 * @since   6.67.0.0
 */
class API {

	const CONNECTION_TIMEOUT = 10;
	const API_RETRY_COUNT    = 3;

	private $api_endpoint = 'https://app.slydepay.com.gh/api/merchant/';

	private $merchant_email;

	private $api_key;

	public function __construct( $merchant_email, $api_key ) {
		$this->merchant_email = $merchant_email;
		$this->api_key        = $api_key;
	}

	public function create_invoice( $data, $retry = self::API_RETRY_COUNT ) {
		if ( 0 === $retry ) {
			throw new Exception( 'Something went wrong. Please try again later.' );
		}
		$endpoint = $this->api_endpoint . 'invoice/create';
		$api_data = wp_parse_args( $data, $this->get_api_data() );

		$response = wp_remote_post(
			$endpoint,
			[
				'body'    => wp_json_encode( $api_data ),
				'headers' => [ 'Content-Type' => 'application/json' ],
				'timeout' => self::CONNECTION_TIMEOUT,
			]
		);
		$result   = wp_remote_retrieve_body( $response );

		$result = json_decode( $result );
		if ( isset( $result->success ) && $result->success ) {
			return $result->result->payToken;
		}

		if ( isset( $result->errorMessage ) ) {
			throw new Exception( trim( $result->errorMessage ) );
		}
		sleep( 1 );
		return self::create_invoice( $data, --$retry );
	}

	public function check_payment_status( $data, $retry = self::API_RETRY_COUNT ) {
		if ( 0 === $retry ) {
			throw new Exception( 'Something went wrong. Please try again later.' );
		}

		$endpoint = $this->api_endpoint . 'invoice/checkstatus';
		$api_data = wp_parse_args( $data, $this->get_api_data() );

		$response = wp_remote_post(
			$endpoint,
			[
				'body'    => wp_json_encode( $api_data ),
				'headers' => [ 'Content-Type' => 'application/json' ],
				'timeout' => self::CONNECTION_TIMEOUT,
			]
		);
		$result   = wp_remote_retrieve_body( $response );

		$result = json_decode( $result );
		if ( isset( $result->success ) && $result->success ) {
			return $result;
		}

		if ( isset( $result->errorMessage ) ) {
		    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			throw new Exception( 'Unable to Check Payment Status: Server Responds ' . print_R( $result->reason, true ) );
		}
		sleep( 1 );
		return self::check_payment_status( $data, --$retry );
	}

	private function get_api_data() {
		return [
			'emailOrMobileNumber' => $this->merchant_email,
			'merchantKey'         => $this->api_key,
		];
	}
}
