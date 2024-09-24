<?php

namespace KnitPay\Gateways\Cashfree;

use KnitPay\Gateways\PaymentMethods as KP_PaymentMethods;

class PaymentMethods extends KP_PaymentMethods {
	const CASHFREE = 'cashfree';

	/**
	 * Transform WordPress payment method to Cashfree method.
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
				return 'nb';
			case self::DEBIT_CARD:
				return 'dc';
			case self::CREDIT_CARD:
				return 'cc';
			case self::UPI:
				return 'upi';
			default:
				return '';
		}
	}
}
