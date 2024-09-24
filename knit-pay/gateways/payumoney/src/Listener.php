<?php
namespace KnitPay\Gateways\PayUmoney;

use Pronamic\WordPress\Pay\Plugin;

/**
 * Title: PayUMoney Webhook Listner
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 1.0.0
 * @since 2.2.3.0
 */
class Listener {


	public static function listen() {
		if ( ! isset( $_SERVER['HTTP_PAYUMONEY_WEBHOOK'] ) ) {
			return;
		}

		$post_body = file_get_contents( 'php://input' );
		$post_body = json_decode( $post_body );

		$payment = get_pronamic_payment( $post_body->merchantTransactionId );

		if ( null === $payment ) {
			exit;
		}

		// Add note.
		$note = sprintf(
		/* translators: %s: EMS */
			__( 'Webhook requested by %s.', 'knit-pay-lang' ),
			__( 'PayUmoney', 'knit-pay-lang' )
		);

		$payment->add_note( $note );

		// Log webhook request.
		do_action( 'pronamic_pay_webhook_log_payment', $payment );

		// Update payment.
		Plugin::update_payment( $payment, false );
		exit;
	}
}
