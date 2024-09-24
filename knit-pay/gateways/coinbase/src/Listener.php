<?php
namespace KnitPay\Gateways\Coinbase;

use Pronamic\WordPress\Pay\Plugin;

/**
 * Title: Coinbase Commerce Webhook Listner
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 8.77.0.0
 * @since 8.77.0.0
 */
class Listener {


	public static function listen() {
		if ( ! filter_has_var( INPUT_GET, 'kp_coinbase_webhook' ) ) {
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
		
		$event_data = $data['event']['data'];

		$payment = get_pronamic_payment_by_transaction_id( $event_data['code'] );

		if ( null === $payment ) {
			exit;
		}
		
		if ( ! self::verify_webhook_signature( $post_body, $payment ) ) {
			exit;
		}

		// Add note.
		$note = sprintf(
		/* translators: %s: Coinbase Commerce */
			__( 'Webhook requested by %s.', 'knit-pay-lang' ),
			__( 'Coinbase Commerce', 'knit-pay-lang' )
		);

		$payment->add_note( $note );

		// Log webhook request.
		do_action( 'pronamic_pay_webhook_log_payment', $payment );

		// Update payment.
		Plugin::update_payment( $payment, false );
		exit;
	}
	
	private static function verify_webhook_signature( $post_body, $object ) {
		$coinbase_integration = new Integration();
		$config               = $coinbase_integration->get_config( $object->get_config_id() );
		
		$webhook_shared_secret = $config->webhook_shared_secret;
		
		if ( ! filter_has_var( INPUT_SERVER, 'HTTP_X_CC_WEBHOOK_SIGNATURE' ) ) {
			return false;
		}
		
		$sig = filter_input( INPUT_SERVER, 'HTTP_X_CC_WEBHOOK_SIGNATURE', FILTER_SANITIZE_STRING );
		
		$sig2 = hash_hmac( 'sha256', $post_body, $webhook_shared_secret );
		
		if ( $sig === $sig2 ) {
			return true;
		}
		
		$object->add_note( 'Webhook Error: Signature Missmatch.' );
		return false;
	}
}
