<?php
namespace KnitPay\Gateways\Cashfree;

use Pronamic\WordPress\Pay\Plugin;

/**
 * Title: Cashfree Webhook Listner
 * Copyright: 2020-2026 Knit Pay
 *
 * @author Knit Pay
 * @version 1.0.0
 * @since 2.4
 */
class Listener {

	public static function listen() {
		if ( ! filter_has_var( INPUT_GET, 'kp_cashfree_webhook' ) ) {
			return;
		}

		//phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsRemoteFile
		$post_body = file_get_contents( 'php://input' );
		$data      = json_decode( $post_body, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			exit;
		}

		if ( empty( $data['data'] ) || empty( $data['data']['order'] ) || empty( $data['data']['order']['order_id'] ) ) {
			exit;
		}

		$order_id = \sanitize_text_field( \wp_unslash( $data['data']['order']['order_id'] ) );
		$payment  = get_pronamic_payment_by_transaction_id( $order_id );

		if ( null === $payment ) {
			exit;
		}

		// Add note.
		$note = sprintf(
		/* translators: %s: Cashfree */
			__( 'Webhook requested by %s.', 'knit-pay-lang' ),
			__( 'Cashfree', 'knit-pay-lang' )
		);

		$payment->add_note( $note );

		// Log webhook request.
		do_action( 'pronamic_pay_webhook_log_payment', $payment );

		// Update payment.
		Plugin::update_payment( $payment, false );
		exit;
	}
}
