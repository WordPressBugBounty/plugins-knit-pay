<?php

namespace KnitPay\Gateways\Pesapal;

use Pronamic\WordPress\Pay\Plugin;

/**
 * Title: Pesapal Webhook Listener
 * Copyright: 2020-2025 Knit Pay
 *
 * @author  Knit Pay
 * @version 9.2.0.0
 * @since   9.2.0.0
 */
class Listener {
	/**
	 * Integration instance
	 * 
	 * @var Integration
	 */
	private $integration;

	/**
	 * Constructor
	 * 
	 * @param Integration $integration Integration instance
	 */
	public function __construct( Integration $integration ) {
		$this->integration = $integration;
	}

	/**
	 * Listen for webhook requests from Pesapal
	 * 
	 * Pesapal sends IPN notifications via GET request with orderTrackingId and orderMerchantReference
	 */
	public function listen() {
		// Pesapal sends webhooks via GET request
		// Extract parameters from GET request
		$order_tracking_id = \sanitize_text_field( \wp_unslash( $_REQUEST['OrderTrackingId'] ) );
		
		if ( empty( $order_tracking_id ) ) {
			exit;
		}
		
		// Get Payment from Order Tracking ID.
		$payment = get_pronamic_payment_by_transaction_id( $order_tracking_id );

		if ( null === $payment ) {
			exit;
		}

		// Add note.
		$note = sprintf(
		/* translators: %s: Cashfree */
			__( 'Webhook requested by %s.', 'knit-pay-lang' ),
			__( 'Pesapal', 'knit-pay-lang' )
		);

		$payment->add_note( $note );

		// Log webhook request.
		do_action( 'pronamic_pay_webhook_log_payment', $payment );

		// Update payment.
		Plugin::update_payment( $payment, false );
		exit;
	}
}
