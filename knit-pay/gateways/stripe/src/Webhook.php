<?php
namespace KnitPay\Gateways\Stripe;

use WP_Error;
use Exception;
use Pronamic\WordPress\Pay\Plugin;

/**
 * Title: Stripe Webhook
 * Copyright: 2020-2025 Knit Pay
 *
 * @author Knit Pay
 * @version 8.96.11.0
 * @since   8.96.11.0
 */
class Webhook extends Gateway {
	private $config_id;
	private $webhook_url;
	protected $config;

	/**
	 * Constructs and initializes an Razorpay Webhook
	 *
	 * @param int    $config_id Configuration id of Razorpay configuration.
	 * @param Config $config Config.
	 */
	public function __construct( $config_id, Config $config ) {
		parent::__construct( $config );
		$this->init( $config );

		$this->config_id   = $config_id;
		$this->config      = $config;
		$this->webhook_url = add_query_arg( 'kp_stripe_webhook', $config_id, home_url( '/' ) );
	}

	/**
	 *  @return null
	 */
	/*
	public function configure_webhook() {
		$api = new API( $this->config );
		try {
			// If webhook id not available, try to get it from PayPal.
			if ( empty( $this->config->webhook_id ) ) {
				$this->config->webhook_id = $this->find_existing_webhook();
			}
			// If webhook id is not available even after checking PayPal, create new.
			if ( empty( $this->config->webhook_id ) ) {
				$paypal_webhook = $api->create_webhook( $this->get_paypal_webhook_data() );
				update_post_meta( $this->config_id, '_pronamic_gateway_stripe_webhook_id', $paypal_webhook->id );
				return;
			}

			// Update existing Webhook.
			$paypal_webhook = $api->update_webhook( $this->get_paypal_webhook_data(), $this->config->webhook_id );
		} catch ( Exception $e ) {
			$this->reset_webhook( false );

			return new WP_Error( 'paypay_error', $e->getMessage() );
		}
	}

	private function reset_webhook( $retry = true ) {
		$this->config->webhook_id = '';
		update_post_meta( $this->config_id, '_pronamic_gateway_paypal_webhook_id', '' );
		if ( $retry ) {
			$this->configure_webhook();
		}
	}

	private function find_existing_webhook() {
		$api = new API( $this->config );

		$paypal_webhooks = $api->list_webhooks();

		if ( 0 === count( $paypal_webhooks ) ) {
			return false;
		}
		foreach ( $paypal_webhooks as $paypal_webhook ) {
			if ( $this->webhook_url !== $paypal_webhook->url ) {
				continue;
			}
			update_post_meta( $this->config_id, '_pronamic_gateway_paypal_webhook_id', $paypal_webhook->id );
			return $paypal_webhook->id;
		}

		return false;
	}

	private function get_paypal_webhook_data() {
		$required_events = [
			[
				'name' => 'CHECKOUT.ORDER.APPROVED',
			],
		];

		return [
			'url'         => $this->webhook_url,
			'event_types' => $required_events,
		];
	}*/

	public static function listen() {
		if ( ! filter_has_var( INPUT_GET, 'kp_stripe_webhook' ) ) {
			return;
		}

		$post_body = @file_get_contents( 'php://input' );
		$event     = json_decode( $post_body );

		if ( json_last_error() !== 0 ) {
			exit;
		}

		if ( empty( $event->type ) ) {
			exit;
		}

		$event_type = explode( '.', $event->type )[0];

		switch ( $event_type ) {
			case 'checkout':
				$payment = get_pronamic_payment( $event->data->object->metadata->knitpay_payment_id );
				break;

			default:
				exit;
		}

		if ( null === $payment ) {
			exit;
		}

		// Add note.
		$note = sprintf(
		/* translators: %s: Razorpay */
			__( 'Webhook requested by %s.', 'knit-pay-lang' ),
			__( 'Stripe', 'knit-pay-lang' )
		);

		$payment->add_note( $note );

		// Log webhook request.
		do_action( 'pronamic_pay_webhook_log_payment', $payment );

		$payment->save();

		// Update payment.
		Plugin::update_payment( $payment, false );
		exit;
	}
}
