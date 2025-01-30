<?php

namespace KnitPay\Gateways\CMI;

use Exception;

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
			throw new Exception( $response->get_error_message() );
		}

		// Get response body
		$body = wp_remote_retrieve_body( $response );

		// Parse XML response
		$xml_response = simplexml_load_string( $body );
		if ( $xml_response === false ) {
			throw new Exception( 'Failed to parse XML response' );
		}

		// Convert to array
		$result = json_decode( wp_json_encode( $xml_response ), true );

		if ( empty( $result ) ) {
			throw new Exception( 'Invalid response from CMI' );
		}

		if ( ! empty( $result['ErrMsg'] ) ) {
			throw new Exception( $result['ErrMsg'] );
		}

		return $result;
	}

	public function generate_hash( array $data ): string {
		// Assign store key
		$storeKey = $this->config->store_key;

		// Retrieve and sort parameters
		$cmiParams  = $data;
		$postParams = array_keys( $cmiParams );
		natcasesort( $postParams );

		// Construct hash input string
		$hashval = '';
		foreach ( $postParams as $param ) {
			if ( null === $cmiParams[ $param ] ) {
				$hashval .= '|';
				continue;
			}

			$paramValue        = trim( $cmiParams[ $param ] );
			$escapedParamValue = str_replace( '|', '\\|', str_replace( '\\', '\\\\', $paramValue ) );
			$lowerParam        = strtolower( $param );
			if ( $lowerParam !== 'hash' && $lowerParam !== 'encoding' ) {
				$hashval .= $escapedParamValue . '|';
			}
		}

		// Append storeKey and prepare for hashing
		$escapedStoreKey = str_replace( '|', '\\|', str_replace( '\\', '\\\\', $storeKey ) );
		$hashval        .= $escapedStoreKey;

		// Calculate hash
		$calculatedHashValue = hash( 'sha512', $hashval );
		$hash                = base64_encode( pack( 'H*', $calculatedHashValue ) );

		return $hash;
	}
}
