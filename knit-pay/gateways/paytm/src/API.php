<?php

namespace KnitPay\Gateways\Paytm;

use Exception;
use paytm\paytmchecksum\PaytmChecksum;

class API {

	const CONNECTION_TIMEOUT = 30;

	private $merchant_key;

	private $test_mode;

	public function __construct( $merchant_key, $test_mode ) {
		$this->merchant_key = $merchant_key;
		$this->test_mode    = $test_mode;
	}

	public function get_endpoint() {
		if ( $this->test_mode ) {
			return 'https://securegw-stage.paytm.in/';
		}
		return 'https://securegw.paytm.in/';
	}

	public function initiate_transaction( $data ) {
		$merchant_id = $data['body']['mid'];
		$order_id    = $data['body']['orderId'];

		$data = wp_json_encode( $data );

		$endpoint = $this->get_endpoint() . 'theia/api/v1/initiateTransaction';
		$endpoint = add_query_arg(
			[
				'mid'     => $merchant_id,
				'orderId' => $order_id,
			],
			$endpoint
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

		$result = json_decode( $result );

		if ( isset( $result->body->resultInfo->resultStatus ) && 'F' === $result->body->resultInfo->resultStatus ) {
			throw new Exception( $result->body->resultInfo->resultMsg );
		}

		if ( ! isset( $result->head->signature ) ) {
			throw new Exception( 'Something went wrong. Please try again later.' );
		}

		if ( ! PaytmChecksum::verifySignature( wp_json_encode( $result->body ), $this->merchant_key, $result->head->signature ) ) {
			throw new Exception( 'Signature Verification Failed.' );
		}

		return $result->body->txnToken;
	}

	public function get_transaction_status( $data ) {
		$data = wp_json_encode( $data );

		$endpoint = $this->get_endpoint() . 'v3/order/status';

		$response = wp_remote_post(
			$endpoint,
			[
				'body'    => $data,
				'headers' => $this->get_request_headers(),
				'timeout' => self::CONNECTION_TIMEOUT,
			]
		);
		$result   = wp_remote_retrieve_body( $response );

		$result = json_decode( $result );

		if ( ! isset( $result->head->signature ) ) {
			throw new Exception( 'Something went wrong. Please try again later.' );
		}

		if ( ! PaytmChecksum::verifySignature( wp_json_encode( $result->body ), $this->merchant_key, $result->head->signature ) ) {
			throw new Exception( 'Signature Verification Failed. Response: ' . wp_json_encode( $result ) );
		}

		return $result->body;
	}

	private function get_request_headers() {
		return [
			'Content-Type' => 'application/json',
		];
	}
}
