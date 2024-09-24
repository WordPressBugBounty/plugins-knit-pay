<?php

namespace KnitPay\Extensions\KnitPayPaymentButton;

use Pronamic\WordPress\Pay\Address;
use Pronamic\WordPress\Pay\AddressHelper;
use Pronamic\WordPress\Pay\ContactName;
use Pronamic\WordPress\Pay\ContactNameHelper;
use Pronamic\WordPress\Pay\CustomerHelper;
use Pronamic\WordPress\Pay\Payments\Payment;

/**
 * Title: Knit Pay - Payment Button Helper
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   8.75.0.0
 */
class Helper {
	/**
	 * Get title.
	 *
	 * @param Payment $payment
	 * @return string
	 */
	public static function get_title( Payment $payment ) {
		return \sprintf(
			/* translators: %s: Payment Button  */
			__( 'Payment Button %s', 'knit-pay-lang' ),
			$payment->get_order_id()
		);
	}

	/**
	 * Get description.
	 *
	 * @param Payment $payment
	 * @return string
	 */
	public static function get_description( Payment $payment, $description ) {
		if ( empty( $description ) ) {
			$description = self::get_title( $payment );
		}

		// Replacements.
		$replacements = [
			'{knit_pay_payment_id}' => $payment->get_id(),
			'{knit_pay_order_id}'   => $payment->get_order_id(),
		];

		return strtr( $description, $replacements );
	}

	/**
	 * Get value from object.
	 *
	 * @param object $object Object.
	 * @param string $key   Key.
	 * @return string|null
	 */
	private static function get_value_from_object( $object, $var ) {
		if ( isset( $object->{$var} ) ) {
			return $object->{$var};
		}
		return null;
	}

	/**
	 * Get customer from order.
	 */
	public static function get_customer( $current_user ) {
		return CustomerHelper::from_array(
			[
				'name'    => self::get_name( $current_user ),
				'email'   => self::get_value_from_object( $current_user, 'user_email' ),
				'user_id' => $current_user->ID,
			]
		);
	}

	/**
	 * Get name from order.
	 *
	 * @return ContactName|null
	 */
	public static function get_name( $current_user ) {
		return ContactNameHelper::from_array(
			[
				'first_name' => self::get_value_from_object( $current_user, 'user_firstname' ),
				'last_name'  => self::get_value_from_object( $current_user, 'user_lastname' ),
			]
		);
	}

	/**
	 * Get address from order.
	 *
	 * @return Address|null
	 */
	public static function get_address( $current_user ) {
		return AddressHelper::from_array(
			[
				'name'  => self::get_name( $current_user ),
				'email' => self::get_value_from_object( $current_user, 'user_email' ),
			]
		);
	}
}
