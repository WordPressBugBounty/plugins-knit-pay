<?php

namespace KnitPay;

use Pronamic\WordPress\Http\Facades\Http;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class PaymentRestController extends WP_REST_Controller {
	protected $rest_base = 'knit-pay';

	// Here initialize our namespace and resource name.
	public function __construct() {
		$this->namespace     = $this->rest_base . '/v1';
		$this->resource_name = 'payments';
		$this->post_type     = 'pronamic_payment';
	}

	// Register our routes.
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->resource_name,
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_item' ],
					'permission_callback' => [ $this, 'create_item_permissions_check' ],
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
				],
				// Register our schema callback.
				'schema' => [ $this, 'get_item_schema' ],
			] 
		);
		
		register_rest_route(
			$this->namespace,
			'/' . $this->resource_name . '/(?P<id>[\d]+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_item' ],
					'permission_callback' => [ $this, 'get_item_permissions_check' ],
					'args'                => [
						'context' => [
							'default' => 'view',
						],
					],
				],
				// Register our schema callback.
				'schema' => [ $this, 'get_item_schema' ],
			] 
		);
	}

	/**
	 * Check permissions for reading a payment.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function get_item_permissions_check( $request ) {
		$post_type = get_post_type_object( $this->post_type );

		if ( ! current_user_can( $post_type->cap->edit_posts ) ) {
			return new WP_Error(
				'rest_cannot_read',
				__( 'Sorry, you are not allowed to read payments as this user.', 'knit-pay-lang' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return true;
	}

	/**
	 * Gets post data of requested post id and outputs it as a rest response.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function get_item( $request ) {
		$payment_id = (int) $request['id'];

		$result = PaymentApiHelper::get_payment( $payment_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}
	
	/**
	 * Create one item from the collection.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function create_item( $request ) {
		if ( ! empty( $request['id'] ) ) {
			return new WP_Error(
				'rest_payment_exists',
				__( 'Cannot create existing payment.', 'knit-pay-lang' ),
				[ 'status' => 400 ]
			);
		}

		$params = $request->get_params();
		$result = PaymentApiHelper::create_payment( $params );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$response = rest_ensure_response( $result );
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( $this->namespace . '/payments/' . $result['id'] ) );

		return $response;
	}

	/**
	 * Get the full item schema (writable input fields + read-only fields).
	 *
	 * Merges get_input_schema() (no readonly markers) with get_readonly_schema()
	 * (only truly read-only fields, no overlap with input). Because the two
	 * schemas are disjoint, array_merge() is safe: no writable field will
	 * accidentally gain a readonly marker, and get_endpoint_args_for_item_schema()
	 * will correctly generate args for all writable fields.
	 *
	 * @return array The JSON Schema for a payment.
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->schema;
		}

		$all_properties = array_merge(
			PaymentApiHelper::get_input_schema()['properties'],
			PaymentApiHelper::get_readonly_schema()['properties']
		);

		$this->schema = [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'payment',
			'type'       => 'object',
			'properties' => $all_properties,
		];

		return $this->schema;
	}
	
	/**
	 * Check if a given request has access to create items.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function create_item_permissions_check( $request ) {
		// Check for internal API call nonce.
		$internal_nonce = $request->get_header( 'X-KnitPay-Internal-Nonce' );
		if ( $internal_nonce && wp_verify_nonce( $internal_nonce, 'knit_pay_internal_api' ) ) {
			return true;
		}

		if ( ! empty( $request['id'] ) ) {
			return new WP_Error(
				'rest_payment_exists',
				__( 'Cannot create existing payment.', 'knit-pay-lang' ),
				[ 'status' => 400 ]
			);
		}

		$post_type = get_post_type_object( $this->post_type );

		if ( ! current_user_can( $post_type->cap->edit_posts ) ) {
			return new WP_Error(
				'rest_cannot_create',
				__( 'Sorry, you are not allowed to create payments as this user.', 'knit-pay-lang' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return true;
	}
}

// Payment redirect URL.
add_filter(
	'pronamic_payment_redirect_url',
	function ( $url, $payment ) {
		// Set Redirect URL if defined in REST API.
		if ( $payment->get_meta( 'rest_redirect_url' ) ) {
			$redirect_url = add_query_arg(
				[
					'kp_payment_id' => $payment->get_id(),
				],
				$payment->get_meta( 'rest_redirect_url' )
			);

			return $redirect_url;
		}

		return $url;
	},
	10,
	2
);

add_action(
	'pronamic_payment_status_update',
	function ( $payment, $can_redirect, $old_status, $new_status ) {
		// Trigger webhook.
		if ( $payment->get_meta( 'rest_notify_url' ) ) {
			$notify_url = $payment->get_meta( 'rest_notify_url' );

			$payment_object = $payment->get_json();
			unset( $payment_object->status );
			$response = Http::post(
				$notify_url,
				[
					'body' => wp_json_encode( $payment_object ),
				]
			);
		}
	},
	10,
	4
);

// Payment Rest API.
add_action(
	'rest_api_init',
	function () {
		// @link https://developer.wordpress.org/rest-api/extending-the-rest-api/controller-classes/#controllers
		$payment_rest_controller = new PaymentRestController();
		$payment_rest_controller->register_routes();
	}
);
