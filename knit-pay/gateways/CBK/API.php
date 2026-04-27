<?php

namespace KnitPay\Gateways\CBK;

use Exception;

class API {
	private $config;
	private $mode;

	const CONNECTION_TIMEOUT = 10;

	public function __construct( Config $config ) {
		$this->config = $config;
		$this->mode   = $config->mode;
	}

	public static function get_endpoint( $test_mode ) {
		$endpoint_urls = [
			Gateway::MODE_TEST => 'https://pgtest.cbk.com',
			Gateway::MODE_LIVE => 'https://pg.cbk.com',
		];
		return $endpoint_urls[ $test_mode ];
	}
	
	public function get_access_token() {
		$keys = wp_json_encode(
			[
				'ClientId'     => $this->config->client_id,
				'ClientSecret' => $this->config->client_secret,
				'ENCRP_KEY'    => $this->config->encrypt_key,
			]
		);    
		
		$response = wp_remote_post(
			self::get_endpoint( $this->mode ) . '/ePay/api/cbk/online/pg/merchant/Authenticate',
			[
				'body'    => $keys,
				'headers' => $this->get_request_headers(),
				'timeout' => self::CONNECTION_TIMEOUT,
			]
		);
		$result   = wp_remote_retrieve_body( $response );

		$result = json_decode( $result, true );
		if ( isset( $result['Status'] ) && '1' === $result['Status'] ) {
			return $result['AccessToken'];
		}
		throw new Exception( 'Something went wrong. Please try again later.' );
	}

	public function get_payment_details( $id ) {
		$endpoint = self::get_endpoint( $this->mode ) . '/ePay/api/cbk/online/pg/Verify';

		$data = wp_json_encode(
			[
				'encrypmerch' => $this->config->encrypt_key,
				'authkey'     => $this->get_access_token(),
				'payid'       => $id,
			]
		);
		
		$response = wp_remote_post(
			$endpoint,
			[
				'body'    => $data,
				'headers' => $this->get_request_headers(),
				'timeout' => self::CONNECTION_TIMEOUT,
			]
		);
		$result   = wp_remote_retrieve_body( $response );

		$result = json_decode( $result, true );

		if ( isset( $result['Status'] ) && ( '0' === $result['Status'] || '-1' === $result['Status'] ) ) {
			$message = isset( $result['Message'] ) ? $result['Message'] : '';
			if ( is_array( $message ) || is_object( $message ) ) {
				$message = wp_json_encode( $message );
			}
			throw new Exception( esc_html( sprintf( 'Unable to Fetch Payment Details: %s Server Response: %s', $id, $message ) ) );
		}

		return $result;
	}

	private function get_request_headers() {
		return [
			'Authorization' => 'Basic ' . base64_encode( $this->config->client_id . ':' . $this->config->client_secret ),
			'Content-Type'  => 'application/json',
			'cache-control' => 'no-cache',
		];
	}
}
