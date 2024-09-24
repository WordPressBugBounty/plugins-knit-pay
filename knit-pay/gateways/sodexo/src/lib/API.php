<?php

namespace KnitPay\Gateways\Sodexo;

use Exception;

class API {


	private $api_endpoint;

	private $api_keys;

	public function __construct( $api_keys, $test_mode ) {
		$this->api_keys = $api_keys;

		$this->get_endpoint( $test_mode );
	}

	private function get_endpoint( $test_mode ) {
		if ( $test_mode ) {
			$this->api_endpoint = 'https://pay-gw.preprod.zeta.in';
			return;
		}
		$this->api_endpoint = 'https://pay.gw.zetapay.in';
	}

	public function create_transaction( $data ) {

		$endpoint = $this->api_endpoint . '/v1.0/sodexo/transactions';

		$response = wp_remote_post(
			$endpoint,
			[
				'body'    => $data,
				'headers' => $this->get_request_headers(),

			]
		);
		$result = wp_remote_retrieve_body( $response );

		$result = json_decode( $result );
		if ( isset( $result->redirectUserTo ) ) {
			return $result;
		}

		if ( isset( $result->errorCode ) ) {
			throw new Exception( trim( $result->errorMessage ) );
		}

		throw new Exception( 'Something went wrong. Please try again later.' );
	}

	public function get_transaction_details( $transaction_id ) {

		$endpoint = $this->api_endpoint . '/v1.0/sodexo/transactions/' . $transaction_id;

		$response = wp_remote_get(
			$endpoint,
			[
				'headers' => $this->get_request_headers(),
			]
		);
		$result   = wp_remote_retrieve_body( $response );

		$result = json_decode( $result );

		if ( isset( $result->errorCode ) ) {
			throw new Exception( trim( $result->additionalInfo ) );
		}

		if ( isset( $result->transactionId ) ) {
			return $result;
		}

		throw new Exception( 'Something went wrong. Please try again later.' );
	}

	public function refund_transaction( $data ) {
		$endpoint = $this->api_endpoint . '/v2.0/sodexo/transactions/refund';

		$response = wp_remote_post(
			$endpoint,
			[
				'body'    => $data,
				'headers' => $this->get_request_headers(),
			]
		);
		$result   = wp_remote_retrieve_body( $response );

		$result = json_decode( $result );

		if ( isset( $result->errorCode ) ) {
			throw new Exception( trim( $result->errorMessage ) );
		}

		if ( isset( $result->refundTransactionId ) ) {
			return $result->refundTransactionId;
		}

		throw new Exception( 'Something went wrong. Please try again later.' );
	}

	private function get_request_headers() {
		return [
			'apiKey'       => $this->api_keys,
			'Content-Type' => 'application/json',
		];
	}
}
