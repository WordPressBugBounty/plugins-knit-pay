<?php

namespace KnitPay\Gateways\Paypal;

use Exception;

/**
 * Title: PayPal API Client
 * Copyright: 2020-2025 Knit Pay
 *
 * @author Knit Pay
 * @version 8.94.0.0
 * @since 8.94.0.0
 */
class API {
	private $config;
	private $api_base_url;

	public function __construct( $config ) {
		$this->config = $config;

		if ( 'test' === $this->config->mode ) {
			$this->api_base_url = 'https://api.sandbox.paypal.com/';
		} else {
			$this->api_base_url = 'https://api.paypal.com/';
		}
	}

	public function create_order( $data ) {
		$endpoint = $this->api_base_url . 'v2/checkout/orders';

		$response = wp_remote_post(
			$endpoint,
			[
				'body'    => wp_json_encode( $data ),
				'headers' => $this->get_request_headers(),
			]
		);
		$result   = wp_remote_retrieve_body( $response );

		$result = json_decode( $result );

		if ( isset( $result->details ) ) {
			throw new Exception( trim( $result->details[0]->description ) );
		} elseif ( isset( $result->message ) ) {
			throw new Exception( trim( $result->message ) );
		}

		if ( isset( $result->id ) ) {
			return $result;
		}

		throw new Exception( 'Something went wrong. Please try again later.' );
	}

	public function capture_payment( $paypal_order_id ) {
		$endpoint = $this->api_base_url . 'v2/checkout/orders/' . $paypal_order_id . '/capture';

		$response = wp_remote_post(
			$endpoint,
			[
				'headers' => $this->get_request_headers(),
			]
		);
		$result   = wp_remote_retrieve_body( $response );

		$result = json_decode( $result );

		if ( isset( $result->message ) || $paypal_order_id === $result->id ) {
			return $result;
		}

		throw new Exception( 'Something went wrong. Please try again later.' );
	}

	/**
	 * Get order details.
	 *
	 * @see https://developer.paypal.com/docs/api/orders/v2/#orders_get
	 *
	 * @param string $paypal_order_id PayPal Order ID.
	 *
	 * @return object
	 * @throws Exception
	 */
	public function get_order_details( $paypal_order_id ) {
		$endpoint = $this->api_base_url . 'v2/checkout/orders/' . $paypal_order_id;

		$response = wp_remote_get(
			$endpoint,
			[
				'headers' => $this->get_request_headers(),
			]
		);
		$result   = wp_remote_retrieve_body( $response );

		$result = json_decode( $result );

		// return result after error occurs.
		if ( isset( $result->name ) ) {
			$result->status = $result->name;
			return $result;
		}

		if ( ! isset( $result->status ) ) {
			throw new Exception( 'Something went wrong. Please try again later.' );
		}

		if ( $paypal_order_id === $result->id ) {
			return $result;
		}

		throw new Exception( 'Something went wrong. Please try again later.' );
	}

	public function create_webhook( $data ) {
		$endpoint = $this->api_base_url . 'v1/notifications/webhooks';

		$response = wp_remote_post(
			$endpoint,
			[
				'body'    => wp_json_encode( $data ),
				'headers' => $this->get_request_headers(),
			]
		);
		$result   = wp_remote_retrieve_body( $response );

		$result = json_decode( $result );

		if ( isset( $result->details ) ) {
			throw new Exception( trim( $result->details[0]->description ) );
		} elseif ( isset( $result->message ) ) {
			throw new Exception( trim( $result->message ) );
		}

		if ( isset( $result->id ) ) {
			return $result;
		}

		throw new Exception( 'Something went wrong. Please try again later.' );
	}

	public function update_webhook( $data, $webhook_id ) {
		$endpoint = $this->api_base_url . 'v1/notifications/webhooks/' . $webhook_id;

		$payload = [
			[
				'op'    => 'replace',
				'path'  => '/url',
				'value' => $data['url'],
			],
			[
				'op'    => 'replace',
				'path'  => '/event_types',
				'value' => $data['event_types'],
			],
		];

		$response = wp_remote_request(
			$endpoint,
			[
				'method'  => 'PATCH',
				'body'    => wp_json_encode( $payload ),
				'headers' => $this->get_request_headers(),
			]
		);
		$result   = wp_remote_retrieve_body( $response );

		$result = json_decode( $result );
	}

	public function list_webhooks() {
		$endpoint = $this->api_base_url . 'v1/notifications/webhooks';

		$response = wp_remote_get(
			$endpoint,
			[
				'headers' => $this->get_request_headers(),
			]
		);
		$result   = wp_remote_retrieve_body( $response );

		$result = json_decode( $result );

		if ( isset( $result->details ) ) {
			throw new Exception( trim( $result->details[0]->description ) );
		} elseif ( isset( $result->message ) ) {
			throw new Exception( trim( $result->message ) );
		}

		if ( isset( $result->webhooks ) ) {
			return $result->webhooks;
		}

		throw new Exception( 'Something went wrong. Please try again later.' );
	}

	/**
	 * Refund payment.
	 *
	 * @see: https://developer.paypal.com/docs/api/payments/v2/#captures_refund
	 *
	 * @param string $paypal_capture_id PayPal Capture ID.
	 * @param array  $data Refund data.
	 *
	 * @return object
	 * @throws Exception
	 */
	public function refund_payment( $paypal_capture_id, $data ) {
		$endpoint = $this->api_base_url . "v2/payments/captures/{$paypal_capture_id}/refund";

		$response = wp_remote_post(
			$endpoint,
			[
				'body'    => wp_json_encode( $data ),
				'headers' => $this->get_request_headers(),
			]
		);
		$result   = wp_remote_retrieve_body( $response );

		$result = json_decode( $result );

		if ( isset( $result->message ) ) {
			throw new Exception( trim( $result->message ) );
		}

		if ( isset( $result->id ) ) {
			return $result;
		}

		throw new Exception( 'Something went wrong. Please try again later.' );
	}

	private function get_request_headers() {
		return [
			'Authorization' => 'Basic ' . base64_encode( $this->config->client_id . ':' . $this->config->client_secret ),
			'Content-Type'  => 'application/json',
		];
	}
}
