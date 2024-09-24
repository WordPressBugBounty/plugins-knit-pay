<?php
namespace KnitPay\Gateways\Easebuzz;

use Pronamic\WordPress\Pay\Plugin;

/**
 * Title: Easebuzz Webhook Listner
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 1.0.0
 * @since 2.2.4.0
 */
class Listener {
	public static function listen() {
		if ( ! filter_has_var( INPUT_GET, 'easebuzz_webhook' ) || ! filter_has_var( INPUT_POST, 'txnid' ) ) {
			return;
		}

		$payment = get_pronamic_payment_by_transaction_id( \sanitize_text_field( \wp_unslash( $_POST['txnid'] ) ) );

		if ( null === $payment ) {
			exit;
		}

		// Add note.
		$note = sprintf(
		/* translators: %s: Easebuzz */
			__( 'Webhook requested by %s.', 'knit-pay-lang' ),
			__( 'Easebuzz', 'knit-pay-lang' )
		);

		$payment->add_note( $note );

		// Log webhook request.
		do_action( 'pronamic_pay_webhook_log_payment', $payment );

		// Update payment.
		Plugin::update_payment( $payment, false );
		exit;
	}
}
