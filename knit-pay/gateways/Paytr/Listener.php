<?php
namespace KnitPay\Gateways\Paytr;

use Pronamic\WordPress\Pay\Payments\FailureReason;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;

/**
 * Title: PayTR Webhook Listner
 * Copyright: 2020-2026 Knit Pay
 *
 * @author Knit Pay
 * @version 9.6.0.1
 * @since 8.86.0.0
 */
class Listener {


	public static function listen() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- External payment gateway webhook via GET/POST; no WordPress nonce available.
		if ( ! isset( $_GET['kp_paytr_webhook'] ) || ! isset( $_POST['merchant_oid'] ) ) {
			return;
		}

		$merchant_oid = sanitize_text_field( wp_unslash( $_POST['merchant_oid'] ) );
		$status       = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
		$total_amount = isset( $_POST['total_amount'] ) ? sanitize_text_field( wp_unslash( $_POST['total_amount'] ) ) : '';

		$payment = get_pronamic_payment_by_transaction_id( $merchant_oid );

		if ( null === $payment ) {
			die( 'PAYTR notification failed: Order ID not found.' );
		}

		// Load config from the payment's own config_id (never from $_GET).
		$config_id = $payment->get_config_id();
		if ( empty( $config_id ) ) {
			die( 'PAYTR notification failed: missing configuration.' );
		}

		$paytr_integration = new Integration();
		$config            = $paytr_integration->get_config( $config_id );

		if ( empty( $config->merchant_key ) || empty( $config->merchant_salt ) ) {
			die( 'PAYTR notification failed: missing merchant credentials.' );
		}

		$generated_hash = base64_encode( hash_hmac( 'sha256', $merchant_oid . $config->merchant_salt . $status . $total_amount, $config->merchant_key, true ) );

		$hash_sanitized = isset( $_POST['hash'] ) ? sanitize_text_field( wp_unslash( $_POST['hash'] ) ) : '';
		if ( $generated_hash !== $hash_sanitized ) {
			die( 'PAYTR notification failed: bad hash' );
		}

		// Verify amount: collected amount must be at least the order total.
		// Note: PayTR may collect MORE than payment_amount for installments (see https://dev.paytr.com/en/iframe-api/iframe-api-2-adim).
		$expected_amount = $payment->get_total_amount()->get_minor_units()->format( 0, '.', '' );
		if ( floatval( $total_amount ) < floatval( $expected_amount ) ) {
			die( 'PAYTR notification failed: amount mismatch.' );
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
		$post_keys   = array_map( 'sanitize_key', array_keys( wp_unslash( $_POST ) ) );
		$post_values = array_map( 'sanitize_text_field', array_values( wp_unslash( $_POST ) ) );
		$post_array  = array_combine( $post_keys, $post_values );
		
		// Save Post Array.
		$payment->add_note( '<strong>PayTR Response:</strong><br><pre>' . wp_json_encode( $post_array, JSON_PRETTY_PRINT ) . '</pre><br>' );
		
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
