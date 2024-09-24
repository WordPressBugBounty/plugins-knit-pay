<?php

namespace KnitPay\Gateways\Slydepay;

use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: Slydepay Statuses
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 6.67.0.0
 * @since   6.67.0.0
 */
class Statuses {

	const NEW = 'NEW';

	const PENDING = 'PENDING';

	const CONFIRMED = 'CONFIRMED';

	const CANCELLED = 'CANCELLED';

	const DISPUTED = 'DISPUTED';

	/**
	 * Transform an Slydepay status to an Knit Pay status
	 *
	 * @param string $status
	 *
	 * @return string
	 */
	public static function transform( $status ) {
		switch ( $status ) {
			case self::CONFIRMED:
				return Core_Statuses::SUCCESS;

			case self::CANCELLED:
				return Core_Statuses::CANCELLED;

			case self::DISPUTED:
				return Core_Statuses::ON_HOLD;

			case self::NEW:
			case self::PENDING:
			default:
				return Core_Statuses::OPEN;
		}
	}
}
