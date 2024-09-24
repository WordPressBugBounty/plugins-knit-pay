<?php
namespace KnitPay\Gateways\SBIePay;

use WP_REST_Request;
use WP_Query;

/**
 * Title: SBIePay Webhook Controller
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 8.85.9.0
 * @since 8.85.9.0
 */
class WebhookController {
	/**
	 * SBIePay integration object.
	 *
	 * @var Integration
	 */
	private $integration;

	/**
	 * Construct notifications controller.
	 *
	 * @param Integration $integration
	 *            Integration.
	 */
	public function __construct( Integration $integration ) {
		$this->integration = $integration;
	}

	/**
	 * Setup.
	 *
	 * @return void
	 */
	public function setup() {
		\add_action(
			'rest_api_init',
			[
				$this,
				'rest_api_init',
			]
		);
	}

	/**
	 * REST API init.
	 *
	 * @return void
	 */
	public function rest_api_init() {
		\register_rest_route(
			Integration::REST_ROUTE_NAMESPACE,
			'/push-response-listener/(?P<hash>\w+)',
			[
				'args'                => [
					'pushRespData' => [
						'description' => \__( 'Encrypted Data', 'knit-pay-lang' ),
						'type'        => 'string',
					],
					'Bank_Code'    => [
						'description' => \__( 'Bank Code', 'knit-pay-lang' ),
						'type'        => 'string',
					],
					'merchIdVal'   => [
						'description' => \__( 'Merchant ID', 'knit-pay-lang' ),
						'type'        => 'string',
					],
				],
				'methods'             => 'POST',
				'callback'            => [
					$this,
					'rest_api_sbiepay_push_response_listener',
				],
				'permission_callback' => [
					$this,
					'rest_api_sbiepay_push_response_listener_permission',
				],
			]
		);
	}

	/**
	 * REST API SBIePay push response Listener handler.
	 *
	 * @param WP_REST_Request $request
	 *            Request.
	 * @return object
	 */
	public function rest_api_sbiepay_push_response_listener( WP_REST_Request $request ) {
		$merchant_id = $request->get_param( 'merchIdVal' );

		if ( empty( $merchant_id ) ) {
			return new \WP_Error(
				'rest_sbiepay_empty_merchant_id',
				\__( 'Empty `merchIdVal` SBIePay variable.', 'knit-pay-lang' ),
				[
					'status' => 200,
				]
			);
		}

		// Find Gateway Configuration for provided payment type id.
		$query = new WP_Query(
			[
				'post_type'  => 'pronamic_gateway',
				'fields'     => 'ids',
				'nopaging'   => true,
				'meta_query' => [
					[
						'key'   => '_pronamic_gateway_sbiepay_merchant_id',
						'value' => $merchant_id,
					],
				],
			]
		);
		if ( empty( $query->post_count ) ) {
			return new \WP_Error(
				'rest_sbiepay_config_not_found',
				\__( 'Gateway Configuration not found for provided merchant id.', 'knit-pay-lang' ),
				[
					'status' => 200,
				]
			);
		}
		if ( 1 < $query->post_count ) {
			return new \WP_Error(
				'rest_sbiepay_config_not_found',
				\__( 'More than 1 Gateway Configurations found for provided merchant id.', 'knit-pay-lang' ),
				[
					'status' => 200,
				]
			);
		}

		$config_id = reset( $query->posts );

		$config = $this->integration->get_config( $config_id );

		$encrypted_response_data = $request->get_param( 'pushRespData' );
		$response_data           = AES128::decrypt( $encrypted_response_data, $config->encryption_key );

		// Convert String to Array.
		$response_data_array = explode( '|', $response_data );

		$payment = \get_pronamic_payment_by_transaction_id( $response_data_array[0] );
		if ( null === $payment ) {
			return new \WP_Error(
				'rest_sbiepay_payment_not_found',
				\__( 'No payment found.', 'knit-pay-lang' ),
				[
					'status' => 200,
				]
			);
		}

		// Add note.
		$note = \sprintf( '%s<pre>%s</pre>', \__( 'Received SBIePay Push Response:', 'knit-pay-lang' ), $response_data );

		$payment->add_note( $note );

		$payment->get_gateway()->update_payment( $payment, $response_data_array );

		$payment->save();

		exit();
	}

	/**
	 * REST API SBIePay push response listener permission handler.
	 *
	 * @param WP_REST_Request $request
	 *            Request.
	 * @return bool
	 */
	public function rest_api_sbiepay_push_response_listener_permission( WP_REST_Request $request ) {
		$hash = $request->get_param( 'hash' );

		if ( empty( $hash ) ) {
			return false;
		}

		return \wp_hash( home_url( '/' ) ) === $hash;
	}
}
