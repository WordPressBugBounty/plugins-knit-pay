<?php
namespace KnitPay\Gateways\CMI;

use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;

/**
 * Title: CMI Webhook Listner
 * Copyright: 2020-2026 Knit Pay
 *
 * @author Knit Pay
 * @version 8.96.5.0
 * @since 8.96.4.0
 */
class Listener {
	public static function listen() {
		if ( ! filter_has_var( INPUT_GET, 'kp_cmi_webhook' ) || ! filter_has_var( INPUT_POST, 'oid' ) ) {
			return;
		}

		$oid     = \sanitize_text_field( \wp_unslash( $_POST['oid'] ) );
		$payment = get_pronamic_payment_by_transaction_id( $oid );

		if ( null === $payment ) {
			echo 'FAILURE';
			exit;
		}

		// Add note.
		$note = sprintf(
		/* translators: %s: CMI */
			__( 'Webhook requested by %s.', 'knit-pay-lang' ),
			__( 'CMI', 'knit-pay-lang' )
		);

		$payment->add_note( $note );

		// Log webhook request.
		do_action( 'pronamic_pay_webhook_log_payment', $payment );

		// Update payment.
		Plugin::update_payment( $payment, false );

		if ( $payment->get_status() === PaymentStatus::SUCCESS ) {
			echo 'ACTION=POSTAUTH';
		} else {
			echo 'APPROVED';
		}

		exit;
	}
}
