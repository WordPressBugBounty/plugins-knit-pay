<?php

namespace KnitPay\Gateways\Pesapal;

use Exception;

/**
 * Title: Pesapal API Client
 * Copyright: 2020-2025 Knit Pay
 *
 * @author Knit Pay
 * @version 9.2.0.0
 * @since 9.2.0.0
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
	 * Bearer token for API authentication
	 * 
	 * @var string|null
	 */
	private $bearer_token = null;

	/**
	 * Constructor - Initialize the API client with configuration
	 * 
	 * @param Config $config Gateway configuration object containing credentials
	 */
	public function __construct( Config $config ) {
		$this->config = $config;
		
		// Set API endpoint URL based on mode (test/live)
		$mode          = $config->mode ?? 'live';
		$this->api_url = ( 'test' === $mode ) 
			? 'https://cybqa.pesapal.com/pesapalv3/api' 
			: 'https://pay.pesapal.com/v3/api';
		
		// Don't authenticate here - lazy load token when needed
	}
	
	/**
	 * Authenticate with Pesapal API and get bearer token
	 * Token is generated only when needed (lazy loading)
	 * 
	 * @throws Exception When authentication fails
	 */
	private function authenticate() {
		// Return existing token if already authenticated
		if ( ! empty( $this->bearer_token ) ) {
			return;
		}
		
		$endpoint = $this->api_url . '/Auth/RequestToken';
		
		$auth_data = [
			'consumer_key'    => $this->config->consumer_key,
			'consumer_secret' => $this->config->consumer_secret,
		];
		
		$response = wp_remote_post(
			$endpoint,
			[
				'body'    => wp_json_encode( $auth_data ),
				'headers' => [
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				],
				'timeout' => 30,
			]
		);
		
		if ( is_wp_error( $response ) ) {
			throw new Exception( 'Authentication failed: ' . $response->get_error_message() );
		}
		
		$body   = wp_remote_retrieve_body( $response );
		$result = json_decode( $body );
		
		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code >= 400 ) {
			$error_message = $result->message ?? $result->error ?? 'Authentication failed with HTTP ' . $status_code;
			throw new Exception( $error_message );
		}
		
		if ( empty( $result->token ) ) {
			throw new Exception( 'No token received from Pesapal' );
		}
		
		$this->bearer_token = $result->token;
	}
	
	/**
	 * Create a payment on Pesapal
	 * 
	 * @param array $data Payment data formatted for Pesapal's API
	 * @return object Pesapal response object
	 * @throws Exception When API request fails
	 */
	public function create_payment( array $data ) {
		// Authenticate before making API call
		$this->authenticate();
		
		$endpoint = $this->api_url . '/Transactions/SubmitOrderRequest';
		
		$response = wp_remote_post(
			$endpoint,
			[
				'body'    => wp_json_encode( $data ),
				'headers' => $this->get_request_headers(),
				'timeout' => 30,
			]
		);
		
		if ( is_wp_error( $response ) ) {
			throw new Exception( 'API request failed: ' . $response->get_error_message() );
		}
		
		$body   = wp_remote_retrieve_body( $response );
		$result = json_decode( $body );
		
		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code >= 400 ) {
			$error_message = $result->message ?? $result->error ?? 'HTTP Error ' . $status_code;
			throw new Exception( $error_message );
		}
		
		if ( isset( $result->error ) ) {
			throw new Exception( $result->error->message ?? 'Payment creation failed' );
		}
		
		return $result;
	}
	
	/**
	 * Get payment status from Pesapal
	 * 
	 * @param string $order_tracking_id Order tracking ID from Pesapal
	 * @return object Payment details from Pesapal
	 * @throws Exception When payment is not found or API request fails
	 */
	public function get_payment_status( string $order_tracking_id ) {
		// Authenticate before making API call
		$this->authenticate();
		
		$endpoint = $this->api_url . '/Transactions/GetTransactionStatus?orderTrackingId=' . urlencode( $order_tracking_id );
		
		$response = wp_remote_get(
			$endpoint,
			[
				'headers' => $this->get_request_headers(),
				'timeout' => 30,
			]
		);
		
		if ( is_wp_error( $response ) ) {
			throw new Exception( 'Failed to get payment status: ' . $response->get_error_message() );
		}
		
		$body   = wp_remote_retrieve_body( $response );
		$result = json_decode( $body );
		
		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code >= 400 ) {
			$error_message = $result->message ?? $result->error ?? 'Payment not found';
			throw new Exception( $error_message );
		}
		
		return $result;
	}
	
	/**
	 * Register IPN (Instant Payment Notification) endpoint
	 * 
	 * @param string $notification_url Webhook URL to receive notifications
	 * @return object IPN registration response
	 * @throws Exception When IPN registration fails
	 */
	public function register_ipn( string $notification_url ) {
		// Authenticate before making API call
		$this->authenticate();
		
		$endpoint = $this->api_url . '/URLSetup/RegisterIPN';
		
		$ipn_data = [
			'url'                   => $notification_url,
			'ipn_notification_type' => 'GET',
		];
		
		$response = wp_remote_post(
			$endpoint,
			[
				'body'    => wp_json_encode( $ipn_data ),
				'headers' => $this->get_request_headers(),
				'timeout' => 30,
			]
		);
		
		if ( is_wp_error( $response ) ) {
			throw new Exception( 'IPN registration failed: ' . $response->get_error_message() );
		}
		
		$body   = wp_remote_retrieve_body( $response );
		$result = json_decode( $body );
		
		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code >= 400 ) {
			$error_message = $result->message ?? $result->error ?? 'IPN registration failed';
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
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
			'Authorization' => 'Bearer ' . $this->bearer_token,
		];
		
		return $headers;
	}
}
