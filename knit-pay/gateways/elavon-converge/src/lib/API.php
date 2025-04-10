<?php

namespace KnitPay\Gateways\ElavonConverge;

use Exception;

class API {
	private $merchant_id;
	private $user_id;
	private $terminal_pin;

	public $api_endpoint;

	public $xml_api_url;

	public function __construct( $config, $test_mode ) {
		$this->merchant_id = $config->merchant_id;

		$this->user_id = $config->user_id;

		$this->terminal_pin = $config->terminal_pin;

		$this->get_endpoint( $test_mode );
	}

	private function get_endpoint( $test_mode ) {
		if ( $test_mode ) {
			$this->api_endpoint = 'https://api.demo.convergepay.com/hosted-payments';
			$this->xml_api_url  = 'https://api.demo.convergepay.com/VirtualMerchantDemo/processxml.do';
			return;
		}
		$this->api_endpoint = 'https://api.convergepay.com/hosted-payments';
		$this->xml_api_url  = 'https://api.convergepay.com/VirtualMerchant/processxml.do';
	}

	public function get_session_token( $data ) {
		$data['ssl_merchant_id']      = $this->merchant_id;
		$data['ssl_user_id']          = $this->user_id;
		$data['ssl_pin']              = $this->terminal_pin;
		$data['ssl_transaction_type'] = 'ccsale';

		$endpoint = $this->api_endpoint . '/transaction_token';

		$response = wp_remote_post(
			$endpoint,
			[
				'body' => $data,
			]
		);

		$result = wp_remote_retrieve_body( $response );

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			throw new Exception( trim( $result ) );
		}

		return urlencode( $result );
	}

	public function get_transaction_details( $transaction_id ) {

		$data['ssl_merchant_id']      = $this->merchant_id;
		$data['ssl_user_id']          = $this->user_id;
		$data['ssl_pin']              = $this->terminal_pin;
		$data['ssl_transaction_type'] = 'txnquery';
		$data['ssl_txn_id']           = $transaction_id;
		$data                         = array_flip( $data );

		$xml = new \SimpleXMLElement( '<txn/>' );
		array_walk_recursive( $data, [ $xml, 'addChild' ] );

		$t_xml = new \DOMDocument();
		$t_xml->loadXML( $xml->asXML() );
		$xml_out = $t_xml->saveXML( $t_xml->documentElement );

		$url = $this->xml_api_url . '?xmldata=' . $xml_out;

		$response = wp_remote_post(
			$url,
			[
				'timeout' => 30,
			]
		);

		$result = wp_remote_retrieve_body( $response );

		$xml_object  = simplexml_load_string( $result );
		$result_json = wp_json_encode( $xml_object );
		$result_data = json_decode( $result_json, true );

		if ( isset( $result_data['ssl_trans_status'] ) ) {
			return $result_data;
		}

		throw new Exception( 'Something went wrong. Please try again later.' );
	}
}
