<?php

namespace KnitPay\Gateways\SabPaisa;

use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: Sab Paisa Statuses
 * Copyright: 2020-2025 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.95.0.0
 * @since   8.95.0.0
 */
class Statuses {
	/**
	 * INITIATED
	 *
	 * @var string
	 *
	 * @link https://sabpaisa.in/wp-content/uploads/2024/05/Transaction-Enquiry-API.pdf
	 */
	const INITIATED = 'INITIATED';
	const SUCCESS   = 'SUCCESS';
	const FAILED    = 'FAILED';
	const ABORTED   = 'ABORTED';

	/**
	 * Transform an Sab Paisa status to an Knit Pay status
	 *
	 * @param string $status
	 *
	 * @return string
	 */
	public static function transform( $status ) {
		switch ( $status ) {
			case self::SUCCESS:
				return Core_Statuses::SUCCESS;

			case self::FAILED:
				return Core_Statuses::FAILURE;

			case self::ABORTED:
				return Core_Statuses::CANCELLED;

			case self::INITIATED:
			default:
				return Core_Statuses::OPEN;
		}
	}
}
