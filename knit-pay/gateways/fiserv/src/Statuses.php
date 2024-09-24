<?php

namespace KnitPay\Gateways\Fiserv;

use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: Fiserv Statuses
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 6.64.0.0
 * @since   6.64.0.0
 */
class Statuses {
	/**
	 * SUCCESS
	 *
	 * @var string
	 */
	const SUCCESS = 'Y';

	/**
	 * FAILURE.
	 *
	 * @var string
	 */
	const FAILURE = 'N';
	
	const INITIATED = '?';

	/**
	 * Transform an Fiserv status to an Knit Pay status
	 *
	 * @param string $status
	 *
	 * @return string
	 */
	public static function transform( $status ) {
		switch ( $status ) {
			case self::SUCCESS:
				return Core_Statuses::SUCCESS;

			case self::FAILURE:
				return Core_Statuses::FAILURE;

			case self::INITIATED:
			default:
				return Core_Statuses::OPEN;
		}
	}
}
