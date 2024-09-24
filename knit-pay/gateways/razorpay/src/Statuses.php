<?php

namespace KnitPay\Gateways\Razorpay;

use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: Razorpay Statuses
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 1.0.0
 * @since   1.7.0
 */
class Statuses {
	/**
	 * PAID
	 *
	 * @var string
	 *
	 * @link https://razorpay.com/docs/payment-links/#payment-links-life-cycle
	 */
	const PAID = 'paid';

	/**
	 * CANCELLED.
	 *
	 * @var string
	 *
	 * @link https://razorpay.com/docs/payment-links/#payment-links-life-cycle
	 */
	const CANCELLED = 'cancelled';

	/**
	 * ISSUED.
	 *
	 * @var string
	 *
	 * @link https://razorpay.com/docs/payment-links/#payment-links-life-cycle
	 */
	const ISSUED = 'issued';

	/**
	 * EXPIRED.
	 *
	 * @var string
	 *
	 * @link https://razorpay.com/docs/api/invoices/#invoices-entity
	 */
	const EXPIRED = 'expired';

	/**
	 * CREATED.
	 *
	 * @var string
	 *
	 * @link https://razorpay.com/docs/api/payments/#payment-entity
	 */
	const CREATED = 'created';

	/**
	 * AUTHORIZED.
	 *
	 * @var string
	 *
	 * @link https://razorpay.com/docs/api/payments/#payment-entity
	 */
	const AUTHORIZED = 'authorized';

	/**
	 * CAPTURED.
	 *
	 * @var string
	 *
	 * @link https://razorpay.com/docs/api/payments/#payment-entity
	 */
	const CAPTURED = 'captured';

	/**
	 * FAILED.
	 *
	 * @var string
	 *
	 * @link https://razorpay.com/docs/api/payments/#payment-entity
	 */
	const FAILED = 'failed';

	/**
	 * FAILED.
	 *
	 * @var string
	 *
	 * @link https://razorpay.com/docs/api/payments/#payment-entity
	 */
	const REFUNDED = 'refunded';


	/**
	 * FAILED.
	 *
	 * @var string
	 *
	 * @link https://razorpay.com/docs/api/payments/subscriptions#subscriptions-entity
	 */
	const AUTHENTICATED = 'authenticated';


	/**
	 * Transform an Razorpay status to an Knit Pay status
	 *
	 * @param string $status
	 *
	 * @return string
	 */
	public static function transform( $status ) {
		switch ( $status ) {
			case self::PAID:
			case self::AUTHORIZED:
			case self::CAPTURED:
				return Core_Statuses::SUCCESS;

			case self::CANCELLED:
				return Core_Statuses::CANCELLED;

			case self::FAILED:
				return Core_Statuses::FAILURE;

			case self::ISSUED:
			case self::CREATED:
			default:
				return Core_Statuses::OPEN;
		}
	}

	/**
	 * Transform an Razorpay Subscription status to an Knit Pay status
	 *
	 * @param string $status
	 *
	 * @return string
	 */
	public static function transform_subscription_status( $status ) {
		switch ( $status ) {
			case self::AUTHENTICATED:
				return Core_Statuses::SUCCESS;
		}
	}
}
