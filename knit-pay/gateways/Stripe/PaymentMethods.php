<?php

namespace KnitPay\Gateways\Stripe;

use KnitPay\Gateways\PaymentMethods as KP_PaymentMethods;
use WPForms\Admin\Education\Core;

class PaymentMethods extends KP_PaymentMethods {
	const STRIPE = 'stripe';

	/**
	 * Transform an Stripe status to an Knit Pay status
	 *
	 * @param string $status
	 *
	 * @return string
	 */
	public static function transform( $method, $enabled_methods = null, $output_array = true ) {
		if ( empty( $method ) || 'knit_pay' === $method ) {
			$method = self::STRIPE;
		}

		$payment_method_types = [];
		switch ( $method ) {
			case self::CREDIT_CARD:
			case self::CARD:
				$payment_method_types = 'card';
				break;
			case self::DIRECT_DEBIT:
				$payment_method_types = 'sepa_debit';
				break;
			case self::AFTERPAY_COM:
				$payment_method_types = 'afterpay_clearpay';
				break;
			case self::STRIPE:
				foreach ( $enabled_methods as $payment_method ) {
					$payment_method_types[] = self::transform( $payment_method, null, false );
				}
				break;
			default:
				$payment_method_types = $method;
				break;
		}
		if ( $output_array && ! is_array( $payment_method_types ) ) {
			return [ $payment_method_types ];
		}
		return $payment_method_types;
	}
}
