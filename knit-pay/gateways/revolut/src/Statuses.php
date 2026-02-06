<?php

namespace KnitPay\Gateways\Revolut;

use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: Revolut Statuses
 * Copyright: 2020-2026 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.98.0.0
 * @since   8.98.0.0
 */
class Statuses {
	/**
	 * Revolut order status constants
	 * Based on: https://developer.revolut.com/docs/guides/accept-payments/other-resources/order-payment-flow
	 */
	
	// Order is created and awaiting payment
	const PENDING = 'pending';
	
	// Payment is being processed
	const PROCESSING = 'processing';
	
	// Payment completed successfully
	const COMPLETED = 'completed';
	
	// Payment authorized but not captured (for two-step payments)
	const AUTHORISED = 'authorised';

	// Payment captured.
	const CAPTURED = 'captured';
	
	// Order was cancelled
	const CANCELLED = 'cancelled';
	
	// Payment failed
	const FAILED = 'failed';

	// Payment Declined
	const DECLINED = 'declined';
	
	/**
	 * Transform Revolut status to Knit Pay status
	 *
	 * @param string $status Status value from Revolut
	 * @return string Knit Pay status constant
	 */
	public static function transform( $status ) {
		
		switch ( $status ) {
			// SUCCESS - Payment completed successfully
			case self::COMPLETED:
			case self::AUTHORISED:
			case self::CAPTURED:
				return Core_Statuses::SUCCESS;
			
			// FAILURE - Payment failed
			case self::FAILED:
			case self::DECLINED:
				return Core_Statuses::FAILURE;
			
			// CANCELLED - User cancelled payment
			case self::CANCELLED:
				return Core_Statuses::CANCELLED;
			
			// OPEN/PENDING - Payment in progress
			case self::PENDING:
			case self::PROCESSING:
			default:
				return Core_Statuses::OPEN;
		}
	}
}
