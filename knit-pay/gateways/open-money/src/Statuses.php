<?php

namespace KnitPay\Gateways\OpenMoney;

use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: Open Money Statuses
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 5.3.0
 * @since   5.3.0
 */
class Statuses {
	const CREATED = 'created';

	const CAPTURED = 'captured';

	const PENDING = 'pending';

	const CANCELLED = 'cancelled';

	const FAILED = 'failed';

	/**
	 * Transform an Open Money status to an Knit Pay status
	 *
	 * @param string $status
	 *
	 * @return string
	 */
	public static function transform( $status ) {
		switch ( $status ) {
			case self::CAPTURED:
				return Core_Statuses::SUCCESS;

			case self::CANCELLED:
				return Core_Statuses::CANCELLED;

			case self::FAILED:
				return Core_Statuses::FAILURE;

			case self::PENDING:
			case self::CREATED:
			default:
				return Core_Statuses::OPEN;
		}
	}
}
