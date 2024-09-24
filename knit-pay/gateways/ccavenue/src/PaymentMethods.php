<?php

namespace KnitPay\Gateways\CCAvenue;

use KnitPay\Gateways\PaymentMethods as KP_PaymentMethods;

class PaymentMethods extends KP_PaymentMethods {
	const CCAVENUE = 'ccavenue';

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
				return 'OPTNBK';
			case self::DEBIT_CARD:
				return 'OPTDBCRD';
			case self::CREDIT_CARD:
				return 'OPTCRDC';
			case self::UPI:
				return 'OPTUPI';
			default:
				return ''; // TODO OPTCASHC, OPTMOBP, OPTEMI.
		}
	}
}
