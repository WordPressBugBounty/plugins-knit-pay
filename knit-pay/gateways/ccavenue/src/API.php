<?php

namespace KnitPay\Gateways\CCAvenue;

use Exception;

/**
 * Title: CCAvenue API
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 8.75.3.0
 * @since 8.75.3.0
 */
class API {
	private $config;
	private $endpoint_url;
	private $api_endpoint_url;

	public function __construct( Config $config, $mode ) {
		$this->config = $config;

		$endpoint_urls = [
			'in' => [
				'trans' => [
					Gateway::MODE_TEST => 'https://test.ccavenue.com',
					Gateway::MODE_LIVE => 'https://secure.ccavenue.com',
				],
				'api'   => [
					Gateway::MODE_TEST => 'https://apitest.ccavenue.com/apis/servlet/DoWebTrans',
					Gateway::MODE_LIVE => 'https://api.ccavenue.com/apis/servlet/DoWebTrans',
				],
			],
			'ae' => [
				'trans' => [
					Gateway::MODE_TEST => 'https://secure.ccavenue.ae',
					Gateway::MODE_LIVE => 'https://secure.ccavenue.ae',
				],
				'api'   => [
					Gateway::MODE_TEST => 'https://apitest.ccavenue.ae/apis/servlet/DoWebTrans',
					Gateway::MODE_LIVE => 'https://login.ccavenue.ae/apis/servlet/DoWebTrans',
				],
			],
		];

		$this->endpoint_url     = $endpoint_urls[ $config->country ]['trans'][ $mode ]; // Endpoint for transaction.
		$this->api_endpoint_url = $endpoint_urls[ $config->country ]['api'][ $mode ];
	}

	public function get_endpoint_url() {
		return $this->endpoint_url;
	}

	public function get_order_status( $order_id ) {
		$data           = [
			'order_no' => $order_id,
		];
		$encrypted_data = self::encrypt( wp_json_encode( $data ) );

		// Fill POST fields array.
		$post_fields = [
			'enc_request'  => $encrypted_data,
			'access_code'  => $this->config->access_code,
			'command'      => 'orderStatusTracker',
			'request_type' => 'JSON',
		];

		$response = wp_remote_post(
			$this->api_endpoint_url,
			[
				'body' => $post_fields,
			]
		);
		$result   = wp_remote_retrieve_body( $response );

		$result_array = [];
		parse_str( $result, $result_array );

		if ( ! isset( $result_array['status'] ) ) {
			throw new Exception( 'Something went wrong. Please try again later.' );
		}

		if ( $result_array['status'] && isset( $result_array['enc_response'] ) ) {
			throw new Exception( $result_array['enc_response'] );
		} else {
			$response = json_decode( $this->decrypt( $result_array['enc_response'] ), true )['Order_Status_Result'];
			if ( $response['status'] ) {
				throw new Exception( $response['error_desc'] );
			}
			return $response;
		}

		throw new Exception( 'Something went wrong. Please try again later.' );
	}
	
	/**
	 * Encrypt the string.
	 *
	 * @param string $plain_text Plain String.
	 * @return string Encrypted String
	 */
	public function encrypt( $plain_text ) {
		$key = $this->config->working_key;

		$key            = hex2bin( md5( $key ) );
		$init_vector    = pack( 'C*', 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f );
		$open_mode      = openssl_encrypt( $plain_text, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $init_vector );
		$encrypted_text = bin2hex( $open_mode );
		return $encrypted_text;
	}
	
	/**
	 * Decrypt the Encrypted String.
	 *
	 * @param string $encrypted_text Encrypted String.
	 * @return string Plain String
	 */
	public function decrypt( $encrypted_text ) {
		$key = $this->config->working_key;

		$encrypted_text = trim( $encrypted_text );

		$key            = hex2bin( md5( $key ) );
		$init_vector    = pack( 'C*', 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f );
		$encrypted_text = hex2bin( $encrypted_text );
		$decrypted_text = openssl_decrypt( $encrypted_text, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $init_vector );
		return $decrypted_text;
	}
}
