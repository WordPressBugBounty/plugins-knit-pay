<?php

namespace KnitPay\Gateways\Cashfree;

use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: Cashfree Statuses
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 1.0.0
 * @since   2.4
 */
class Statuses {
	const ACTIVE = 'ACTIVE';

	const PAID = 'PAID';

	const EXPIRED = 'EXPIRED';

	const PENDING = 'PENDING';

	const CANCELLED = 'CANCELLED';

	const SUCCESS = 'SUCCESS';
	
	const NOT_ATTEMPTED = 'NOT_ATTEMPTED';

	const FLAGGED = 'FLAGGED';

	const VOID = 'VOID';
	
	const FAILED = 'FAILED';
	
	const USER_DROPPED = 'USER_DROPPED';

	/**
	 * Transform an Cashfree status to an Knit Pay status
	 *
	 * @param string $status
	 *
	 * @return string
	 */
	public static function transform( $status ) {
		switch ( $status ) {
			case self::SUCCESS:
			case self::PAID:
				return Core_Statuses::SUCCESS;

			case self::CANCELLED:
				return Core_Statuses::CANCELLED;

			case self::FLAGGED:
				return Core_Statuses::ON_HOLD;

			case self::FAILED:
			case self::USER_DROPPED:
			case self::VOID:
				return Core_Statuses::FAILURE;
				
			case self::EXPIRED:
				return Core_Statuses::EXPIRED;

			case self::PENDING:
			case self::ACTIVE:
			case self::NOT_ATTEMPTED:
			default:
				return Core_Statuses::OPEN;
		}
	}
}
