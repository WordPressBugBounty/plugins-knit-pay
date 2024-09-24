<?php
namespace KnitPay\Gateways\Paytr;

use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Payments\FailureReason;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;

/**
 * Title: PayTR Webhook Listner
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 8.86.0.0
 * @since 8.86.0.0
 */
class Listener {


	public static function listen() {
		if ( ! filter_has_var( INPUT_GET, 'kp_paytr_webhook' ) || ! filter_has_var( INPUT_POST, 'merchant_oid' ) ) {
			return;
		}

		$config_id = sanitize_text_field( $_GET['kp_config_id'] );
		
		$merchant_oid = sanitize_text_field( $_POST['merchant_oid'] );
		$status       = sanitize_text_field( $_POST['status'] );
		$total_amount = sanitize_text_field( $_POST['total_amount'] );

		$paytr_integration = new Integration();
		$config            = $paytr_integration->get_config( $config_id );
		
		$hash = base64_encode( hash_hmac( 'sha256', $merchant_oid . $config->merchant_salt . $status . $total_amount, $config->merchant_key, true ) );
		
		if ( $hash !== $_POST['hash'] ) {
			die( 'PAYTR notification failed: bad hash' );
		}
		
		$payment = get_pronamic_payment_by_transaction_id( $merchant_oid );
		
		if ( null === $payment ) {
			die( 'PAYTR notification failed: Order ID not found.' );
		}
		
		// Add note.
		$note = sprintf(
			/* translators: %s: Paytr */
			__( 'Webhook requested by %s.', 'knit-pay-lang' ),
			__( 'Paytr', 'knit-pay-lang' )
		);
		
		$payment->add_note( $note );
		
		// Log webhook request.
		do_action( 'pronamic_pay_webhook_log_payment', $payment );
		
		// Don't take any action if status was already updated.
		// @see https://dev.paytr.com/en/iframe-api/iframe-api-2-adim (Important Warning: 5)
		if ( PaymentStatus::SUCCESS === $payment->get_status() || PaymentStatus::FAILURE === $payment->get_status() ) {
			echo 'OK';
			exit;
		}
		
		// Sanatize $_POST array.
		$post_keys   = array_map( 'sanitize_key', array_keys( $_POST ) );
		$post_values = array_map( 'sanitize_text_field', array_values( $_POST ) );
		$post_array  = array_combine( $post_keys, $post_values );
		
		// Save Post Array.
		$payment->add_note( '<strong>PayTR Response:</strong><br><pre>' . print_r( $post_array, true ) . '</pre><br>' );
		
		// Update Payment.
		if ( 'success' === $status ) {
			$payment->set_status( PaymentStatus::SUCCESS );
		} else {                
			$failure_reason = new FailureReason();
			$failure_reason->set_message( $post_array['failed_reason_msg'] );
			$failure_reason->set_code( $post_array['failed_reason_code'] );
			$payment->set_failure_reason( $failure_reason );
				
			$payment->set_status( PaymentStatus::FAILURE );
		}
		
		// Save payment Changes.
		$payment->save();
	
		echo 'OK';
		exit;
	}
}
