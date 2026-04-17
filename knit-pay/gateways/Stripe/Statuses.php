<?php

namespace KnitPay\Gateways\Stripe;

use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: Stripe Statuses
 * Copyright: 2020-2026 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.96.11.0
 * @since   3.1.0
 */
class Statuses {
	/**
	 * Stripe status constants.
	 *
	 * @link https://stripe.com/docs/api/payment_intents/object
	 */
	const REQUIRES_PAYMENT_METHOD = 'requires_payment_method';
	const REQUIRES_CONFIRMATION   = 'requires_confirmation';
	const REQUIRES_ACTION         = 'requires_action';
	const PROCESSING              = 'processing';
	const SUCCEEDED               = 'succeeded';
	const CANCELED                = 'canceled';

	/**
	 * Transform an Stripe status to an Knit Pay status
	 *
	 * @param string $status
	 *
	 * @return string
	 */
	public static function transform( $status ) {
		switch ( $status ) {
			case self::SUCCEEDED:
				return Core_Statuses::SUCCESS;

			case self::CANCELED:
				return Core_Statuses::CANCELLED;

			case self::PROCESSING:
				return Core_Statuses::ON_HOLD;

			case self::REQUIRES_PAYMENT_METHOD:
			case self::REQUIRES_CONFIRMATION:
			case self::REQUIRES_ACTION:
			default:
				return Core_Statuses::OPEN;
		}
	}
}
