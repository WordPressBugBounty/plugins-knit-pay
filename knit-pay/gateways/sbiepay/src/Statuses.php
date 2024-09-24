<?php

namespace KnitPay\Gateways\SBIePay;

use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: SBIePay Statuses
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 5.7.0
 * @since   5.7.0
 */
class Statuses {

	const SUCCESS          = 'SUCCESS';
	const FAIL             = 'FAIL';
	const PENDING          = 'PENDING';
	const ABORT            = 'ABORT';
	const IN_PROGRESS      = 'IN PROGRESS';
	const INPROGRESS       = 'INPROGRESS';
	const NO_RECORDS_FOUND = 'No Records Found';
	const BOOKED           = 'BOOKED';
	const REFUND           = 'REFUND';
	const CANCELLED        = 'CANCELLED';
	const EXPIRED          = 'EXPIRED';

	/**
	 * Transform an SBIePay status to an Knit Pay status
	 *
	 * @param string $status
	 *
	 * @return string
	 */
	public static function transform( $status ) {
		$return_status = '';
		switch ( $status ) {
			case self::SUCCESS:
				$return_status = Core_Statuses::SUCCESS;
				break;

			case self::FAIL:
			case self::ABORT:
			case self::NO_RECORDS_FOUND:
				$return_status = Core_Statuses::FAILURE;
				break;

			case self::CANCELLED:
				$return_status = Core_Statuses::CANCELLED;
				break;

			case self::EXPIRED:
				$return_status = Core_Statuses::EXPIRED;
				break;

			case self::PENDING:
			case self::IN_PROGRESS:
			case self::INPROGRESS:
			case self::BOOKED:
			default:
				$return_status = Core_Statuses::OPEN;
				break;
		}

		return $return_status;
	}
}
