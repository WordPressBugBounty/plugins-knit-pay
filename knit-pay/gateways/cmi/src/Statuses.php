<?php

namespace KnitPay\Gateways\CMI;

use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: CMI Statuses
 * Copyright: 2020-2025 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.96.5.0
 * @since   7.71.0.0
 */
class Statuses {
	/**
	 * ACCEPTED
	 *
	 * @var string
	 */
	const ACCEPTED = '00';

	/**
	 * FAILED.
	 *
	 * @var string
	 */
	const FAILED = '99';

	/**
	 * Transform an CMI status to an Knit Pay status
	 *
	 * @param string $status
	 *
	 * @return string
	 */
	public static function transform( $status ) {
		switch ( $status ) {
			case self::ACCEPTED:
				return Core_Statuses::SUCCESS;

			case self::FAILED:
				return Core_Statuses::FAILURE;

			default:
				return Core_Statuses::OPEN;
		}
	}
}
