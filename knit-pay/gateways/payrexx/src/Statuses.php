<?php

namespace KnitPay\Gateways\Payrexx;

use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: Payrexx Statuses
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.82.0.0
 * @since   8.82.0.0
 */
class Statuses {
	/**
	 * WAITING
	 *
	 * @var string
	 *
	 * @link https://developers.payrexx.com/reference/retrieve-a-gateway
	 */
	const WAITING = 'waiting';
	
	const CONFIRMED = 'confirmed';
	
	const AUTHORIZED = 'authorized';
	
	const RESERVED = 'reserved';
	
	/**
	 * Transform an Payrexx status to an Knit Pay status
	 *
	 * @param string $status
	 *
	 * @return string
	 */
	public static function transform( $status ) {
		switch ( $status ) {
			case self::CONFIRMED:
			case self::AUTHORIZED:
				return Core_Statuses::SUCCESS;
				
			case self::RESERVED:
				return Core_Statuses::ON_HOLD;
				
			case self::WAITING:
			default:
				return Core_Statuses::OPEN;
		}
	}
}
