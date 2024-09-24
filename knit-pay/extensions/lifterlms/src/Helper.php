<?php

namespace KnitPay\Extensions\LifterLMS;

use Pronamic\WordPress\Pay\Address;
use Pronamic\WordPress\Pay\AddressHelper;
use Pronamic\WordPress\Pay\ContactName;
use Pronamic\WordPress\Pay\ContactNameHelper;
use Pronamic\WordPress\Pay\CustomerHelper;

/**
 * Title: Lifter LMS Helper
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   1.8
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
			/* translators: %s: Lifter LMS Order */
			__( 'Lifter LMS Order %s', 'knit-pay-lang' ),
			$order_id
		);
	}

	/**
	 * Get description.
	 *
	 * @return string
	 */
	public static function get_description( $llms_gateway, $order_id ) {
		$description = $llms_gateway->get_option( 'payment_description' );

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

	/**
	 * Get customer from order.
	 */
	public static function get_customer_from_order( $order ) {
		return CustomerHelper::from_array(
			[
				'name'    => self::get_name_from_order( $order ),
				'email'   => self::get_value_from_object( $order, 'billing_email' ),
				'phone'   => self::get_value_from_object( $order, 'billing_phone' ),
				'user_id' => null,
			]
		);
	}

	/**
	 * Get name from order.
	 *
	 * @return ContactName|null
	 */
	public static function get_name_from_order( $order ) {
		return ContactNameHelper::from_array(
			[
				'first_name' => self::get_value_from_object( $order, 'billing_first_name' ),
				'last_name'  => self::get_value_from_object( $order, 'billing_last_name' ),
			]
		);
	}

	/**
	 * Get address from order.
	 *
	 * @return Address|null
	 */
	public static function get_address_from_order( $order ) {
		return AddressHelper::from_array(
			[
				'name'         => self::get_name_from_order( $order ),
				'line_1'       => self::get_value_from_object( $order, 'billing_address_1' ),
				'line_2'       => self::get_value_from_object( $order, 'billing_address_2' ),
				'postal_code'  => self::get_value_from_object( $order, 'billing_zip' ),
				'city'         => self::get_value_from_object( $order, 'billing_city' ),
				'region'       => self::get_value_from_object( $order, 'billing_state' ),
				'country_code' => self::get_value_from_object( $order, 'billing_country' ),
				'email'        => self::get_value_from_object( $order, 'billing_email' ),
				'phone'        => self::get_value_from_object( $order, 'billing_phone' ),
			]
		);
	}
}
