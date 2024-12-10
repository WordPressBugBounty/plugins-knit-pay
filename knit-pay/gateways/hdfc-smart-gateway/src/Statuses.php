<?php

namespace KnitPay\Gateways\HdfcSmartGateway;

use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: HDFC Smart Gateway Statuses
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.93.0.0
 * @since   8.93.0.0
 */
class Statuses {
	/**
	 * NEW
	 *
	 * @see https://smartgateway.hdfcbank.com/docs/hdfc-resources/docs/common-resources/transaction-status
	 * @var string
	 */
	const NEW = 'NEW';

	/**
	 * PENDING_VBV.
	 *
	 * @var string
	 */
	const PENDING_VBV = 'PENDING_VBV';

	/**
	 * CHARGED.
	 *
	 * @var string
	 */
	const CHARGED = 'CHARGED';

		/**
	 * AUTHENTICATION_FAILED.
	 *
	 * @var string
	 */
	const AUTHENTICATION_FAILED = 'AUTHENTICATION_FAILED';

		/**
	 * AUTHORIZATION_FAILED.
	 *
	 * @var string
	 */
	const AUTHORIZATION_FAILED = 'AUTHORIZATION_FAILED';

		/**
	 * AUTHORIZING.
	 *
	 * @var string
	 */
	const AUTHORIZING = 'AUTHORIZING';

		/**
	 * STARTED.
	 *
	 * @var string
	 */
	const STARTED = 'STARTED';

	/**
	 * Transform an HDFC Smart Gateway status to an Knit Pay status
	 *
	 * @param string $status
	 *
	 * @return string
	 */
	public static function transform( $status ) {
		$core_status = null;
		switch ( $status ) {
			case self::CHARGED:
				$core_status = Core_Statuses::SUCCESS;
				break;

			case self::AUTHENTICATION_FAILED:
			case self::AUTHORIZATION_FAILED:
				$core_status = Core_Statuses::FAILURE;
				break;

			case self::NEW:
			case self::PENDING_VBV:
			case self::AUTHORIZING:
			case self::STARTED:
			default:
				$core_status = Core_Statuses::OPEN;
				break;
		}
		return $core_status;
	}
}
