<?php

namespace KnitPay\Gateways\Pesapal;

use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: Pesapal Statuses
 * Copyright: 2020-2025 Knit Pay
 *
 * @author  Knit Pay
 * @version 9.2.0.0
 * @since   9.2.0.0
 */
class Statuses {
	/**
	 * Pesapal status constants
	 * Based on Pesapal API documentation
	 */
	const COMPLETED = 'COMPLETED';
	const FAILED    = 'FAILED';
	const REVERSED  = 'REVERSED';
	const INVALID   = 'INVALID';

	/**
	 * Transform Pesapal status to Knit Pay status
	 *
	 * @param string $status Status value from Pesapal
	 * @return string Knit Pay status constant
	 */
	public static function transform( $status ) {
		// Convert to uppercase for comparison
		$status = strtoupper( (string) $status );
		
		switch ( $status ) {
			// SUCCESS STATUS
			case self::COMPLETED:
				return Core_Statuses::SUCCESS;
			
			// FAILURE STATUSES
			case self::FAILED:
			case self::REVERSED:
				return Core_Statuses::FAILURE;
			
			// PENDING/INVALID STATUSES
			case self::INVALID:
			default:
				// Any unknown status is treated as OPEN (pending)
				return Core_Statuses::OPEN;
		}
	}
}
