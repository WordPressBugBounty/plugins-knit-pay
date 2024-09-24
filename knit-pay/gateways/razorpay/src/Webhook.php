<?php
namespace KnitPay\Gateways\Razorpay;

use Razorpay\Api\Errors\ServerError;
use Razorpay\Api\Errors\BadRequestError;
use Requests_Exception;
use WP_Error;

/**
 * Title: Razorpay Webhook
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 4.5.7.0
 * @since   4.5.7.0
 */
class Webhook extends Gateway {
	private $config_id;
	private $webhook_url;
	private $removed_event_names;

	/**
	 * Constructs and initializes an Razorpay Webhook
	 *
	 * @param int    $config_id Configuration id of Razorpay configuration.
	 * @param Config $config Config.
	 */
	public function __construct( $config_id, Config $config ) {
		parent::__construct( $config );
		$this->init( $config );

		$this->config_id           = $config_id;
		$this->webhook_url         = add_query_arg( 'kp_razorpay_webhook', '', home_url( '/' ) );
		$this->removed_event_names = [];

		if ( empty( $this->config->webhook_secret ) ) {
			$this->config->webhook_secret = wp_generate_password( 15 );
			update_post_meta( $this->config_id, '_pronamic_gateway_razorpay_webhook_secret', $this->config->webhook_secret );
		}
	}

	/**
	 *  @return null
	 */
	public function configure_webhook() {
		$api = $this->get_razorpay_api();
		try {
			// If webhook id not available, try to get it from razorpay.
			if ( empty( $this->config->webhook_id ) ) {
				$this->config->webhook_id = $this->find_existing_webhook();
			}
			// If webhook id is not available even after checking razorpay, create new.
			if ( empty( $this->config->webhook_id ) ) {
				$razorpay_webhook = $api->webhook->create( $this->get_razorpay_webhook_data() );
				update_post_meta( $this->config_id, '_pronamic_gateway_razorpay_webhook_id', $razorpay_webhook->id );
				return;
			}

			// Update existing Webhook.
			$razorpay_webhook = $api->webhook->edit( $this->get_razorpay_webhook_data(), $this->config->webhook_id );
		} catch ( BadRequestError $e ) {
			$this->reset_webhook( false );
			if ( 'record not found' === $e->getMessage() ) {
				$this->configure_webhook();
				return;
			}

			// If fail was due to unsupported event names, remove them and retry.
			$error_array = explode( ':', $e->getMessage() );
			if ( 'Invalid event name/names' === $error_array[0] ) {
				$error_event_names         = trim( $error_array[1] );
				$error_event_names         = explode( ', ', $error_event_names );
				$this->removed_event_names = $error_event_names;
				$this->configure_webhook();
				return;
			}

			return new WP_Error( 'razorpay_error', $e->getMessage() );
		} catch ( Requests_Exception $e ) {
			return new WP_Error( 'razorpay_error', $e->getMessage() );
		} catch ( ServerError $e ) {
			return new WP_Error( 'razorpay_error', $e->getMessage() );
		}
	}

	private function reset_webhook( $retry = true ) {
		$this->config->webhook_id = '';
		update_post_meta( $this->config_id, '_pronamic_gateway_razorpay_webhook_id', '' );
		if ( $retry ) {
			$this->configure_webhook();
		}
	}

	private function find_existing_webhook() {
		$api = $this->get_razorpay_api();

		$razorpay_webhooks = $api->webhook->all();
		if ( 0 === $razorpay_webhooks->count ) {
			return false;
		}
		foreach ( $razorpay_webhooks->items as $razorpay_webhook ) {
			if ( $this->webhook_url !== $razorpay_webhook->url ) {
				continue;
			}
			update_post_meta( $this->config_id, '_pronamic_gateway_razorpay_webhook_id', $razorpay_webhook->id );
			return $razorpay_webhook->id;
		}

		return false;
	}

	private function get_razorpay_webhook_data() {
		$required_events = [
			'payment.authorized'   => true,
			'payment.failed'       => true,
			'subscription.charged' => true,
		];

		// Remove unsupported webhook events.
		if ( ! empty( $this->removed_event_names ) ) {
			foreach ( $this->removed_event_names as $event_name ) {
				unset( $required_events[ $event_name ] );
			}
		}

		return [
			'url'    => $this->webhook_url,
			'secret' => $this->config->webhook_secret,
			'active' => true,
			'events' => $required_events,
		];
	}
}
