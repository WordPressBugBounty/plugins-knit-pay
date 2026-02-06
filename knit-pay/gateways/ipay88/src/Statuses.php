<?php

namespace KnitPay\Gateways\IPay88;

use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: iPay88 Statuses
 * Copyright: 2020-2026 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.96.0.0
 * @since   8.96.0.0
 */
class Statuses {
	/**
	 * SUCCESS
	 *
	 * @var string
	 *
	 * @link https://cdnb.nolt.in/cldn/nolt/image/upload/s--rOWjMrts--/v1686135239/cil8iz3cb3da06crdbko.pdf
	 */
	const SUCCESS    = '1';
	const FAILURE    = '0';
	const PENDING    = '6';
	const AUTHORIZED = '20';

	/**
	 * Transform an iPay88 status to an Knit Pay status
	 *
	 * @param string $status
	 *
	 * @return string
	 */
	public static function transform( $status ) {
		switch ( $status ) {
			case self::SUCCESS:
			case self::AUTHORIZED:
				return Core_Statuses::SUCCESS;

			case self::FAILURE:
				return Core_Statuses::FAILURE;

			case self::PENDING:
			default:
				return Core_Statuses::OPEN;
		}
	}
}
