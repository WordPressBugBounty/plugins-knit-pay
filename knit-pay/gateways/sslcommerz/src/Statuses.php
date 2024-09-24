<?php

namespace KnitPay\Gateways\SSLCommerz;

use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: SSLCommerz Statuses
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.80.0.0
 * @since   8.80.0.0
 */
class Statuses {
	/**
	 * VALID
	 *
	 * @var string
	 *
	 * @link https://developer.sslcommerz.com/doc/v4/#ti-returned-parameters
	 */
	const VALID = 'VALID';

	/**
	 * VALIDATED
	 *
	 * @var string
	 *
	 * @link https://developer.sslcommerz.com/doc/v4/#ti-returned-parameters
	 */
	const VALIDATED = 'VALIDATED';

	/**
	 * PENDING
	 *
	 * @var string
	 *
	 * @link https://developer.sslcommerz.com/doc/v4/#ti-returned-parameters
	 */
	const PENDING = 'PENDING';

	const PROCESSING = 'PROCESSING';

	/**
	 * FAILED
	 *
	 * @var string
	 *
	 * @link https://developer.sslcommerz.com/doc/v4/#ti-returned-parameters
	 */
	const FAILED = 'FAILED';

	const CANCELLED = 'CANCELLED';

	/**
	 * Transform an SSLCommerz status to an Knit Pay status
	 *
	 * @param string $status
	 *
	 * @return string
	 */
	public static function transform( $status ) {
		switch ( $status ) {
			case self::VALID:
			case self::VALIDATED:
				return Core_Statuses::SUCCESS;

			case self::FAILED:
				return Core_Statuses::FAILURE;

			case self::CANCELLED:
				return Core_Statuses::CANCELLED;

			case self::PENDING:
			case self::PROCESSING:
			default:
				return Core_Statuses::OPEN;
		}
	}
}
