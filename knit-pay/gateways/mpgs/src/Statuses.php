<?php

namespace KnitPay\Gateways\MPGS;

use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: MPGS Statuses
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.81.0.0
 * @since   8.81.0.0
 */
class Statuses {
	const AUTHENTICATED               = 'AUTHENTICATED';
	const AUTHENTICATION_INITIATED    = 'AUTHENTICATION_INITIATED';
	const AUTHENTICATION_NOT_NEEDED   = 'AUTHENTICATION_NOT_NEEDED';
	const AUTHENTICATION_UNSUCCESSFUL = 'AUTHENTICATION_UNSUCCESSFUL';
	const AUTHORIZED                  = 'AUTHORIZED';
	const CANCELLED                   = 'CANCELLED';
	const CAPTURED                    = 'CAPTURED';
	const CHARGEBACK_PROCESSED        = 'CHARGEBACK_PROCESSED';
	const DISBURSED                   = 'DISBURSED';
	const DISPUTED                    = 'DISPUTED';
	const EXCESSIVELY_REFUNDED        = 'EXCESSIVELY_REFUNDED';
	const FAILED                      = 'FAILED';
	const FUNDING                     = 'FUNDING';
	const INITIATED                   = 'INITIATED';
	const PARTIALLY_CAPTURED          = 'PARTIALLY_CAPTURED';
	const PARTIALLY_REFUNDED          = 'PARTIALLY_REFUNDED';
	const REFUNDED                    = 'REFUNDED';
	const REFUND_REQUESTED            = 'REFUND_REQUESTED';
	const VERIFIED                    = 'VERIFIED';

	/**
	 * Transform an MPGS status to an Knit Pay status
	 *
	 * @param string $status
	 *
	 * @return string
	 */
	public static function transform( $status ) {
		switch ( $status ) {
			case self::CAPTURED:
				return Core_Statuses::SUCCESS;

			case self::CANCELLED:
				return Core_Statuses::CANCELLED;

			case self::FAILED:
				return Core_Statuses::FAILURE;
				
			default:
				return Core_Statuses::OPEN;
		}
	}
}
