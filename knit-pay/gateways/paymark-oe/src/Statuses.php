<?php

namespace KnitPay\Gateways\PaymarkOE;

use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: Paymark OE Statuses
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 5.2.0
 * @since   5.2.0
 */
class Statuses {
	/**
	 * NEW
	 *
	 * @var string
	 *
	 * @link http://docs.dev.paymark.nz/oe/#header-payment-status-codes
	 */
	const NEW = 'NEW';

	/**
	 * SUBMITTED
	 *
	 * @var string
	 *
	 * @link http://docs.dev.paymark.nz/oe/#header-payment-status-codes
	 */
	const SUBMITTED = 'SUBMITTED';

	/**
	 * AUTHORISED
	 *
	 * @var string
	 *
	 * @link http://docs.dev.paymark.nz/oe/#header-payment-status-codes
	 */
	const AUTHORISED = 'AUTHORISED';

	/**
	 * DECLINED
	 *
	 * @var string
	 *
	 * @link http://docs.dev.paymark.nz/oe/#header-payment-status-codes
	 */
	const DECLINED = 'DECLINED';

	/**
	 * EXPIRED
	 *
	 * @var string
	 *
	 * @link http://docs.dev.paymark.nz/oe/#header-payment-status-codes
	 */
	const EXPIRED = 'EXPIRED';

	/**
	 * REFUNDED
	 *
	 * @var string
	 *
	 * @link http://docs.dev.paymark.nz/oe/#header-payment-status-codes
	 */
	const REFUNDED = 'REFUNDED';

	/**
	 * ERROR
	 *
	 * @var string
	 *
	 * @link http://docs.dev.paymark.nz/oe/#header-payment-status-codes
	 */
	const ERROR = 'ERROR';




	/**
	 * Transform an Paymark OE status to an Knit Pay status
	 *
	 * @param string $status
	 *
	 * @return string
	 */
	public static function transform( $status ) {
		switch ( $status ) {
			case self::AUTHORISED:
				return Core_Statuses::SUCCESS;

			case self::DECLINED:
			case self::ERROR:
				return Core_Statuses::FAILURE;

			case self::EXPIRED:
				return Core_Statuses::EXPIRED;

			case self::NEW:
			case self::SUBMITTED:
			default:
				return Core_Statuses::OPEN;
		}
	}
}
