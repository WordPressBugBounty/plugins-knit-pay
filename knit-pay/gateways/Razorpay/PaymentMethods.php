<?php

namespace KnitPay\Gateways\Razorpay;

use KnitPay\Gateways\PaymentMethods as KP_PaymentMethods;

class PaymentMethods extends KP_PaymentMethods {
	const RAZORPAY = 'razorpay';

	/**
	 * Transform WordPress payment method to Razorpay prefill method.
	 *
	 * @param mixed $payment_method Payment method.
	 *
	 * @return string
	 */
	public static function prefill_method( $payment_method ) {
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
			case self::CARD:
			case self::AMERICAN_EXPRESS:
				return 'card';
			case self::FPX:
				return 'fpx';
			default:
				return '';
		}
	}

	/**
	 * Show and Hide Razorpay payment methods.
	 *
	 * @param mixed $payment_method Payment method.
	 *
	 * @return array
	 */
	public static function get_methods( $payment_method ) {
		if ( ! is_scalar( $payment_method ) ) {
			return [];
		}

		// @see: https://razorpay.com/docs/payments/payment-gateway/web-integration/standard/integration-steps/#2-test-integration
		$methods = [
			'card'          => false,
			'netbanking'    => false,
			'upi'           => false,
			'emi'           => false,
			'wallet'        => false,
			'cardless_emi'  => false,
			'bank_transfer' => false,
			'emandate'      => false,
			'paylater'      => false,
			'fpx'           => false,
		];

		switch ( $payment_method ) {
			case self::UPI:
				$methods['upi'] = true;
				break;
			case self::NET_BANKING:
				$methods['netbanking'] = true;
				break;
			case self::DEBIT_CARD:
				$methods['card'] = true;
				$methods['emi']  = true;
				break;
			case self::CREDIT_CARD:
				$methods['card'] = true;
				$methods['emi']  = true;
				break;
			case self::CARD:
				$methods['card'] = true;
				$methods['emi']  = true;
				break;
			case self::AMERICAN_EXPRESS:
				$methods['card'] = true;
				$methods['emi']  = true;
				break;
			case self::FPX:
				$methods['fpx'] = true;
				break;
			default:
				$methods = [];
		}

		return $methods;
	}
}
