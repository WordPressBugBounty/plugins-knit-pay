<?php

namespace KnitPay\Gateways\CCAvenue;

use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: CCAvenue Statuses
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 1.0.0
 * @since   2.3.0
 */
class Statuses {
	/**
	 * SUCCESS
	 *
	 * @var string
	 *
	 * @link https://dashboard.ccavenue.com/resources/integrationKit.do#response_parameters_doc
	 */
	const SUCCESS = 'Success';

	/**
	 * FAILURE.
	 *
	 * @var string
	 *
	 * @link https://dashboard.ccavenue.com/resources/integrationKit.do#response_parameters_doc
	 */
	const FAILURE = 'Failure';

	/**
	 *
	 * @var string
	 *
	 * @link https://dashboard.ccavenue.com/resources/integrationKit.do#status_api_calls_doc
	 */
	const ABORTED        = 'Aborted';
	const INVALID        = 'Invalid';
	const INITIATED      = 'Initiated';
	const SUCCESSFUL     = 'Successful';
	const AUTO_CANCELLED = 'Auto-Cancelled';
	const AUTO_REVERSED  = 'Auto-Reversed';
	const AWAITED        = 'Awaited';
	const CANCELLED      = 'Cancelled';
	const FRAUD          = 'Fraud';
	const REFUNDED       = 'Refunded';
	const SHIPPED        = 'Shipped';
	const SYSTEM_REFUND  = 'System refund';
	const TIMEOUT        = 'Timeout';
	const UNSUCCESSFUL   = 'Unsuccessful';

	/**
	 * Transform an CCAvenue status to an Knit Pay status
	 *
	 * @param string $status
	 *
	 * @return string
	 */
	public static function transform( $status ) {
		switch ( $status ) {
			case self::SUCCESS:
			case self::SUCCESSFUL:
			case self::SHIPPED:
				return Core_Statuses::SUCCESS;

			case self::FAILURE:
			case self::INVALID:
			case self::FRAUD:
			case self::UNSUCCESSFUL:
				return Core_Statuses::FAILURE;

			case self::ABORTED:
			case self::AUTO_CANCELLED:
				return Core_Statuses::CANCELLED;

			case self::TIMEOUT:
				return Core_Statuses::EXPIRED;

			case self::INITIATED:
			case self::AWAITED:
			default:
				return Core_Statuses::OPEN;
		}
	}
}
