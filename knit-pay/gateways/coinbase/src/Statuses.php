<?php

namespace KnitPay\Gateways\Coinbase;

use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: Coinbase Commerce Statuses
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.77.0.0
 * @since   8.77.0.0
 */
class Statuses {

	// @see: https://docs.cloud.coinbase.com/commerce/docs/payment-status
	const NEW = 'NEW';

	const PENDING = 'PENDING';

	const COMPLETED = 'COMPLETED';
	
	const EXPIRED = 'EXPIRED';
	
	const UNRESOLVED = 'UNRESOLVED';
	
	const RESOLVED = 'RESOLVED';
	
	const CANCELLED = 'CANCELED';
	
	const PENDING_REFUND = 'PENDING REFUND';
	
	const REFUNDED = 'REFUNDED';

	/**
	 * Transform an Coinbase Commerce status to an Knit Pay status
	 *
	 * @param string $status
	 *
	 * @return string
	 */
	public static function transform( $last_update ) {
		$return_status = Core_Statuses::OPEN;
		switch ( $last_update['status'] ) {
			case self::COMPLETED:
				$return_status = Core_Statuses::SUCCESS;
				break;

			case self::CANCELLED:
				$return_status = Core_Statuses::CANCELLED;
				break;
				
			case self::EXPIRED:
				$return_status = Core_Statuses::EXPIRED;
				break;
				
			case self::UNRESOLVED:
				if ( 'OVERPAID' === $last_update['context'] ) {
					$return_status = Core_Statuses::SUCCESS;
					break;
				} else {
					$return_status = Core_Statuses::FAILURE;
				}
				break;

			case self::NEW:
			case self::PENDING:
			case self::RESOLVED:// We don't know the resolution, so don't change order status.
			default:
				$return_status = Core_Statuses::OPEN;
				break;
		}
		return $return_status;
	}
}
