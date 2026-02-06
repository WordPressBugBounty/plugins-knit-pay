<?php

namespace KnitPay\Gateways\PhonePe;

use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: PhonePe Statuses
 * Copyright: 2020-2026 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.73.0.0
 * @since   8.73.0.0
 */
class Statuses {

	// v1 statuses.
	const PAYMENT_PENDING      = 'PAYMENT_PENDING';
	const SUCCESS              = 'PAYMENT_SUCCESS';
	const PAYMENT_DECLINED     = 'PAYMENT_DECLINED';
	const AUTHORIZATION_FAILED = 'AUTHORIZATION_FAILED';
	const PAYMENT_ERROR        = 'PAYMENT_ERROR';
	const TIMED_OUT            = 'TIMED_OUT';

	// v2 statuses.
	const PENDING   = 'PENDING';
	const COMPLETED = 'COMPLETED';
	const FAILED    = 'FAILED';

	/**
	 * Transform an PhonePe status to an Knit Pay status
	 *
	 * @param string $status
	 *
	 * @return string
	 */
	public static function transform( $status ) {
		switch ( $status ) {
			case self::SUCCESS:
			case self::COMPLETED:
				return Core_Statuses::SUCCESS;

			case self::PAYMENT_DECLINED:
			case self::AUTHORIZATION_FAILED:
			case self::PAYMENT_ERROR:
			case self::FAILED:
				return Core_Statuses::FAILURE;
				
			case self::TIMED_OUT:
				return Core_Statuses::EXPIRED;

			case self::PENDING:
			case self::PAYMENT_PENDING:
			default:
				return Core_Statuses::OPEN;
		}
	}
}
