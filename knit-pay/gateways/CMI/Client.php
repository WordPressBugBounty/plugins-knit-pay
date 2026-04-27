<?php

namespace KnitPay\Gateways\CMI;

use Exception;

/**
 * Title: CMI API Client
 * Copyright: 2020-2026 Knit Pay
 *
 * @author Knit Pay
 * @version 8.96.5.0
 * @since 8.96.4.0
 */
class Client {
	private $config;

	public function __construct( $config ) {
		$this->config = $config;
	}

	public function get_endpoint_url() {
		$endpoint_urls = [
			Gateway::MODE_TEST => 'https://testpayment.cmi.co.ma',
			Gateway::MODE_LIVE => 'https://payment.cmi.co.ma',
		];
		return $endpoint_urls[ $this->config->mode ];
	}

	public function get_order_status( $order_id ) {
		// Prepare XML request
		$xml = new \SimpleXMLElement( '<?xml version="1.0" encoding="UTF-8"?><CC5Request></CC5Request>' );

		// Add required elements
		$xml->addChild( 'Name', $this->config->username );
		$xml->addChild( 'Password', $this->config->password );
		$xml->addChild( 'ClientId', $this->config->client_id );
		$xml->addChild( 'OrderId', $order_id );

		// Add Extra element with ORDERHISTORY
		$extra = $xml->addChild( 'Extra' );
		$extra->addChild( 'ORDERHISTORY', 'QUERY' );

		// Make API request
		$response = wp_remote_post(
			$this->get_endpoint_url() . '/fim/api',
			[
				'headers' => [
					'Content-Type' => 'application/xml',
				],
				'body'    => $xml->asXML(),
			]
		);

		// Check for errors
		if ( is_wp_error( $response ) ) {
			throw new Exception( esc_html( $response->get_error_message() ) );
		}

		// Get response body
		$body = wp_remote_retrieve_body( $response );

		// Parse XML response
		$xml_response = simplexml_load_string( $body );
		if ( false === $xml_response ) {
			throw new Exception( 'Failed to parse XML response' );
		}

		// Convert to array
		$result = json_decode( wp_json_encode( $xml_response ), true );

		if ( empty( $result ) ) {
			throw new Exception( 'Invalid response from CMI' );
		}

		if ( ! empty( $result['ErrMsg'] ) ) {
			throw new Exception( esc_html( $result['ErrMsg'] ) );
		}

		$order_status = explode( "\t", $result['Extra']['TRX1'] );

		return array_merge(
			$order_status,
			[
				'ProcReturnCode' => $order_status[9],
			]
		);
	}

	public function generate_hash( array $data ): string {
		// Assign store key
		$store_key = $this->config->store_key;

		// Retrieve and sort parameters
		$cmi_params  = $data;
		$post_params = array_keys( $cmi_params );
		natcasesort( $post_params );

		// Construct hash input string
		$hashval = '';
		foreach ( $post_params as $param ) {
			if ( null === $cmi_params[ $param ] ) {
				$hashval .= '|';
				continue;
			}

			$param_value         = trim( $cmi_params[ $param ] );
			$escaped_param_value = str_replace( '|', '\\|', str_replace( '\\', '\\\\', $param_value ) );
			$lower_param         = strtolower( $param );
			if ( 'hash' !== $lower_param && 'encoding' !== $lower_param ) {
				$hashval .= $escaped_param_value . '|';
			}
		}

		// Append store_key and prepare for hashing
		$escaped_store_key = str_replace( '|', '\\|', str_replace( '\\', '\\\\', $store_key ) );
		$hashval          .= $escaped_store_key;

		// Calculate hash
		$calculated_hash_value = hash( 'sha512', $hashval );
		$hash                  = base64_encode( pack( 'H*', $calculated_hash_value ) );

		return $hash;
	}
}
