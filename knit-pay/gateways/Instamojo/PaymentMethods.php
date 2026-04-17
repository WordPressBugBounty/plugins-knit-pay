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
			case self::CREDIT_CARD:
			case self::CARD:
				return 'Credit / Debit Cards';
			case self::UPI:
				if ( wp_is_mobile() ) {
					return 'QR_INIT'; // For UPI Intent and UPI Collect.
				}
				return ''; // Issue in Instamojo, if UPI is passed QR code is not displayed.
			case self::UPI_COLLECT:
				if ( wp_is_mobile() ) {
					return 'QR_INIT'; // For UPI Intent and UPI Collect.
				}
				return 'UPI'; // For only UPI Collect on other devices.
			default:
				return '';
		}
	}
}
