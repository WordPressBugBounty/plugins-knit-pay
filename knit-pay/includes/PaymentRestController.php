<?php

namespace Pronamic\WordPress\Pay\Payments;

use Pronamic\WordPress\Pay\MoneyJsonTransformer;
use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Core\PaymentMethods;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use Pronamic\WordPress\Http\Facades\Http;

class PaymentRestController extends WP_REST_Controller {
    protected $rest_base = 'knit-pay';

	// Here initialize our namespace and resource name.
	public function __construct() {
		$this->namespace     = '/' . $this->rest_base . '/v1';
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
	 * Check permissions for the posts.
	 *
	 * @param WP_REST_Request $request Current request.
	 */
	public function get_item_permissions_check( $request ) {
	    $post_type = get_post_type_object( $this->post_type );

		if ( ! current_user_can( $post_type->cap->edit_posts ) ) {
			return new WP_Error(
				'rest_cannot_read',
				__( 'Sorry, you are not allowed to read payments as this user.' ),
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
		
		$payment = \get_pronamic_payment( $payment_id );
		
		if ( null === $payment ) {
			return new WP_Error(
				'rest_payment_not_found',
				\sprintf(
					/* translators: %s: payment ID */
					\__( 'Could not find payment with ID `%s`.', 'pronamic_ideal' ),
					$payment_id
				),
				$payment_id
			);
		}
		
		return $this->prepare_item_for_response( $payment, $request );
	}
	
	/**
	 * Create one item from the collection
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function create_item( $request ) {
		if ( ! empty( $request['id'] ) ) {
			return new WP_Error(
				'rest_payment_exists',
				__( 'Cannot create existing payment.' ),
				[ 'status' => 400 ]
			);
		}
		
		try {
			$json_param = $request->get_params();
			$req_object = json_decode( wp_json_encode( $json_param ) );
			
			$payment = new Payment();
			
			PaymentInfoHelper::from_json( $req_object, $payment );

			$payment->title = property_exists( $req_object, 'title' ) ? $req_object->title : null;
 
			// Amount.
			$payment->set_total_amount( MoneyJsonTransformer::from_json( $req_object->total_amount ) );
			
			// Configuration.
			$payment->config_id = property_exists( $req_object, 'config_id' ) ? $req_object->config_id : null;
		
			$payment = Plugin::start_payment( $payment );

			$payment->set_meta( 'rest_redirect_url', $request->get_param( 'redirect_url' ) );
			$payment->set_meta( 'rest_notify_url', $request->get_param( 'notify_url' ) );
			$payment->save();

			$response = $this->prepare_item_for_response( $payment, $request );
			$response = rest_ensure_response( $response );

			$response->set_status( 201 );
			$response->header( 'Location', rest_url( $this->namespace . '/payments/' . $payment->get_id() ) );

			return $response;
		} catch ( \Exception $e ) {
			return new WP_Error( 'rest_cannot_create', $e->getMessage(), [ 'status' => 500 ] );
		}
	}

	/**
	 * Matches the payment data to the schema we want.
	 *
	 * @param Payment $payment Payment object.
	 */
	public function prepare_item_for_response( $payment, $request ) {
		$post_data = [];

		$payment_json = $payment->get_json();

		$fields = $this->get_fields_for_response( $request );

		foreach ( $fields as  $field ) {
			if ( rest_is_field_included( $field, $fields ) && property_exists( $payment_json, $field ) ) {
				$post_data[ $field ] = $payment_json->$field;
			}
		}

		if ( rest_is_field_included( 'pay_redirect_url', $fields ) ) {
			$post_data['pay_redirect_url'] = $payment->get_pay_redirect_url();
		}

		return rest_ensure_response( $post_data );
	}

	/**
	 * Get our sample schema for a post.
	 *
	 * @return array The sample schema for a post
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			// Since WordPress 5.3, the schema can be cached in the $schema property.
			return $this->schema;
		}

		$this->schema = [
			// This tells the spec of JSON Schema we are using which is draft 4.
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			// The title property marks the identity of the resource.
			'title'      => 'post',
			'type'       => 'object',
			// In JSON Schema you can specify object properties in the properties attribute.
			'properties' => [
				'id'               => [
					'type'     => 'integer',
					'readonly' => true,
				],
			    'config_id' => [
			        'type' => 'integer',
			    ],
				'total_amount'     => [
					'type'       => 'object',
					'required'   => true,
					'properties' => [
						'value'    => [
							'description'      => esc_html__( 'Amount Value', 'knit-pay-lang' ),
							'type'             => 'number',
							'minimum'          => 0,
							'exclusiveMinimum' => true,
							'required'         => true,
						],
						'currency' => [
							'description' => esc_html__( 'Currency', 'knit-pay-lang' ),
							'type'        => 'string',
							'minLength'   => 3,
							'maxLength'   => 3,
							'pattern'     => '[A-Z]{3}',
							'required'    => true,
						],
					],
				],
				
				'payment_method'   => [
					'type' => 'string',
					'enum' => PaymentMethods::get_active_payment_methods(),
				],
				'source'           => [
					'type' => 'object',
				],
				'order_id'         => [
					'type' => 'string',
				],
				'description'      => [
					'type' => 'string',
				],
				'meta'             => [
					'type'       => 'object',
					'properties' => [
						'redirect_url' => [
							'description' => esc_html__( 'URL where buyer gets redirected after payment attempt.', 'knit-pay-lang' ),
							'type'        => 'string',
							'format'      => 'uri',
							'required'    => true,
						],
					],
				],
				'action_url'       => [
					'type'     => 'string',
					'format'   => 'uri',
					'readonly' => true,
					'context'  => [ 'view' ],
				],
				'pay_redirect_url' => [
					'type'     => 'string',
					'format'   => 'uri',
					'readonly' => true,
					'context'  => [ 'view' ],
				],
				'status'           => [
					'type'     => 'string',
					'readonly' => true,
				],
				'customer'         => [
					'type' => 'object',
				],
			    'billing_address' => [
			        'type' => 'object'
			    ],
				'mode'             => [
					'type'     => 'string',
					'readonly' => true,
				],
				'gateway'          => [
					'type'     => 'object',
					'readonly' => true,
				],
				'transaction_id'   => [
					'type'     => 'string',
					'readonly' => true,
				],
			],
		];

		return $this->schema;
	}
	
	/**
	 * Check if a given request has access to create items
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function create_item_permissions_check( $request ) {
		if ( ! empty( $request['id'] ) ) {
			return new WP_Error(
				'rest_payment_exists',
				__( 'Cannot create existing payment.' ),
				[ 'status' => 400 ]
			);
		}

		$post_type = get_post_type_object( $this->post_type );

		if ( ! current_user_can( $post_type->cap->edit_posts ) ) {
			return new WP_Error(
				'rest_cannot_create',
				__( 'Sorry, you are not allowed to create payments as this user.' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return true;
	}
}

// Payment redirect URL.
add_filter( 'pronamic_payment_redirect_url', function ($url, $payment){
    // Set Redirect URL if defined in REST API.
    if ( $payment->get_meta( 'rest_redirect_url' ) ) {
        return $payment->get_meta( 'rest_redirect_url' );
    }
    
    return $url;
}, 10, 2 );

add_action( 'pronamic_payment_status_update', function ( $payment, $can_redirect, $old_status, $new_status ) {        
    // Trigger webhook.
    if ( $payment->get_meta( 'rest_notify_url' ) ) {
        $notify_url = $payment->get_meta( 'rest_notify_url' );
        $response   = Http::post(
            $notify_url,
            [
                'body' => wp_json_encode( $payment->get_json() ),
            ]
        );
    }
}, 10, 4 );

// Payment Rest API.
add_action( 'rest_api_init', function () {
    // @link https://developer.wordpress.org/rest-api/extending-the-rest-api/controller-classes/#controllers
    $payment_rest_controller = new PaymentRestController();
    $payment_rest_controller->register_routes();
});
