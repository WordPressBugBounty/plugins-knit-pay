<?php

namespace KnitPay\Gateways\MyFatoorah;

use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: MyFatoorah Statuses
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 1.0.0
 * @since   6.63.0.0
 */
class Statuses {

	const PENDING = 'Pending';

	const CANCELLED = 'Canceled';

	const PAID = 'Paid';

	/**
	 * Transform an MyFatoorah status to an Knit Pay status
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

			case self::PENDING:
			default:
				return Core_Statuses::OPEN;
		}
	}
}
