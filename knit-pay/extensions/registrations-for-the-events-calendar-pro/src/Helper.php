<?php

namespace KnitPay\Extensions\RegistrationsForTheEventsCalendarPro;

use Pronamic\WordPress\Pay\Address;
use Pronamic\WordPress\Pay\AddressHelper;
use Pronamic\WordPress\Pay\ContactName;
use Pronamic\WordPress\Pay\ContactNameHelper;
use Pronamic\WordPress\Pay\CustomerHelper;

/**
 * Title: Registrations For The Events Calendar Pro Helper
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   4.2.0
 */
class Helper {

	/**
	 * Get title.
	 *
	 * @param array $course_data.
	 * @return string
	 */
	public static function get_title( $event_name ) {
		return $event_name;
	}

	/**
	 * Get description.
	 *
	 * @param array $config
	 * @param array $course_data.
	 * @return string
	 */
	public static function get_description( $event_name, $entry_id ) {
		$description = $event_name . ' ' . $entry_id;
		return $description; // TODO: make it editable by admin.

		if ( empty( $description ) ) {
			$description = self::get_title( $course_data );
		}

		// Replacements.
		$replacements = [
			'{course_id}'   => $course_data['course_id'],
			'{course_name}' => $course_data['course_name'],
		];

		return strtr( $description, $replacements );
	}

	/**
	 * Get value from array.
	 *
	 * @param array  $array Array.
	 * @param string $key   Key.
	 * @return string|null
	 */
	private static function get_value_from_array( $array, $key ) {
		if ( ! array_key_exists( $key, $array ) ) {
			return null;
		}

		return $array[ $key ]['value'];
	}

	/**
	 * Get customer from order.
	 */
	public static function get_customer( $entry_data ) {
		return CustomerHelper::from_array(
			[
				'name'    => self::get_name( $entry_data['entry_data_cache'] ),
				'email'   => self::get_value_from_array( $entry_data['entry_data_cache'], 'email' ),
				'phone'   => self::get_value_from_array( $entry_data['entry_data_cache'], 'phone' ),
				'user_id' => self::get_value_from_array( $entry_data['entry_data_cache'], 'userIDOverride' ),
			]
		);
	}

	/**
	 * Get name from order.
	 *
	 * @return ContactName|null
	 */
	public static function get_name( $entry_data_cache ) {
		return ContactNameHelper::from_array(
			[
				'first_name' => self::get_value_from_array( $entry_data_cache, 'first' ),
				'last_name'  => self::get_value_from_array( $entry_data_cache, 'last' ),
			]
		);
	}

	/**
	 * Get address from order.
	 *
	 * @return Address|null
	 */
	public static function get_address( $entry_data ) {

		return AddressHelper::from_array(
			[
				'name'  => self::get_name( $entry_data['entry_data_cache'] ),
				'email' => self::get_value_from_array( $entry_data['entry_data_cache'], 'email' ),
				'phone' => self::get_value_from_array( $entry_data['entry_data_cache'], 'phone' ),

			]
		);
	}
}
