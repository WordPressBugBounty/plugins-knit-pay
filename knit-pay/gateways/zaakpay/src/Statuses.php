<?php

namespace KnitPay\Gateways\Zaakpay;

use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: Zaakpay Statuses
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 6.66.0.0
 * @since   6.66.0.0
 */
class Statuses {
	
	const PENDING = '2';
	
	const SUCCESS = '0';
	
	const FAILURE = '1';
	
	/**
	 * Transform an Zaakpay status to an Knit Pay status
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
				
			case self::PENDING:
			default:
				return Core_Statuses::OPEN;
		}
	}
}
