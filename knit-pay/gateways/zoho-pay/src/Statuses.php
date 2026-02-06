<?php

namespace KnitPay\Gateways\ZohoPay;

use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: Zoho Pay Statuses
 * Copyright: 2020-2026 Knit Pay
 *
 * @author  Knit Pay
 * @version 9.0.0.0
 * @since   9.0.0.0
 */
class Statuses {
	const ACTIVE = 'active';

	const PAID = 'paid';

	const CANCELED = 'canceled';

	const EXPIRED = 'expired';

	/**
	 * Transform an Zoho Pay status to an Knit Pay status
	 *
	 * @param string $status
	 *
	 * @return string
	 */
	public static function transform( $status ) {
		switch ( $status ) {
			case self::PAID:
				return Core_Statuses::SUCCESS;

			case self::CANCELED:
				return Core_Statuses::CANCELLED;

			case self::EXPIRED:
				return Core_Statuses::EXPIRED;

			case self::ACTIVE:
			default:
				return Core_Statuses::OPEN;
		}
	}
}
