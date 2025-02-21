<?php

namespace KnitPay\Gateways\Paypal;

use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: PayPal Statuses
 * Copyright: 2020-2025 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.94.0.0
 * @since   8.94.0.0
 */
class Statuses {
	/**
	 * CREATED
	 *
	 * @see https://developer.paypal.com/docs/api/orders/v2/#orders_get
	 * @var string
	 */
	const CREATED = 'CREATED';

	/**
	 * SAVED.
	 *
	 * @var string
	 */
	const SAVED = 'SAVED';

	/**
	 * APPROVED.
	 *
	 * @var string
	 */
	const APPROVED = 'APPROVED';

	/**
	 * COMPLETED.
	 *
	 * @var string
	 */
	const COMPLETED = 'COMPLETED';

	/**
	 * VOIDED.
	 *
	 * @var string
	 */
	const VOIDED = 'VOIDED';

	/**
	 * PAYER_ACTION_REQUIRED.
	 *
	 * @var string
	 */
	const PAYER_ACTION_REQUIRED = 'PAYER_ACTION_REQUIRED';

	const DECLINED = 'DECLINED';
	const PENDING  = 'PENDING';
	const FAILED   = 'FAILED';

	/**
	 * Transform a PayPal status to a Knit Pay status
	 *
	 * @param string $status
	 *
	 * @return string
	 */
	public static function transform( $status ) {
		$core_status = null;
		switch ( $status ) {
			case self::COMPLETED:
				$core_status = Core_Statuses::SUCCESS;
				break;

			case self::VOIDED:
			case self::DECLINED:
			case self::FAILED:
				$core_status = Core_Statuses::FAILURE;
				break;

			case self::PENDING:
			case self::APPROVED:
			case self::CREATED:
			case self::SAVED:
			case self::PAYER_ACTION_REQUIRED:
			default:
				$core_status = Core_Statuses::OPEN;
				break;
		}
		return $core_status;
	}
}
