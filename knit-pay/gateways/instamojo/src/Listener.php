<?php
namespace KnitPay\Gateways\Instamojo;

use Pronamic\WordPress\Pay\Plugin;

/**
 * Title: Instamojo Webhook Listner
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 5.9.1.0
 * @since 5.9.1.0
 */
class Listener {

	public static function listen() {
		if ( ! filter_has_var( INPUT_GET, 'kp_instamojo_webhook' ) || ! filter_has_var( INPUT_POST, 'mac' ) ) {
			return;
		}

		$payment_request_id = array_key_exists( 'payment_request_id', $_POST ) ? \sanitize_text_field( \wp_unslash( $_POST['payment_request_id'] ) ) : null;
		$payment            = get_pronamic_payment_by_transaction_id( $payment_request_id );

		if ( null === $payment ) {
			exit;
		}

		// Add note.
		$note = sprintf(
		/* translators: %s: Instamojo */
			__( 'Webhook requested by %s.', 'knit-pay-lang' ),
			__( 'Instamojo', 'knit-pay-lang' )
		);

		$payment->add_note( $note );

		// Log webhook request.
		do_action( 'pronamic_pay_webhook_log_payment', $payment );

		// Update payment.
		Plugin::update_payment( $payment, false );
		exit;
	}
}
