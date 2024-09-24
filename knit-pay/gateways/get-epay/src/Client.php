<?php

namespace KnitPay\Gateways\GetEpay;

use Exception;

/**
 * Title: Get ePay API
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.87.0.0
 * @since   8.87.0.0
 */
class Client {
	const CONNECTION_TIMEOUT = 10;

	private $config;
	private $endpoint_url;

	public function __construct( Config $config, $test_mode ) {
		$this->config = $config;
		
		$this->set_endpoint_url( $test_mode );
	}
	
	private function set_endpoint_url( $test_mode ) {
		if ( $test_mode ) {
			$this->endpoint_url = 'https://pay1.getepay.in:8443/getepayPortal/pg/';
			return;
		}
		$this->endpoint_url = 'https://portal.getepay.in:8443/getepayPortal/pg/';
	}

	public function get_endpoint_url() {
		return $this->endpoint_url;
	}
	
	private function format_data( $data ) {
		$terminal_id = $this->config->terminal_id;
		$mid         = $this->config->mid;
		
		$data['mid']        = $mid;
		$data['terminalId'] = $terminal_id;

		$request = [
			'mid'        => $mid,
			'terminalId' => $terminal_id,
			'req'        => $this->encrypt( wp_json_encode( $data ) ),
		];
		
		return $request;
	}
	
	public function generate_invoice( $data ) {
		$request = $this->format_data( $data );
		
		$endpoint = $this->get_endpoint_url() . 'generateInvoice';
		
		$response = wp_remote_post(
			$endpoint,
			[
				'body'    => wp_json_encode( $request ),
				'headers' => [ 'Content-Type' => 'application/json' ],
				'timeout' => self::CONNECTION_TIMEOUT,
			]
		);
		$result   = wp_remote_retrieve_body( $response );
		
		$result = json_decode( $result );
		
		if ( isset( $result->status ) && 'FAILED' === $result->status ) {
			throw new Exception( $result->message );
		}
		
		if ( isset( $result->status ) && 'SUCCESS' === $result->status ) {
			$decrypted_response = $this->decrypt( $result->response );
			return $decrypted_response;
		}
		
		throw new Exception( 'Something went wrong. Please try again later.' );
	}
	
	public function get_invoice_status( $data = [] ) {
		$request = $this->format_data( $data );
		
		$endpoint = $this->get_endpoint_url() . 'invoiceStatus'; 
		
		$response = wp_remote_post(
			$endpoint,
			[
				'body'    => wp_json_encode( $request ),
				'headers' => [ 'Content-Type' => 'application/json' ],
				'timeout' => self::CONNECTION_TIMEOUT,
			]
		);
		$result   = wp_remote_retrieve_body( $response );
		
		$result = json_decode( $result );
		
		if ( ! isset( $result->status ) ) {
			throw new Exception( 'Something went wrong. Please try again later.' );
		}
		
		if ( 'FAILED' === $result->status ) {
			throw new Exception( $result->message );
		} else {
		
			$decrypted_response = $this->decrypt( $result->response );
			return $decrypted_response;
		}
		
	}
	
	/**
	 * Encrypt the string.
	 *
	 * @param string $plain_text Plain String.
	 * @return string Encrypted String
	 */
	public function encrypt( $plain_text ) {
		$key = base64_decode( $this->config->key );
		$iv  = base64_decode( $this->config->iv );
		
		$ciphertext_raw = openssl_encrypt( $plain_text, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
		$ciphertext     = bin2hex( $ciphertext_raw );
		$newCipher      = strtoupper( $ciphertext );
		
		return $newCipher;
	}
	
	/**
	 * Decrypt the Encrypted String.
	 *
	 * @param string $encrypted_text Encrypted String.
	 * @return string Plain String
	 */
	public function decrypt( $encrypted_text ) {
		$key = base64_decode( $this->config->key );
		$iv  = base64_decode( $this->config->iv );
		
		$ciphertext_raw     = hex2bin( $encrypted_text );
		$original_plaintext = openssl_decrypt( $ciphertext_raw, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
		return json_decode( $original_plaintext );
	}
}
