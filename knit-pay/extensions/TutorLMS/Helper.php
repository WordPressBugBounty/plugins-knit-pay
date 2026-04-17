<?php

namespace KnitPay\Extensions\TutorLMS;

use Pronamic\WordPress\Pay\Address;
use Pronamic\WordPress\Pay\AddressHelper;
use Pronamic\WordPress\Pay\ContactName;
use Pronamic\WordPress\Pay\ContactNameHelper;
use Pronamic\WordPress\Pay\CustomerHelper;
use KnitPay\Utils;

/**
 * Title: Tutor LMS Helper
 * Description:
 * Copyright: 2020-2026 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   8.97.0.0
 */
class Helper {
	/**
	 * Get title.
	 *
	 * @param int $order_id Order ID.
	 * @return string
	 */
	public static function get_title( $order_id ) {
		return \sprintf(
			/* translators: %s: Tutor LMS Order */
			__( 'Order %s', 'knit-pay-lang' ),
			$order_id
		);
	}

	/**
	 * Get description.
	 *
	 * @return string
	 */
	public static function get_description( $description, $order_id ) {
		if ( empty( $description ) ) {
			$description = self::get_title( $order_id );
		}

		// Replacements.
		$replacements = [
			'{order_id}' => $order_id,
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

	public static function get_currency( $payment_data ) {
		$currency = self::get_value_from_object( $payment_data, 'currency' );
		return self::get_value_from_object( $currency, 'code' );
	}

	/**
	 * Get customer from order.
	 */
	public static function get_customer( $payment_data ) {
		return CustomerHelper::from_array(
			[
				'name'    => self::get_name( $payment_data->customer ),
				'email'   => self::get_value_from_object( $payment_data->customer, 'email' ),
				'phone'   => self::get_value_from_object( $payment_data->customer, 'phone_number' ),
				'user_id' => null,
			]
		);
	}

	/**
	 * Get name from order.
	 *
	 * @return ContactName|null
	 */
	public static function get_name( $customer ) {
		return Utils::get_contact_name_from_string( self::get_value_from_object( $customer, 'name' ) );
	}

	/**
	 * Get address from order.
	 *
	 * @return Address|null
	 */
	public static function get_address( $payment_data ) {
		$billing_address = $payment_data->billing_address;

		return AddressHelper::from_array(
			[
				'name'         => self::get_name( $billing_address ),
				'line_1'       => self::get_value_from_object( $billing_address, 'address1' ),
				'line_2'       => self::get_value_from_object( $billing_address, 'address2' ),
				'postal_code'  => self::get_value_from_object( $billing_address, 'postal_code' ),
				'city'         => self::get_value_from_object( $billing_address, 'city' ),
				'region'       => self::get_value_from_object( $billing_address, 'state' ),
				'country_code' => self::get_value_from_object( self::get_value_from_object( $billing_address, 'country' ), 'alpha_2' ),
				'email'        => self::get_value_from_object( $billing_address, 'email' ),
				'phone'        => self::get_value_from_object( $billing_address, 'phone_number' ),
			]
		);
	}
}
