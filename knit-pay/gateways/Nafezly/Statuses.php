<?php

namespace KnitPay\Gateways\Nafezly;

use Pronamic\WordPress\Pay\Payments\PaymentStatus;

/**
 * Title: Nafezly Statuses
 * Copyright: 2020-2025 Knit Pay
 *
 * @author  Knit Pay
 * @version 1.0.0
 * @since   1.0.0
 */
class Statuses {
	/**
	 * Map a Nafezly verify() success flag to a Knit Pay status.
	 *
	 * @param bool   $success
	 * @param string $message
	 * @return string
	 */
	public static function transform( $success, $message = '' ) {
		if ( true === $success ) {
			return PaymentStatus::SUCCESS;
		}

		return PaymentStatus::FAILURE;
	}
}
