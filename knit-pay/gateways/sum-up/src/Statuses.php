<?php

namespace KnitPay\Gateways\SumUp;

use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: SumUp Statuses
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.91.0.0
 * @since   8.91.0.0
 */
class Statuses {
	/**
	 * Paid
	 *
	 * @var string
	 */
	const PAID = 'PAID';

	/**
	 * Failed
	 *
	 * @var string
	 */
	const FAILED = 'FAILED';

	/**
	 * Pending
	 *
	 * @var string
	 */
	const PENDING = 'PENDING';

	/**
	 * Transform an SumUp status to an Pronamic status
	 *
	 * @param string $status Status.
	 * @return string
	 */
	public static function transform( $status ) {
		switch ( $status ) {
			case self::PAID:
				return Core_Statuses::SUCCESS;

			case self::FAILED:
				return Core_Statuses::FAILURE;

			case self::PENDING:
			default:
				return Core_Statuses::OPEN;
		}
	}
} 
