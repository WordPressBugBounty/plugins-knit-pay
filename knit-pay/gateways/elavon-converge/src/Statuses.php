<?php

namespace KnitPay\Gateways\ElavonConverge;

use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: Elavon Converge Statuses
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 1.0.0
 * @since   4.3.0
 */
class Statuses {

	// @see https://developer.elavon.com/na/docs/converge/1.0.0/integration-guide/transaction_types/end_of_day/transaction_query
	const PENDED     = 'PEN';
	const UNPENDED   = 'OPN';
	const REVIEW     = 'REV';
	const SETTLED    = 'STL';
	const FAILED_PST = 'PST';
	const FAILED_FPR = 'FPR';
	const FAILED_PRE = 'PRE';


	/**
	 * Transform an Elavon Converge payemnt request status to an Knit Pay status
	 *
	 * @param string $status
	 *
	 * @return string
	 */
	public static function transform( $status ) {
		switch ( $status ) {
			case self::SETTLED:
			case self::UNPENDED:
				return Core_Statuses::SUCCESS;

			case self::FAILED_PST:
			case self::FAILED_FPR:
			case self::FAILED_PRE:
				return Core_Statuses::FAILURE;

			case self::REVIEW:
				return Core_Statuses::ON_HOLD;

			case self::PENDED:
			default:
				return Core_Statuses::OPEN;
		}
	}
}
