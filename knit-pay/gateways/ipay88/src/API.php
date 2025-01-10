<?php

namespace KnitPay\Gateways\IPay88;

use Exception;

/**
 * Title: iPay88 API
 * Copyright: 2020-2025 Knit Pay
 *
 * @author Knit Pay
 * @version 8.96.0.0
 * @since 8.96.0.0
 */
class API {
	private $config;
	private $endpoint_url;
	private $api_endpoint_url;

	public function __construct( Config $config, $mode ) {
		$this->config = $config;

		$endpoint_urls = [
			'my' => [
				'trans' => [
					Gateway::MODE_TEST => 'https://payment.ipay88.com.my/epayment/entry.asp',
					Gateway::MODE_LIVE => 'https://payment.ipay88.com.my/epayment/entry.asp',
				],
				'api'   => [
					Gateway::MODE_TEST => 'https://payment.ipay88.com.my/epayment/webservice/TxInquiryCardDetails/TxDetailsInquiry.asmx/TxDetailsInquiryCardInfo',
					Gateway::MODE_LIVE => 'https://payment.ipay88.com.my/epayment/webservice/TxInquiryCardDetails/TxDetailsInquiry.asmx/TxDetailsInquiryCardInfo',
				],
			],
		];

		$this->endpoint_url     = $endpoint_urls[ $config->country ]['trans'][ $mode ]; // Endpoint for transaction.
		$this->api_endpoint_url = $endpoint_urls[ $config->country ]['api'][ $mode ];
	}

	public function get_endpoint_url() {
		return $this->endpoint_url;
	}

	public function get_transaction_details( $transaction_data ) {
		$transaction_data['MerchantCode'] = $this->config->merchant_code;
		$transaction_data['Version']      = 4;

		$response = wp_remote_post(
			$this->api_endpoint_url,
			[
				'body' => $transaction_data,
			]
		);
		$result   = wp_remote_retrieve_body( $response );

		$xml_object  = simplexml_load_string( $result );
		$result_json = wp_json_encode( $xml_object );
		$result_data = json_decode( $result_json, true );

		if ( isset( $result_data['MerchantCode'] ) && $result_data['MerchantCode'] === $this->config->merchant_code ) {
			return $result_data;
		} elseif ( isset( $result_data['Errdesc'] ) ) {
			throw new Exception( $result_data['Errdesc'] );
		}

		throw new Exception( 'Something went wrong. Please try again later.' );
	}

	/**
	 * Compute the HMAC-SHA512 hash Signature
	 *
	 * @param string $string String.
	 * @return string Hash
	 */
	public function get_signature( $array, $amount_index = 1 ) {
		// remove comma and dot from amount.
		$array[ $amount_index ] = str_replace( [ ',', '.' ], '', $array[ $amount_index ] );

		$array = [
			$this->config->merchant_key,
			$this->config->merchant_code,
			...$array,
		];

		$string_for_hash = implode( '', $array );

		$hash = hash_hmac( 'sha512', $string_for_hash, $this->config->merchant_key );
		return $hash;
	}
}
