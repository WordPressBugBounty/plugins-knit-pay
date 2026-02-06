<?php

namespace KnitPay\Gateways\Revolut;

use Exception;

/**
 * Title: Revolut API Client
 * Copyright: 2020-2026 Knit Pay
 *
 * @author Knit Pay
 * @version 8.98.0.0
 * @since 8.98.0.0
 */
class Client {
	/**
	 * Configuration object
	 *
	 * @var Config
	 */
	private $config;
	
	/**
	 * API endpoint URL
	 *
	 * @var string
	 */
	private $api_url;

	/**
	 * Constructor - Initialize the API client with configuration
	 *
	 * @param Config $config Gateway configuration object containing credentials
	 */
	public function __construct( Config $config ) {
		$this->config = $config;
		
		// Set API endpoint based on mode
		$mode          = $config->mode ?? 'live';
		$this->api_url = ( 'test' === $mode )
			? 'https://sandbox-merchant.revolut.com/api'
			: 'https://merchant.revolut.com/api';
	}
	
	/**
	 * Create an order on Revolut
	 *
	 * @param array $data Order data formatted for Revolut's API
	 * @return object Revolut response object
	 * @throws Exception When API request fails
	 */
	public function create_order( array $data ) {
		$endpoint = $this->api_url . '/orders';
		
		$response = wp_remote_post(
			$endpoint,
			[
				'body'    => wp_json_encode( $data ),
				'headers' => $this->get_request_headers(),
				'timeout' => 30,
			]
		);
		
		// Handle WordPress HTTP errors
		if ( is_wp_error( $response ) ) {
			throw new Exception( 'API request failed: ' . $response->get_error_message() );
		}
		
		// Get response body
		$body   = wp_remote_retrieve_body( $response );
		$result = json_decode( $body );
		
		// Check HTTP status code
		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code >= 400 ) {
			$error_message = $result->message ?? 'HTTP Error ' . $status_code;
			if ( isset( $result->code ) ) {
				$error_message = $result->code . ': ' . $error_message;
			}
			throw new Exception( $error_message );
		}
		
		return $result;
	}
	
	/**
	 * Retrieve an order from Revolut
	 *
	 * @param string $order_id Order ID from Revolut
	 * @return object Order details from Revolut
	 * @throws Exception When order is not found or API request fails
	 */
	public function retrieve_order( string $order_id ) {
		$endpoint = $this->api_url . '/orders/' . $order_id;
		
		$response = wp_remote_get(
			$endpoint,
			[
				'headers' => $this->get_request_headers(),
				'timeout' => 30,
			]
		);
		
		// Handle WordPress HTTP errors
		if ( is_wp_error( $response ) ) {
			throw new Exception( 'Failed to get order status: ' . $response->get_error_message() );
		}
		
		// Get response body
		$body   = wp_remote_retrieve_body( $response );
		$result = json_decode( $body );
		
		// Check HTTP status code
		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code >= 400 ) {
			$error_message = $result->message ?? 'Order not found';
			throw new Exception( $error_message );
		}
		
		return $result;
	}

	/**
	 * Get request headers for API calls
	 *
	 * @return array Headers array for wp_remote_* functions
	 */
	private function get_request_headers() {
		$headers = [
			'Content-Type'        => 'application/json',
			'Accept'              => 'application/json',
			'Revolut-Api-Version' => '2025-10-16',
			'Authorization'       => 'Bearer ' . $this->config->api_secret_key,
		];
		
		return $headers;
	}
}
