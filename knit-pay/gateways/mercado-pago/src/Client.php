<?php

namespace KnitPay\Gateways\MercadoPago;

use Exception;

/**
 * Title: Mercado Pago API
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 8.88.0.0
 * @since 8.88.0.0
 */
class Client {
	private $access_token;

	public function __construct( Config $config ) {
		$this->access_token = $config->access_token;
		
		$this->endpoint_url = 'https://api.mercadopago.com';
	}

	private function get_endpoint_url() {
		return $this->endpoint_url;
	}
	
	public function create_preference( $data ) {
		$response = wp_remote_post(
			$this->get_endpoint_url() . '/checkout/preferences',
			[
				'body'    => wp_json_encode( $data ),
				'headers' => $this->get_request_headers(),
			]
		);
		$result   = wp_remote_retrieve_body( $response );
		$result   = json_decode( $result );
		if ( isset( $result->message ) ) {
			throw new Exception( $result->message );
		}
		return $result;
	}
	
	public function search_payments( $external_reference ) {
		$api_url = add_query_arg(
			[
				'sort'               => 'date_created',
				'criteria'           => 'desc',
				'external_reference' => $external_reference,
			],
			$this->get_endpoint_url() . '/v1/payments/search'
		);
		
		$response = wp_remote_get(
			$api_url,
			[
				'headers' => $this->get_request_headers(),
			]
		);
		$result   = wp_remote_retrieve_body( $response );
		$result   = json_decode( $result );
		if ( 0 === $result->paging->total ) {
			throw new Exception( $result->message );
		}
		return $result->results;
	}
	
	private function get_request_headers() {
		// TODO https://www.mercadopago.com.ar/developers/en/docs/checkout-pro/additional-content/integration-metrics
		return [
			'Authorization' => 'Bearer ' . $this->access_token,
		];
	}
}
