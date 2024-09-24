<?php

namespace KnitPay\Extensions\LearnDash;

use Pronamic\WordPress\Pay\Address;
use Pronamic\WordPress\Pay\AddressHelper;
use Pronamic\WordPress\Pay\ContactName;
use Pronamic\WordPress\Pay\ContactNameHelper;
use Pronamic\WordPress\Pay\CustomerHelper;
use WP_User;

/**
 * Title: Learn Dash LMS Helper
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   2.7.0
 */
class Helper {

	/**
	 * Get title.
	 *
	 * @param array $course_data.
	 * @return string
	 */
	public static function get_title( $product ) {
		return $product->get_post()->post_title;
	}

	/**
	 * Get description.
	 *
	 * @param array  $config
	 * @param object $product.
	 * @return string
	 */
	public static function get_description( $config, $product ) {
		$description = $config['payment_description'];

		if ( empty( $description ) ) {
			$description = self::get_title( $product );
		}

		// Replacements.
		$replacements = [
			'{course_id}'   => $product->get_id(),
			'{course_name}' => $product->get_post()->post_title,
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
	public static function get_customer( WP_User $user ) {
		return CustomerHelper::from_array(
			[
				'name'    => self::get_name( $user ),
				'email'   => self::get_value_from_object( $user, 'user_email' ),
				'phone'   => null,
				'user_id' => self::get_value_from_object( $user, 'ID' ),
			]
		);
	}

	/**
	 * Get name from order.
	 *
	 * @return ContactName|null
	 */
	public static function get_name( WP_User $user ) {
		return ContactNameHelper::from_array(
			[
				'first_name' => self::get_value_from_object( $user, 'first_name' ),
				'last_name'  => self::get_value_from_object( $user, 'last_name' ),
			]
		);
	}

	/**
	 * Get address from order.
	 *
	 * @return Address|null
	 */
	public static function get_address( WP_User $user ) {

		return AddressHelper::from_array(
			[
				'name'  => self::get_name( $user ),
				'email' => self::get_value_from_object( $user, 'user_email' ),
			]
		);
	}
}
