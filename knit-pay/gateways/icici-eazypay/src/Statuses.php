<?php

namespace KnitPay\Gateways\IciciEazypay;

use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: ICICI Eazypay Statuses
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 6.62.0.0
 * @since   6.62.0.0
 */
class Statuses {

	const RECONCILIATION_IN_PROGRESS = 'RIP';
	
	const SETTLEMENT_IN_PROGRESS = 'SIP';
	
	const SUCCESS = 'Success';
	
	const NOT_INITIATED = 'NotInitiated';
	
	const TRANSACTION_INITIATED = 'Transaction Initiated';

	const FAILED = 'FAILED';
	
	const TIMEOUT = 'TIMEOUT';
	
	const TRANSACTION_EXPIRED = 'Transaction Expired';

	/**
	 * Transform an ICICI Eazypay status to an Knit Pay status
	 *
	 * @param string $status
	 *
	 * @return string
	 */
	public static function transform( $status ) {
		switch ( $status ) {
			case self::SUCCESS:
			case self::RECONCILIATION_IN_PROGRESS:
			case self::SETTLEMENT_IN_PROGRESS:
				return Core_Statuses::SUCCESS;

			case self::FAILED:
				return Core_Statuses::FAILURE;

			case self::TIMEOUT:
			case self::TRANSACTION_EXPIRED:
				return Core_Statuses::EXPIRED;

			case self::TRANSACTION_INITIATED:
			case self::NOT_INITIATED:
			default:
				return Core_Statuses::OPEN;
		}
	}

	public static function transform_response_code( $code ) {
		$status = '';

		switch ( $code ) {
			case 'E00335': // Transaction Cancelled By User.
			case 'E0803': // Canceled by user.
				$status = Core_Statuses::CANCELLED;
				break;

			case 'E005': // Unauthorized Return URL.
			case 'E00310': // Mandatory value mobile number in wrong format.
			case 'E00316': // Optional value mobile number in wrong format.
				$status = Core_Statuses::FAILURE;
				break;
		}

		return $status;
	}
}
