<?php
/**
 * Instamojo
 * used to manage Instamojo API calls
 */

namespace KnitPay\Gateways\Instamojo;

use Exception;

require __DIR__ . DIRECTORY_SEPARATOR . 'ValidationException.php';

class Instamojo {

	const CONNECTION_TIMEOUT = 10;

	private $api_endpoint;

	private $auth_endpoint;

	private $auth_headers;

	private $client_id;

	private $client_secret;

	public function __construct( $client_id, $client_secret, $test_mode ) {
		$this->client_id     = $client_id;
		$this->client_secret = $client_secret;

		$this->get_endpoint( $test_mode );

		$this->get_access_token();
	}

	private function get_endpoint( $test_mode ) {
		if ( $test_mode ) {
			$this->api_endpoint  = 'https://test.instamojo.com/v2/';
			$this->auth_endpoint = 'https://test.instamojo.com/oauth2/token/';
			return;
		}
		$this->api_endpoint  = 'https://api.instamojo.com/v2/';
		$this->auth_endpoint = 'https://api.instamojo.com/oauth2/token/';
	}

	public function get_access_token() {
		$data                  = [];
		$data['client_id']     = $this->client_id;
		$data['client_secret'] = $this->client_secret;
		$data['scopes']        = 'all';
		$data['grant_type']    = 'client_credentials';

		$response = wp_remote_post(
			$this->auth_endpoint,
			[
				'body'    => $data,
				'timeout' => self::CONNECTION_TIMEOUT,
			]
		);
		$result   = wp_remote_retrieve_body( $response );
		if ( $result ) {
			$result = json_decode( $result );
			if ( isset( $result->error ) ) {
				throw new ValidationException(
					"The Authorization request failed with message '$result->error'",
					[
						'Payment Gateway Authorization Failed.',
					],
					$result
				);
			}
		}
		if ( ! isset( $result->access_token ) || '' === $result->access_token ) {
			throw new Exception( 'Something went wrong. Please try again later.' );
		}
		$this->auth_headers = "Authorization:Bearer $result->access_token";
	}

	public function create_order_payment( $data ) {
		$endpoint = $this->api_endpoint . 'gateway/orders/';
		$response = wp_remote_post(
			$endpoint,
			[
				'body'    => $data,
				'timeout' => self::CONNECTION_TIMEOUT,
				'headers' => $this->auth_headers,
			]
		);
		$result   = wp_remote_retrieve_body( $response );
		$result   = json_decode( $result );
		if ( isset( $result->order ) ) {
			return $result;
		}
			$errors = [];
		if ( isset( $result->message ) ) {
			throw new ValidationException(
				"Validation Error with message: $result->message",
				[
					$result->message,
				],
				$result
			);
		}

		foreach ( $result as $v ) {
			if ( is_array( $v ) ) {
				$errors[] = $v[0];
			}
		}
		if ( $errors ) {
			throw new ValidationException( 'Validation Error Occured with following Errors : ', $errors, $result );
		}

	}

	public function create_payment_request( $data ) {
		$endpoint = $this->api_endpoint . 'payment_requests/';
		$response = wp_remote_post(
			$endpoint,
			[
				'body'    => $data,
				'timeout' => self::CONNECTION_TIMEOUT,
				'headers' => $this->auth_headers,
			]
		);
		$result   = wp_remote_retrieve_body( $response );

		$result = json_decode( $result );
		if ( isset( $result->id ) ) {
			return $result;
		} elseif ( isset( $result ) ) {
			$errors = [];
			if ( isset( $result->message ) ) {
				throw new ValidationException(
					"Validation Error with message: $result->message",
					[
						$result->message,
					],
					$result
				);
			}

			foreach ( $result as $v ) {
				if ( is_array( $v ) && isset( $v[0] ) ) {
					$errors[] = $v[0];
				}
			}
			if ( $errors ) {
				throw new ValidationException( 'Validation Error Occured with following Errors : ', $errors, $result );
			}
		}
	}

	public function get_order_by_id( $id ) {
		$endpoint = $this->api_endpoint . "gateway/orders/id:$id/";

		$response = wp_remote_get(
			$endpoint,
			[
				'headers' => $this->auth_headers,
			]
		);
		$result   = wp_remote_retrieve_body( $response );

		$result = json_decode( $result );
		if ( isset( $result->id ) && $result->id ) {
			return $result;
		}
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		throw new Exception( "Unable to Fetch Payment Request id:'$id' Server Responds " . print_R( $result, true ) );

	}

	public function get_payment_request_by_id( $id ) {
		$endpoint = $this->api_endpoint . "payment_requests/$id/";
		$response = wp_remote_get(
			$endpoint,
			[
				'headers' => $this->auth_headers,
			]
		);
		$result   = wp_remote_retrieve_body( $response );

		$result = json_decode( $result );
		if ( isset( $result->id ) && $result->id ) {
			return $result;
		}
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		throw new Exception( "Unable to Fetch Payment Request id:'$id' Server Responds " . print_R( $result, true ) );

	}

	public function get_payment_by_id( $id ) {
		$endpoint = $this->api_endpoint . "payments/$id/";

		$response = wp_remote_get(
			$endpoint,
			[
				'headers' => $this->auth_headers,
			]
		);
		$result   = wp_remote_retrieve_body( $response );

		$result = json_decode( $result );
		if ( isset( $result->id ) && $result->id ) {
			return $result;
		}
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		throw new Exception( "Unable to Fetch Payment id:'$id' Server Responds " . print_R( $result, true ) );

	}

	public function get_payment_status( $payment_id, $payments ) {
		foreach ( $payments as $payment ) {
			if ( $payment->id === $payment_id ) {
				return $payment->status;
			}
		}
	}

	public function create_refund( $data ) {
		$endpoint = $this->api_endpoint . 'payments/' . $data['payment_id'] . '/refund/';
		$response = wp_remote_post(
			$endpoint,
			[
				'body'    => $data,
				'timeout' => self::CONNECTION_TIMEOUT,
				'headers' => $this->auth_headers,
			]
		);
		$result   = wp_remote_retrieve_body( $response );

		$result = json_decode( $result );
		if ( isset( $result->success ) && $result->success ) {
			return $result->refund;
		}
		if ( isset( $result->reason ) ) {
			throw new Exception(
				$result->reason
			);
		}
	}

	public function disable_payment_request( $payment_request_id ) {
		$endpoint = $this->api_endpoint . 'payment_requests/' . $payment_request_id . '/disable/';
		$response = wp_remote_post(
			$endpoint,
			[
				'timeout' => self::CONNECTION_TIMEOUT,
				'headers' => $this->auth_headers,
			]
		);
		$result   = wp_remote_retrieve_body( $response );

		$result = json_decode( $result );
		if ( isset( $result->success ) ) {
			return $result->success;
		}
	}
}
