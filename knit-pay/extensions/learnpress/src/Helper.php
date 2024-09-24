<?php

namespace KnitPay\Extensions\LearnPress;

use Pronamic\WordPress\Pay\Address;
use Pronamic\WordPress\Pay\AddressHelper;
use Pronamic\WordPress\Pay\ContactName;
use Pronamic\WordPress\Pay\ContactNameHelper;
use Pronamic\WordPress\Pay\CustomerHelper;
use LP_Checkout;
use LP_Settings;

/**
 * Title: LearnPress Helper
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   1.6.0
 */
class Helper {

	/**
	 * Get title.
	 *
	 * @param int $order_id Order ID.
	 * @return string
	 */
	public static function get_title( $order_id ) {
		/* translators: %s: Learn Press Order */
		return sprintf( __( 'Learn Press Order %s', 'knit-pay-lang' ), $order_id );
	}

	/**
	 * Get description.
	 *
	 * @param LP_Settings $settings Knit Pay Settings.
	 * @param int         $order_id Order ID.
	 * @return string
	 */
	public static function get_description( LP_Settings $settings, $order_id ) {
		$description = $settings->get( 'payment_description' );

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
		$register_profile_fields = get_option( 'learn_press_knit_pay_register_profile_fields' );

		if ( isset( $object->_lp_custom_register ) && isset( $register_profile_fields[ $var ] ) && isset( $object->_lp_custom_register[ $register_profile_fields[ $var ] ] ) ) {
			return $object->_lp_custom_register[ $register_profile_fields[ $var ] ];
		} elseif ( isset( $object->{$var} ) ) {
			return $object->{$var};
		}
		return null;
	}

	/**
	 * Get customer from order.
	 */
	public static function get_customer( LP_Checkout $checkout, $user_data = false ) {
			return CustomerHelper::from_array(
				[
					'name'    => self::get_name( $user_data ),
					'email'   => $checkout->get_checkout_email(),
					'phone'   => self::get_value_from_object( $user_data, 'phone' ),
					'user_id' => self::get_value_from_object( $user_data, 'ID' ),
				]
			);
	}

	/**
	 * Get name from order.
	 *
	 * @return ContactName|null
	 */
	public static function get_name( $user_data ) {
		return ContactNameHelper::from_array(
			[
				'first_name' => self::get_value_from_object( $user_data, 'first_name' ),
				'last_name'  => self::get_value_from_object( $user_data, 'last_name' ),
			]
		);
	}

	/**
	 * Get address from order.
	 *
	 * @return Address|null
	 */
	public static function get_address( LP_Checkout $checkout, $user_data = false ) {
		return AddressHelper::from_array(
			[
				'name'         => self::get_name( $user_data ),
				'line_1'       => self::get_value_from_object( $user_data, 'billing_line_1' ),
				'line_2'       => self::get_value_from_object( $user_data, 'billing_line_2' ),
				'postal_code'  => self::get_value_from_object( $user_data, 'billing_postal_code' ),
				'city'         => self::get_value_from_object( $user_data, 'billing_city' ),
				'region'       => self::get_value_from_object( $user_data, 'billing_region' ),
				'country_code' => self::get_value_from_object( $user_data, 'billing_country' ),
				'email'        => $checkout->get_checkout_email(),
				'phone'        => self::get_value_from_object( $user_data, 'phone' ),
			]
		);
	}
}
