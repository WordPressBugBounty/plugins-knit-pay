<?php

namespace KnitPay\Gateways\Easebuzz;

use KnitPay\Gateways\PaymentMethods as KP_PaymentMethods;

class PaymentMethods extends KP_PaymentMethods {
	const EASEBUZZ = 'easebuzz';

	/**
	 * Transform WordPress payment method to CCAvenue method.
	 *
	 * @param mixed $payment_method Payment method.
	 *
	 * @return string
	 */
	public static function transform( $payment_method ) {
		if ( ! is_scalar( $payment_method ) ) {
			return '';
		}

		switch ( $payment_method ) {
			case self::NET_BANKING:
				return 'NB';
			case self::DEBIT_CARD:
				return 'DC';
			case self::CREDIT_CARD:
				return 'CC';
			case self::UPI:
				return 'UPI';
			default:
				return '';
		}
	}
}
