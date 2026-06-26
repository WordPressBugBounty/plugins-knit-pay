<?php

namespace KnitPay\Gateways\Tosla;

use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: Tosla Statuses
 * Copyright: 2020-2026 Knit Pay
 *
 * @author  Knit Pay
 * @version 9.6.0.0
 * @since   9.6.0.0
 */
class Statuses {
	/**
	 * RequestStatus values from Tosla API documentation.
	 *
	 * @var string
	 */
	const SUCCESS              = '1';
	const ERROR                = '0';
	const CANCELLED            = '2';
	const PARTIALLY_REFUNDED   = '3';
	const FULLY_REFUNDED       = '4';
	const PREAUTH_CLOSED       = '5';
	const PARTIAL_DISPUTE      = '6';
	const FULL_DISPUTE         = '7';
	const THREE_D_WAITING      = '10';
	const THREE_D_SENT         = '11';
	const THREE_D_RESPONSE     = '12';
	const REFUND_WAITING       = '14';
	const CANCELLED_ALT        = '15';
	const FORWARD_DATED_REFUND = '16';

	/**
	 * Transform a Tosla RequestStatus to a Knit Pay status.
	 *
	 * @param string $status
	 * @return string
	 */
	public static function transform( $status ) {
		switch ( (string) $status ) {
			case self::SUCCESS:
				return Core_Statuses::SUCCESS;

			case self::ERROR:
				return Core_Statuses::FAILURE;

			case self::CANCELLED:
			case self::CANCELLED_ALT:
				return Core_Statuses::CANCELLED;

			case self::THREE_D_WAITING:
			case self::THREE_D_SENT:
			case self::THREE_D_RESPONSE:
			case self::REFUND_WAITING:
			case self::FORWARD_DATED_REFUND:
			case self::PARTIALLY_REFUNDED:
			case self::FULLY_REFUNDED:
			case self::PREAUTH_CLOSED:
			case self::PARTIAL_DISPUTE:
			case self::FULL_DISPUTE:
			default:
				return Core_Statuses::OPEN;
		}
	}
}
