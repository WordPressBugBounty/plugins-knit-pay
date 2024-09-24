<?php
namespace KnitPay\Gateways\PhonePe;

use Exception;

class API {


	const CONNECTION_TIMEOUT = 10;

	private $api_endpoint;

	private $merchant_id;

	private $salt_key;

	private $salt_index;

	public function __construct( $merchant_id, $salt_key, $salt_index, $test_mode ) {
		$this->merchant_id = $merchant_id;
		$this->salt_key    = $salt_key;
		$this->salt_index  = $salt_index;

		$this->set_endpoint( $test_mode );
	}

	private function set_endpoint( $test_mode ) {
		if ( $test_mode ) {
			$this->api_endpoint = 'https://api-preprod.phonepe.com/apis/hermes';
			return;
		}
		$this->api_endpoint = 'https://api.phonepe.com/apis/hermes';
	}

	public function create_transaction_link( $json_data ) {
		$sub_url = '/pg/v1/pay';

		$encoded_data = base64_encode( $json_data );
		$response     = wp_remote_post(
			$this->api_endpoint . $sub_url,
			[
				'headers' => [
					'Content-Type' => 'application/json',
					'X-VERIFY'     => $this->get_x_verify( $encoded_data, $sub_url ),
				],
				'body'    => wp_json_encode(
					[
						'request' => $encoded_data,
					]
				),
				'timeout' => self::CONNECTION_TIMEOUT,
			]
		);

		$result = wp_remote_retrieve_body( $response );

		$result = json_decode( $result );

		if ( ! $result->success ) {
			throw new Exception( isset( $result->message ) ? $result->message : 'Error Code: ' . $result->code );
		}

		return $result->data->instrumentResponse->redirectInfo->url;
	}

	public function get_payment_status( $id ) {
		$sub_url = "/pg/v1/status/{$this->merchant_id}/{$id}";

		$endpoint = $this->api_endpoint . $sub_url;

		$response = wp_remote_get(
			$endpoint,
			[
				'headers' => [
					'Content-Type'  => 'application/json',
					'X-VERIFY'      => $this->get_x_verify( '', $sub_url ),
					'X-MERCHANT-ID' => $this->merchant_id,
				],
				'timeout' => self::CONNECTION_TIMEOUT,
			]
		);

		$result = wp_remote_retrieve_body( $response );

		$result = json_decode( $result );

		return $result;
	}

	private function get_x_verify( $params, $url ) {
		$phonepeString = $params . $url . $this->salt_key;

		$hashString = hash( 'sha256', $phonepeString );

		$hashedString = $hashString . '###' . $this->salt_index;
		return $hashedString;
	}
}

