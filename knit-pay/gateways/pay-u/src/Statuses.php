<?php

namespace KnitPay\Gateways\PayU;

use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: PayU Statuses
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 5.4.0
 * @since   5.4.0
 */
class Statuses {
	/**
	 * SUCCESSFUL
	 *
	 * @see https://devguide.payu.in/docs/developers-guide/checkout/payu-prebuilt-checkout-overview/prebuilt-checkout-integration/#response-parameters
	 * @var string
	 */
	const SUCCESS = 'success';

	/**
	 * FAILURE.
	 *
	 * @var string
	 */
	const FAILURE = 'failure';

	/**
	 * PENDING.
	 *
	 * @var string
	 */
	const PENDING = 'pending';

	/**
	 * Transform an PayU status to an Knit Pay status
	 *
	 * @param string $status
	 *
	 * @return string
	 */
	public static function transform( $status ) {
		$core_status = null;
		switch ( $status ) {
			case self::SUCCESS:
				$core_status = Core_Statuses::SUCCESS;
				break;

			case self::FAILURE:
				$core_status = Core_Statuses::FAILURE;
				break;

			case self::PENDING:
			default:
				$core_status = Core_Statuses::OPEN;
				break;
		}
		return $core_status;
	}
}
