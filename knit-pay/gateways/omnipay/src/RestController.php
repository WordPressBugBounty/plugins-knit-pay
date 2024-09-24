<?php
namespace KnitPay\Gateways\Omnipay;

use Pronamic\WordPress\Pay\Plugin;
use WP_REST_Request;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;

/**
 * Title: Omnipay Redirect Controller
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 8.87.11.0
 * @since 8.87.11.0
 */
class RestController {
	/**
	 * Omnipay integration object.
	 *
	 * @var Integration
	 */
	private $integration;
	private $args;

	/**
	 * Construct notifications controller.
	 *
	 * @param Integration $integration
	 *            Integration.
	 */
	public function __construct( Integration $integration, $args ) {
		$this->integration = $integration;
		$this->args        = $args;
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
		if ( isset( $this->args['static_return_url'] ) ) {
			\register_rest_route(
				'/knit-pay/' . $this->integration->get_id() . '/v1',
				'/return/(?P<hash>\w+)/(?P<config_id>\w+)/',
				[
					'args'                => [],
					'methods'             => [ 'GET', 'POST' ],
					'callback'            => [
						$this,
						'rest_api_omnipay_return_url_listener',
					],
					'permission_callback' => [
						$this,
						'rest_api_omnipay_listener_permission',
					],
				]
			);
		}

		if ( isset( $this->args['accept_notification'] ) ) {
			\register_rest_route(
				'/knit-pay/' . $this->integration->get_id() . '/v1',
				'/notification/(?P<hash>\w+)/(?P<config_id>\w+)/',
				[
					'args'                => [],
					'methods'             => [ 'GET', 'POST' ],
					'callback'            => [
						$this,
						'rest_api_omnipay_notification_listener',
					],
					'permission_callback' => [
						$this,
						'rest_api_omnipay_listener_permission',
					],
				]
			);
		}
	}

	/**
	 * REST API Omnipay Return URL Listener handler.
	 *
	 * @param WP_REST_Request $request
	 *            Request.
	 * @return object
	 */
	public function rest_api_omnipay_return_url_listener( WP_REST_Request $request ) {
		$config_id = intval( $request->get_param( 'config_id' ) );
		if ( $this->integration->get_id() !== \get_post_meta( $config_id, '_pronamic_gateway_id', true ) ) {
			return;
		}
		
		$omnipay_transaction_id_key = ltrim( $this->args['omnipay_transaction_id'], '{data:' );
		$omnipay_transaction_id_key = rtrim( $omnipay_transaction_id_key, '}' );
		
		$payment = get_pronamic_payment_by_meta( 'omnipay_transaction_id', $request->get_param( $omnipay_transaction_id_key ) );

		if ( null === $payment ) {
			return new \WP_Error(
				'rest_payment_not_found',
				\__( 'No payment found.', 'knit-pay-lang' ),
				[
					'status' => 200,
				]
			);
		}
		
		if ( $payment->get_config_id() !== $config_id ) {
			return;
		}

		// Check if we should redirect.
		$should_redirect = true;

		/**
		 * Filter whether or not to allow redirects on payment return.
		 *
		 * @param bool    $should_redirect Flag to indicate if redirect is allowed on handling payment return.
		 * @param Payment $payment         Payment.
		 */
		$should_redirect = apply_filters( 'pronamic_pay_return_should_redirect', $should_redirect, $payment );

		try {
			Plugin::update_payment( $payment, $should_redirect );
		} catch ( \Exception $e ) {
			self::render_exception( $e );

			exit;
		}
		
		/*
		 TODO not checking payment status again for now.
		 $recheck_status = $request->get_param( 'recheck_status' );
		if ( is_null( $recheck_status ) ) {
			$recheck_status = 1;
		}

		if ( PaymentStatus::SUCCESS === $payment->get_status() || PaymentStatus::EXPIRED === $payment->get_status() ) {
			$next_redirect_url = $payment->get_return_url();
		} elseif ( 5 <= $recheck_status ) {
			$next_redirect_url = $payment->get_return_url();
		} else {
			$recheck_status++;
			$request_query_params                   = $request->get_query_params();
			$request_query_params['recheck_status'] = $recheck_status;
			
			$next_redirect_url = add_query_arg( $request_query_params, \rest_url( $request->get_route() ) );
		}

		wp_safe_redirect( $next_redirect_url );
		exit; */
	}
	
	/**
	 * REST API Omnipay Notification Listener handler.
	 *
	 * @param WP_REST_Request $request
	 *            Request.
	 * @return object
	 */
	public function rest_api_omnipay_notification_listener( WP_REST_Request $request ) {
		$config_id = intval( $request->get_param( 'config_id' ) );
		if ( $this->integration->get_id() !== \get_post_meta( $config_id, '_pronamic_gateway_id', true ) ) {
			return;
		}

		$omnipay_transaction_id_key = ltrim( $this->args['omnipay_transaction_id'], '{data:' );
		$omnipay_transaction_id_key = rtrim( $omnipay_transaction_id_key, '}' );

		$payment = get_pronamic_payment_by_meta( 'omnipay_transaction_id', $request->get_param( $omnipay_transaction_id_key ) );

		if ( null === $payment ) {
			return new \WP_Error(
				'rest_payment_not_found',
				\__( 'No payment found.', 'knit-pay-lang' ),
				[
					'status' => 200,
				]
			);
		}
		
		if ( $payment->get_config_id() !== $config_id ) {
			return;
		}
		
		// Add note.
		$payment->add_note( 'Received Notification' );
		
		// Log webhook request.
		do_action( 'pronamic_pay_webhook_log_payment', $payment );
		
		// Update payment.
		Plugin::update_payment( $payment, false );
		exit;
	}

	/**
	 * REST API Omnipay listener permission handler.
	 *
	 * @param WP_REST_Request $request
	 *            Request.
	 * @return bool
	 */
	public function rest_api_omnipay_listener_permission( WP_REST_Request $request ) {
		$hash = $request->get_param( 'hash' );

		if ( empty( $hash ) ) {
			return false;
		}

		return \wp_hash( home_url( '/' ) ) === $hash;
	}
}
