<?php

namespace KnitPay\Gateways\EBS;

use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: EBS Statuses
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 1.0.0
 * @since   3.0.0
 */
class Statuses {
	/**
	 * SUCCESS
	 *
	 * @var string
	 */
	const SUCCESS = '0';

	/**
	 * Transform an EBS status to an Knit Pay status
	 *
	 * @param string $response_code
	 * @param string $is_flagged
	 *
	 * @return string
	 */
	public static function transform( $response_code, $is_flagged ) {
		if ( ! empty( $response_code ) ) {
			return Core_Statuses::FAILURE;
		}

		if ( 'YES' === $is_flagged && '0' === $response_code ) {
			return Core_Statuses::ON_HOLD;
		}

		if ( '0' === $response_code ) {
			return Core_Statuses::SUCCESS;
		}

		return Core_Statuses::OPEN;
	}
}
