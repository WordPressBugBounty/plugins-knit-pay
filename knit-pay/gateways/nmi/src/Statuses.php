<?php

namespace KnitPay\Gateways\NMI;

use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: NMI Statuses
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.83.0.0
 * @since   8.83.0.0
 */
class Statuses {
	const APPROVED = '1';
	const DECLINED = '2';
	const ERROR    = '3';

	/**
	 * Transform an NMI status to an Knit Pay status
	 *
	 * @param string $status
	 *
	 * @return string
	 */
	public static function transform( $status ) {
		switch ( $status ) {
			case self::APPROVED:
				return Core_Statuses::SUCCESS;

			case self::DECLINED:
			case self::ERROR:
				return Core_Statuses::FAILURE;

			default:
				return Core_Statuses::OPEN;
		}
	}
}
