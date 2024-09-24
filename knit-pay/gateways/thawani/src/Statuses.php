<?php

namespace KnitPay\Gateways\Thawani;

use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: Thawani Statuses
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 6.70.0.0
 * @since   6.70.0.0
 */
class Statuses {

	const PAID = 'paid';

	const CANCELLED = 'cancelled';

	const UNPAID = 'unpaid';

	/**
	 * Transform an Thawani status to an Knit Pay status
	 *
	 * @param string $status
	 *
	 * @return string
	 */
	public static function transform( $status ) {
		switch ( $status ) {
			case self::PAID:
				return Core_Statuses::SUCCESS;

			case self::CANCELLED:
				return Core_Statuses::CANCELLED;

			case self::UNPAID:
			default:
				return Core_Statuses::OPEN;
		}
	}
}
