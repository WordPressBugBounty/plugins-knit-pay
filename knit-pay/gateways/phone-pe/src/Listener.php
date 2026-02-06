<?php
namespace KnitPay\Gateways\PhonePe;

use Pronamic\WordPress\Pay\Plugin;

/**
 * Title: PhonePe Webhook Listner
 * Copyright: 2020-2026 Knit Pay
 *
 * @author Knit Pay
 * @version 8.96.3.0
 * @since 8.96.3.0
 */
class Listener {


	public static function listen() {
		if ( ! filter_has_var( INPUT_GET, 'kp_phonepe_webhook_payment_id' ) || ! filter_has_var( INPUT_GET, 'key' ) ) {
			return;
		}

		$payment_id  = filter_input( INPUT_GET, 'kp_phonepe_webhook_payment_id', FILTER_SANITIZE_NUMBER_INT );
		$payment_key = \sanitize_text_field( \wp_unslash( $_GET['key'] ) );

		$payment = get_pronamic_payment( $payment_id );

		if ( null === $payment ) {
			exit;
		}

		if ( $payment->get_key() !== $payment_key ) {
			exit;
		}

		// Add note.
		$note = sprintf(
		/* translators: %s: PhonePe */
			__( 'Webhook requested by %s.', 'knit-pay-lang' ),
			__( 'PhonePe', 'knit-pay-lang' )
		);

		$payment->add_note( $note );

		// Log webhook request.
		do_action( 'pronamic_pay_webhook_log_payment', $payment );

		// Update payment.
		Plugin::update_payment( $payment, false );
		exit;
	}
}
