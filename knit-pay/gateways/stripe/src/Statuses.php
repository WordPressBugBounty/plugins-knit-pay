<?php

namespace KnitPay\Gateways\Stripe;

use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: Stripe Statuses
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 1.0.0
 * @since   3.1.0
 */
class Statuses {
	/**
	 * SUCCEEDED
	 *
	 * @var string
	 *
	 * @link https://stripe.com/docs/api/payment_intents/object
	 */
	const SUCCEEDED = 'succeeded';

	/**
	 * CANCELED.
	 *
	 * @var string
	 *
	 * @link https://stripe.com/docs/api/payment_intents/object
	 */
	const CANCELED = 'canceled';

	/**
	 * PROCESSING.
	 *
	 * @var string
	 *
	 * @link https://stripe.com/docs/api/payment_intents/object
	 */
	const PROCESSING = 'processing';

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
			default:
				return Core_Statuses::OPEN;
		}
	}
}
