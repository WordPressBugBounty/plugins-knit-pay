<?php
namespace KnitPay\Gateways\PayU;

use Pronamic\WordPress\Pay\Plugin;

/**
 * Title: PayU Webhook Listner
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 5.6.2.0
 * @since 5.6.2.0
 */
class Listener {


	public static function listen() {
		if ( ! filter_has_var( INPUT_GET, 'kp_payu_webhook' ) ) {
			return;
		}

		$post_string = file_get_contents( 'php://input' );

		// Convert Query String to Array.
		parse_str( $post_string, $post_array );

		$payment = get_pronamic_payment_by_transaction_id( $post_array['txnid'] );

		if ( null === $payment ) {
			exit;
		}

		// Add note.
		$note = sprintf(
		/* translators: %s: PayU */
			__( 'Webhook requested by %s.', 'knit-pay-lang' ),
			__( 'PayU', 'knit-pay-lang' )
		);

		$payment->add_note( $note );

		// Log webhook request.
		do_action( 'pronamic_pay_webhook_log_payment', $payment );

		// Update payment.
		Plugin::update_payment( $payment, false );
		exit;
	}
}
