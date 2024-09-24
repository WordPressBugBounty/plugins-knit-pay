<?php

namespace KnitPay\Gateways\SSLCommerz;

use Exception;

/**
 * Title: SSLCommerz API
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 8.80.0.0
 * @since 8.80.0.0
 */
class API {
	private $config;
	private $api_endpoint;

	const CONNECTION_TIMEOUT = 20;

	public function __construct( $config, $test_mode ) {
		$this->config = $config;

		$this->set_endpoint( $test_mode );
	}

	private function set_endpoint( $test_mode ) {
		if ( $test_mode ) {
			$this->api_endpoint = 'https://sandbox.sslcommerz.com/';
			return;
		}
		$this->api_endpoint = 'https://securepay.sslcommerz.com/';
	}

	public function create_session( $data ) {
		$endpoint = $this->api_endpoint . 'gwprocess/v4/api.php';

		$response = wp_remote_post(
			$endpoint,
			[
				'body'    => $this->add_store_keys( $data ),
				'timeout' => self::CONNECTION_TIMEOUT,
			]
		);
		$result   = wp_remote_retrieve_body( $response );

		$result = json_decode( $result );
		if ( isset( $result->status ) && 'FAILED' === $result->status ) {
			throw new Exception( $result->failedreason );
		}

		if ( ! empty( $result->GatewayPageURL ) ) {
			return $result->GatewayPageURL;
		}

		throw new Exception( 'Something went wrong. Please try again later.' );
	}

	public function get_transaction_status( $tran_id ) {
		$endpoint = $this->api_endpoint . 'validator/api/merchantTransIDvalidationAPI.php';

		$data['tran_id'] = $tran_id;

		$response = wp_remote_get(
			$endpoint,
			[
				'body'    => $this->add_store_keys( $data ),
				'timeout' => self::CONNECTION_TIMEOUT,
			]
		);
		$result   = wp_remote_retrieve_body( $response );

		$result = json_decode( $result );

		if ( isset( $result->APIConnect ) && 'DONE' !== $result->APIConnect ) {
			throw new Exception( 'Something went wrong. Please try again later.' );
		}

		if ( 0 === $result->no_of_trans_found ) {
			throw new Exception( 'Transaction not found.' );
		}
		$element = end( $result->element );
		return $element;
	}

	private function add_store_keys( $data ) {
		$data['store_id']     = $this->config->store_id;
		$data['store_passwd'] = $this->config->store_password;
		return $data;
	}
}
