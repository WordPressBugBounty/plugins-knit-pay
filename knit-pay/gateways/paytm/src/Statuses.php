<?php

namespace KnitPay\Gateways\Paytm;

use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: Paytm Statuses
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 4.9.0
 * @since   4.9.0
 */
class Statuses {
	/**
	 * TXN_SUCCESS
	 *
	 * @var string
	 *
	 * @link https://developer.paytm.com/docs/api/v3/transaction-status-api/
	 */
	const TXN_SUCCESS = 'TXN_SUCCESS';

	/**
	 * TXN_FAILURE.
	 *
	 * @var string
	 */
	const TXN_FAILURE = 'TXN_FAILURE';

	const PENDING = 'PENDING';

	/**
	 * Transform an Paytm status to an Knit Pay status
	 *
	 * @param string $status
	 *
	 * @return string
	 */
	public static function transform( $status ) {
		switch ( $status ) {
			case self::TXN_SUCCESS:
				return Core_Statuses::SUCCESS;

			case self::TXN_FAILURE:
				return Core_Statuses::FAILURE;

			case self::PENDING:
			default:
				return Core_Statuses::OPEN;
		}
	}
}
