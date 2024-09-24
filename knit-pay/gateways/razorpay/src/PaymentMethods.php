<?php

namespace KnitPay\Gateways\Razorpay;

use KnitPay\Gateways\PaymentMethods as KP_PaymentMethods;

class PaymentMethods extends KP_PaymentMethods {
	const RAZORPAY = 'razorpay';

	/**
	 * Transform WordPress payment method to Razorpay method.
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
			case self::UPI:
				return 'upi';
			case self::NET_BANKING:
				return 'netbanking';
			case self::DEBIT_CARD:
			case self::CREDIT_CARD:
			case self::AMERICAN_EXPRESS:
				return 'card';
			default:
				return '';
		}
	}
}
