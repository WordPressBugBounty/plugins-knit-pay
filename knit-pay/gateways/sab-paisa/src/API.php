<?php

namespace KnitPay\Gateways\SabPaisa;

use Exception;

/**
 * Title: Sab Paisa API
 * Copyright: 2020-2025 Knit Pay
 *
 * @author Knit Pay
 * @version 8.75.3.0
 * @since 8.75.3.0
 */

class API {
	private $config;
	private $mode;

	private const OPENSSL_CIPHER_NAME = 'aes-128-cbc';
	private const CIPHER_KEY_LEN      = 16;

	public function __construct( Config $config, $mode ) {
		$this->config = $config;
		$this->mode   = $mode;
	}

	public function get_endpoint_url() {
		if ( 'test' === $this->mode ) {
			return 'https://stage-securepay.sabpaisa.in/SabPaisa/sabPaisaInit?v=1';
		} else {
			return 'https://securepay.sabpaisa.in/SabPaisa/sabPaisaInit?v=1';
		}
	}

	private static function fixKey( $key ) {
		if ( strlen( $key ) < self::CIPHER_KEY_LEN ) {
			return str_pad( "$key", self::CIPHER_KEY_LEN, '0' );
		}

		if ( strlen( $key ) > self::CIPHER_KEY_LEN ) {
			return substr( $key, 0, self::CIPHER_KEY_LEN );
		}
		return $key;
	}

	public function get_transaction_status( $transaction_id ) {
		if ( 'test' === $this->mode ) {
			$url = 'https://stage-txnenquiry.sabpaisa.in/SPTxtnEnquiry/getTxnStatusByClientxnId';
		} else {
			$url = 'https://txnenquiry.sabpaisa.in/SPTxtnEnquiry/getTxnStatusByClientxnId';
		}

		$data           = [
			'clientCode'  => $this->config->client_code,
			'clientTxnId' => $transaction_id,
		];
		$encrypted_data = $this->get_encrypted_data_array( $data, 'statusTransEncData' );

		$response = wp_remote_post(
			$url,
			[
				'body'    => wp_json_encode( $encrypted_data ),
				'headers' => $this->get_request_headers(),
			]
		);
		$result   = wp_remote_retrieve_body( $response );
		$result   = json_decode( $result, true );

		if ( ! isset( $result['statusResponseData'] ) ) {
			throw new Exception( 'Something went wrong. Please try again later.' );
		}

		$dec_text = $this->decrypt( $result['statusResponseData'] );

		parse_str( $dec_text, $result_array );

		return $result_array;
	}

	private function get_request_headers() {
		$headers = [
			'Accept'       => 'application/json',
			'Content-Type' => 'application/json',
		];
		return $headers;
	}

	private function encrypt( $data ) {
		$key = $this->config->auth_key;
		$iv  = $this->config->auth_iv;

		$encodedEncryptedData = base64_encode( openssl_encrypt( $data, self::OPENSSL_CIPHER_NAME, self::fixKey( $key ), OPENSSL_RAW_DATA, $iv ) );
		$encodedIV            = base64_encode( $iv );
		$encryptedPayload     = $encodedEncryptedData . ':' . $encodedIV;

		return $encryptedPayload;
	}

	private function decrypt( $data ) {
		$key = $this->config->auth_key;

		$parts     = explode( ':', $data );
		$encrypted = $parts[0];
		// $iv = $parts[1];
		$decryptedData = openssl_decrypt( base64_decode( $encrypted ), self::OPENSSL_CIPHER_NAME, self::fixKey( $key ), OPENSSL_RAW_DATA );
		return $decryptedData;
	}

	public function get_encrypted_data_array( $data, $data_key = 'encData' ) {
		$data = $this->encrypt( build_query( $data ) );

		return [
			$data_key    => $data,
			'clientCode' => $this->config->client_code,
		];
	}
}
