<?php

namespace KnitPay\Gateways\PayUmoney;

use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: PayUmoney Statuses
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 1.9.1
 * @since   1.0.0
 */
class Statuses {
	/**
	 * SUCCESSFUL
	 *
	 * @var string
	 */
	const SUCCESSFUL = 'success';

	/**
	 * FAILURE.
	 *
	 * @var string
	 */
	const FAILURE = 'failure';

	/**
	 * FAILED.
	 *
	 * @var string
	 */
	const FAILED = 'failed';

	/**
	 * CANCEL.
	 *
	 * @var string
	 */
	const CANCEL = 'CANCEL';

	/**
	 * CANCEL.
	 *
	 * @var string
	 */
	const USER_CANCELED = 'User Cancelled';

	/**
	 * API Payment Statuses
	 *
	 * @link https://www.payumoney.com/dev-guide/apireference.html#operation/chkMerchantTxnStatusUsingPOST
	 */
	const CANCELLED_BY_USER = 'CancelledByUser';

	const MONEY_WITH_PAYUMONEY = 'Money with Payumoney';

	const NOT_STARTED = 'not started';

	const INITIATED = 'Initiated';

	const FULL_REFUNDED = 'Full Refunded';

	const MONEY_SETTLED = 'Money Settled';

	const FAILED_F_CAPITAL = 'Failed';

	/**
	 * Transform an PayUmoney status to an Knit Pay status
	 *
	 * @param string $status
	 *
	 * @return string
	 */
	public static function transform( $status ) {
		$core_status = null;
		switch ( $status ) {
			case self::SUCCESSFUL:
			case self::MONEY_WITH_PAYUMONEY:
			case self::MONEY_SETTLED:
				$core_status = Core_Statuses::SUCCESS;
				break;

			case self::FAILED:
			case self::FAILURE:
			case self::FAILED_F_CAPITAL:
				$core_status = Core_Statuses::FAILURE;
				break;

			case self::USER_CANCELED:
			case self::CANCEL:
			case self::CANCELLED_BY_USER:
				$core_status = Core_Statuses::CANCELLED;
				break;

			case self::FULL_REFUNDED:
				$core_status = Core_Statuses::REFUNDED;
				break;

			case self::NOT_STARTED:
			case self::INITIATED:
			default:
				$core_status = Core_Statuses::OPEN;
				break;
		}
		return $core_status;
	}
}
