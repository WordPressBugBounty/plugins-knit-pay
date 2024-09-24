<?php

namespace KnitPay\Gateways\CBK;

use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: CBK (Commercial Bank of Kuwait - Al-Tijari) Statuses
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 6.68.0.0
 * @since   6.68.0.0
 */
class Statuses {
	/**
	 * SUCCESS
	 *
	 * @var string
	 */
	const SUCCESS = '1';

	/**
	 * FAILURE.
	 *
	 * @var string
	 */
	const FAILED = '2';

	const CANCELLED = '3';

	/**
	 * Transform an CBK (Commercial Bank of Kuwait - Al-Tijari) status to an Knit Pay status
	 *
	 * @param string $status
	 *
	 * @return string
	 */
	public static function transform( $status ) {
		switch ( $status ) {
			case self::SUCCESS:
				return Core_Statuses::SUCCESS;

			case self::FAILED:
				return Core_Statuses::FAILURE;

			case self::CANCELLED:
				return Core_Statuses::CANCELLED;

			default:
				return Core_Statuses::OPEN;
		}
	}
}
