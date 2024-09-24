<?php

namespace KnitPay\Gateways\Iyzico;

use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: Iyzico Statuses
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 5.6.0
 * @since   5.6.0
 */
class Statuses {
	const FAILURE = 'FAILURE';
	const SUCCESS = 'SUCCESS';

	/**
	 * Transform an Iyzico status to an Knit Pay status
	 *
	 * @param string $status
	 *
	 * @return string
	 */
	public static function transform( $status ) {
		switch ( $status ) {
			case self::SUCCESS:
				$return_status = Core_Statuses::SUCCESS;
				break;
			case self::FAILURE:
				$return_status = Core_Statuses::FAILURE;
				break;
			default:
				$return_status = Core_Statuses::OPEN;
		}
		return $return_status;
	}
}
