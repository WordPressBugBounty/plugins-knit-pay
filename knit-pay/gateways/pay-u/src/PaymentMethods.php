<?php

namespace KnitPay\Gateways\PayU;

use KnitPay\Gateways\PaymentMethods as KP_PaymentMethods;

class PaymentMethods extends KP_PaymentMethods {
	const PAY_U = 'pay_u';

	/**
	 * Payments methods map.
	 *
	 * @var array
	 */
	private static $map = [
		self::UPI         => 'UPI',
		self::NET_BANKING => 'netbanking',
		self::DEBIT_CARD  => 'debitcard',
		self::CREDIT_CARD => 'creditcard',
		self::PAYTM       => 'PAYTM', // sub-categorie of cashcard
	];

	/**
	 * Transform WordPress payment method to PayU method.
	 *
	 * @param mixed $payment_method Payment method.
	 *
	 * @return string
	 */
	public static function transform( $payment_method ) {
		if ( ! is_scalar( $payment_method ) ) {
			return null;
		}

		if ( isset( self::$map[ $payment_method ] ) ) {
			return self::$map[ $payment_method ];
		}

		return null;
	}
}
