<?php

namespace KnitPay\Gateways\PhonePe;

use KnitPay\Gateways\PaymentMethods as KP_PaymentMethods;

class PaymentMethods extends KP_PaymentMethods {

	/**
	 * Transform WordPress payment method to PhonePe method.
	 *
	 * @param mixed $payment_method Payment method.
	 *
	 * @return string
	 */
	public static function transform( $payment_method ) {
		if ( ! is_scalar( $payment_method ) ) {
			return [];
		}

		$payment_mode_config = [
			'enabledPaymentModes' => [],
		];

		switch ( $payment_method ) {
			case self::NET_BANKING:
				$payment_mode_config['enabledPaymentModes'][] = [ 'type' => 'NET_BANKING' ];
				break;

			case self::DEBIT_CARD:
				$payment_mode_config['enabledPaymentModes'][] = [
					'type'      => 'CARD',
					'cardTypes' => [ 'DEBIT_CARD' ],
				];
				break;

			case self::CREDIT_CARD:
				$payment_mode_config['enabledPaymentModes'][] = [
					'type'      => 'CARD',
					'cardTypes' => [ 'CREDIT_CARD' ],
				];
				break;

			case self::UPI:
				$payment_mode_config['enabledPaymentModes'][] = [ 'type' => 'UPI_QR' ];
				$payment_mode_config['enabledPaymentModes'][] = [ 'type' => 'UPI_INTENT' ];
				$payment_mode_config['enabledPaymentModes'][] = [ 'type' => 'UPI_COLLECT' ];

				break;

			default:
				return [];
		}

		return [
			'paymentModeConfig' => $payment_mode_config,
		];
	}
}
