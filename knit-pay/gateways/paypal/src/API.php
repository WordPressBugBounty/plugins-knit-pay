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
	const CONNECTION_TIMEOUT = 30;
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
		$url = $this->api_base_url . 'v2/checkout/orders';

		$result = $this->create_connection( $url, 'POST', $data );

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
		$url = $this->api_base_url . 'v2/checkout/orders/' . $paypal_order_id;

		$result = $this->create_connection( $url, 'GET' );

		// return result after error occurs.
		if ( isset( $result->name ) ) {
			$result->status = $result->name;
			return $result;
		}

		if ( ! isset( $result->status ) || $paypal_order_id !== $result->id ) {
			throw new Exception( 'Something went wrong. Please try again later.' );
		}

		return $result;
	}

	public function capture_payment( $paypal_order_id ) {
		$url = $this->api_base_url . 'v2/checkout/orders/' . $paypal_order_id . '/capture';

		$result = $this->create_connection( $url, 'POST' );

		if ( isset( $result->message ) || $paypal_order_id === $result->id ) {
			return $result;
		}

		throw new Exception( 'Something went wrong. Please try again later.' );
	}

	public function create_webhook( $data ) {
		$url = $this->api_base_url . 'v1/notifications/webhooks';

		$result = $this->create_connection( $url, 'POST', $data );

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
		$url = $this->api_base_url . 'v1/notifications/webhooks/' . $webhook_id;

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

		return $this->create_connection( $url, 'PATCH', $payload );
	}

	public function list_webhooks() {
		$url = $this->api_base_url . 'v1/notifications/webhooks';

		$result = $this->create_connection( $url, 'GET' );

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
		$url = $this->api_base_url . "v2/payments/captures/{$paypal_capture_id}/refund";

		$result = $this->create_connection( $url, 'POST', $data );

		if ( isset( $result->message ) ) {
			throw new Exception( trim( $result->message ) );
		}

		if ( isset( $result->id ) ) {
			return $result;
		}

		throw new Exception( 'Something went wrong. Please try again later.' );
	}

	private function create_connection( $url, $method, $data = [], $retry = 1 ) {
		$args = [
			'method'  => $method,
			'headers' => $this->get_request_headers(),
			'timeout' => self::CONNECTION_TIMEOUT,
		];

		if ( $method !== 'GET' && ! empty( $data ) ) {
			$args['body'] = wp_json_encode( $data );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			if ( 5 === $retry ) {
				throw new Exception( $response->get_error_message() );
			}
			usleep( 500000 ); // Wait for 500 ms before retrying.
			return $this->create_connection( $url, $method, $data, ++$retry );
		}

		$result = wp_remote_retrieve_body( $response );

		$result = json_decode( $result );

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 401 === $response_code ) {
			throw new Exception( esc_html( $result->error_description ) );
		}

		return $result;
	}

	private function get_request_headers() {
		return [
			'Authorization' => 'Basic ' . base64_encode( $this->config->client_id . ':' . $this->config->client_secret ),
			'Content-Type'  => 'application/json',
		];
	}
}
