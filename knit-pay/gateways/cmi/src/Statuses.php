<?php

namespace KnitPay\Gateways\CMI;

use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: CMI Statuses
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 7.71.0.0
 * @since   7.71.0.0
 */
class Statuses {
	/**
	 * APPROVED
	 *
	 * @var string
	 */
	const APPROVED = 'Approved';

	/**
	 * ERROR.
	 *
	 * @var string
	 */
	const ERROR = 'Error';

	/**
	 * Transform an CMI status to an Knit Pay status
	 *
	 * @param string $status
	 *
	 * @return string
	 */
	public static function transform( $status ) {
		switch ( $status ) {
			case self::APPROVED:
				return Core_Statuses::SUCCESS;

			case self::ERROR:
				return Core_Statuses::FAILURE;

			default:
				return Core_Statuses::OPEN;
		}
	}
}
