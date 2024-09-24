<?php

namespace KnitPay\Gateways\Instamojo;

use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: Instamojo Statuses
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 1.0.0
 * @since   1.0.0
 */
class Statuses {

	const PENDING_STRING   = 'Pending';
	const SENT_STRING      = 'SENT';
	const FAILED_STRING    = 'Failed';
	const COMPLETED_STRING = 'Completed';

	/**
	 * Transform an Instamojo payemnt request status to an Knit Pay status
	 *
	 * @param string $status
	 *
	 * @return string
	 */
	public static function transform( $status ) {
		switch ( $status ) {
			case self::COMPLETED_STRING:
				return Core_Statuses::SUCCESS;

			case self::FAILED_STRING:
				return Core_Statuses::FAILURE;

			case self::PENDING_STRING:
			case self::SENT_STRING:
			default:
				return Core_Statuses::OPEN;
		}
	}

	/**
	 * Transform an Instamojo payment status to an Knit Pay status
	 *
	 * @param bool $status
	 *
	 * @return string
	 */
	public static function transform_payment_status( $status ) {
		switch ( $status ) {
			case true:
				return Core_Statuses::SUCCESS;

			case false:
				return Core_Statuses::FAILURE;

			default:
				return Core_Statuses::OPEN;
		}
	}
}
