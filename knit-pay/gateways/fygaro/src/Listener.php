<?php
namespace KnitPay\Gateways\Fygaro;

use Pronamic\WordPress\Pay\Payments\PaymentStatus;

/**
 * Title: Fygaro Webhook Listner
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 5.0.0
 * @since 5.0.0
 */
class Listener {


	public static function listen() {
		if ( ! filter_has_var( INPUT_GET, 'kp_fygaro_webhook' ) ) {
			return;
		}

		$post_body = file_get_contents( 'php://input' );
		$data      = json_decode( $post_body, true );

		if ( json_last_error() !== 0 ) {
			exit;
		}

		if ( empty( $data['jwt'] ) ) {
			exit;
		}
		
		$payment = get_pronamic_payment_by_transaction_id( $data['customReference'] );
		
		if ( is_null( $payment ) ) {
			exit;
		}
		
		if ( PaymentStatus::SUCCESS === $payment->get_status() ) {
			exit;
		}

		$fygaro_integration = new Integration();
		$config             = $fygaro_integration->get_config( $payment->get_config_id() );

		// JWT v6 conflicting with many plugings. that's why restricting it to only Faygaro.
		if ( ! class_exists( 'Firebase\JWT\JWT' ) ) {
			require __DIR__ . '/vendor/autoload.php';
		}

		if ( class_exists( 'Firebase\JWT\Key' ) ) {
			// Decode with JWT v6 and above.
			$jwt_data = \Firebase\JWT\JWT::decode( $data['jwt'], new \Firebase\JWT\Key( $config->api_secret, 'HS256' ) );
		} else {
			// Decode with JWT v5 or below.
			$jwt_data = \Firebase\JWT\JWT::decode( $data['jwt'], $config->api_secret, [ 'HS256' ] );
		}

		if ( $jwt_data->customReference !== $payment->get_transaction_id() ) {
			exit;
		}
		
		// Add note.
		$note = sprintf(
			/* translators: %s: Fygaro */
			__( 'Webhook requested by %s.', 'knit-pay-lang' ),
			__( 'Fygaro', 'knit-pay-lang' )
		);
		
		$payment->add_note( $note );
		
		// Log webhook request.
		do_action( 'pronamic_pay_webhook_log_payment', $payment );
		
		
		$payment->set_transaction_id( $jwt_data->reference );
		
		$note = '<strong>Faygaro Webhook Response:</strong><br><pre>' . print_r( $data, true ) . '</pre>';
		
		$payment->set_status( PaymentStatus::SUCCESS );
		$payment->add_note( $note );
		
		// Update payment in data store.
		$payment->save();
		
		exit;
	}
}
