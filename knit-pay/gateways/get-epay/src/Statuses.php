<?php

namespace KnitPay\Gateways\GetEpay;

use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: Get ePay Statuses
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.87.0.0
 * @since   8.87.0.0
 */
class Statuses {
	/**
	 * PENDING
	 *
	 * @var string
	 */
	const PENDING = 'PENDING';

	/**
	 * SUCCESS
	 *
	 * @var string
	 */
	const SUCCESS = 'SUCCESS';

	/**
	 * FAILED.
	 *
	 * @var string
	 */
	const FAILED = 'FAILED';

	/**
	 * Transform an Get ePay status to an Knit Pay status
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

			case self::PENDING:
			default:
				return Core_Statuses::OPEN;
		}
	}
}
