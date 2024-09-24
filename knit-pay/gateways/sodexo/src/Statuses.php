<?php

namespace KnitPay\Gateways\Sodexo;

use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: Sodexo Statuses
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 1.0.0
 * @since   3.3.0
 */
class Statuses {

	// @see https://docs.zetaapps.in/display/SOZ/API+Reference#APIReference-TransactionStates
	const WAITING_FOR_SOURCE        = 'WAITING_FOR_SOURCE';
	const WAITING_FOR_CONSENT       = 'WAITING_FOR_CONSENT';
	const CANCELLED                 = 'CANCELLED';
	const CANCELLED_BY_USER_AGENT   = 'CANCELLED_BY_USER_AGENT';
	const WAITING_FOR_AUTHORIZATION = 'WAITING_FOR_AUTHORIZATION';
	const AUTHORIZED                = 'AUTHORIZED';
	const CLEARANCE_INITIATED       = 'CLEARANCE_INITIATED';
	const CLEARED                   = 'CLEARED';
	const UNAUTHORIZED              = 'UNAUTHORIZED';
	const REFUND_INITIATED          = 'REFUND_INITIATED';
	const REFUND_FAILED             = 'REFUND_FAILED';
	const REFUND_COMPLETED          = 'REFUND_COMPLETED';
	const REFUND_DROPPED            = 'REFUND_DROPPED';


	/**
	 * Transform an Sodexo payemnt request status to an Knit Pay status
	 *
	 * @param string $status
	 *
	 * @return string
	 */
	public static function transform( $status ) {
		switch ( $status ) {
			case self::AUTHORIZED:
			case self::CLEARANCE_INITIATED:
			case self::CLEARED:
				return Core_Statuses::SUCCESS;

			case self::UNAUTHORIZED:
				return Core_Statuses::FAILURE;

			case self::CANCELLED:
			case self::CANCELLED_BY_USER_AGENT:
				return Core_Statuses::CANCELLED;

			case self::WAITING_FOR_SOURCE:
			case self::WAITING_FOR_CONSENT:
			default:
				return Core_Statuses::OPEN;
		}
	}
}
