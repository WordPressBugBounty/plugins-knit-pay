<?php

namespace KnitPay\Gateways\Instamojo;

use KnitPay\Gateways\PaymentMethods as KP_PaymentMethods;

class PaymentMethods extends KP_PaymentMethods {
	const INSTAMOJO = 'instamojo';

	/**
	 * Transform WordPress payment method to Instamojo method.
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
				return 'Net Banking';
			case self::DEBIT_CARD:
				return 'Debit Card';
			case self::CREDIT_CARD:
				return 'Credit Card';
			case self::UPI:
				// Don't return "UPI" if device is not Mobile, else QR will not be show by default.
				if ( wp_is_mobile() ) {
					return 'UPI';
				}
			default:
				return '';
		}
	}
}
