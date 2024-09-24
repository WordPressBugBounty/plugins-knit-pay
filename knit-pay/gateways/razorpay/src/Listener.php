<?php
namespace KnitPay\Gateways\Razorpay;

use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;
use Razorpay\Api\Api;
use Razorpay\Api\Errors;
use WC_Subscription;

/**
 * Title: Razorpay Webhook Listner
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 1.0.0
 * @since 2.2.4.0
 */
class Listener {
	public static function listen() {
		if ( ! filter_has_var( INPUT_GET, 'kp_razorpay_webhook' ) ) {
			return;
		}

		$post_body = file_get_contents( 'php://input' );
		$data      = json_decode( $post_body, true );

		if ( json_last_error() !== 0 ) {
			exit;
		}

		if ( empty( $data['event'] ) ) {
			exit;
		}

		$event_type = explode( '.', $data['event'] )[0];

		switch ( $event_type ) {
			case 'payment':
				$razorpay_payment = $data['payload']['payment']['entity'];
				if ( ! isset( $razorpay_payment['notes']['knitpay_payment_id'] ) ) {
					exit;
				}
				$payment = get_pronamic_payment( $razorpay_payment['notes']['knitpay_payment_id'] );
				if ( is_null( $payment ) ) {
					exit;
				}

				if ( ! self::verify_webhook_signature( $post_body, $payment ) ) {
					exit;
				}

				break;
			
			case 'subscription':
				if ( 'subscription.charged' !== $data['event'] || ! array_key_exists( 'knitpay_subscription_id', $data['payload']['subscription']['entity']['notes'] ) ) {
					exit;
				}

				$razorpay_subscription    = $data['payload']['subscription']['entity'];
				$knitpay_subscription_id  = $razorpay_subscription['notes']['knitpay_subscription_id'];
				$knitpay_first_payment_id = $razorpay_subscription['notes']['knitpay_payment_id'];
				$razorpay_subscription_id = $razorpay_subscription['id'];
				$razorpay_order_id        = $data['payload']['payment']['entity']['order_id'];
				$razorpay_payment_id      = $data['payload']['payment']['entity']['id'];

				// First Payment
				$first_payment = \get_pronamic_payment( $knitpay_first_payment_id );
				if ( PaymentStatus::SUCCESS !== $first_payment->get_status() ) {
					$payment = $first_payment;
					break;
				}
				
				// Don't proceed, if this payment is already updated in Knit Pay.
				if ( ! is_null( get_pronamic_payment_by_transaction_id( $razorpay_payment_id ) ) ) {
					exit;
				}

				$subscription = \get_pronamic_subscription( $knitpay_subscription_id );

				if ( ! isset( $subscription ) ) {
					exit;
				}

				if ( ! self::verify_webhook_signature( $post_body, $subscription ) ) {
					exit;
				}

				$wc_subscription = new WC_Subscription( $subscription->get_source_id() );
				\WCS_Admin_Meta_Boxes::process_renewal_action_request( $wc_subscription );

				$payment = \get_pronamic_payment_by_meta( '_pronamic_payment_razorpay_order_id', $razorpay_order_id );

				// Update Next Payment Date.
				$dates_to_update['next_payment'] = \gmdate( 'Y-m-d H:i:s', $razorpay_subscription['current_end'] );
				$wc_subscription->update_dates( $dates_to_update );

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
			__( 'Razorpay', 'knit-pay-lang' )
		);

		$payment->add_note( $note );

		// Log webhook request.
		do_action( 'pronamic_pay_webhook_log_payment', $payment );

		$payment->save();

		// Update payment.
		Plugin::update_payment( $payment, false );
		exit;
	}

	private static function verify_webhook_signature( $post_body, $object ) {
		$razorpay_integration = new Integration();
		$config               = $razorpay_integration->get_config( $object->get_config_id() );

		$webhook_secret = $config->webhook_secret;

		$api = new Api( $config->key_id, $config->key_secret );
		if ( filter_has_var( INPUT_SERVER, 'HTTP_X_RAZORPAY_SIGNATURE' ) || isset( $webhook_secret ) ) {
			try {
				$api->utility->verifyWebhookSignature(
					$post_body,
					filter_input( INPUT_SERVER, 'HTTP_X_RAZORPAY_SIGNATURE', FILTER_UNSAFE_RAW ),
					$webhook_secret
				);
			} catch ( Errors\SignatureVerificationError $e ) {
				$object->add_note( 'Webhook Error: ' . $e->getMessage() );
				http_response_code( 400 );
				return false;
			}
		}
		return true;
	}
}
