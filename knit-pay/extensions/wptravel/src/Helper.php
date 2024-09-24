<?php
namespace KnitPay\Extensions\WPTravel;

use Pronamic\WordPress\Pay\Address;
use Pronamic\WordPress\Pay\AddressHelper;
use Pronamic\WordPress\Pay\ContactName;
use Pronamic\WordPress\Pay\ContactNameHelper;
use Pronamic\WordPress\Pay\CustomerHelper;

/**
 * Title: WP Travel Helper
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   8.78.0.0
 */
class Helper {

	/**
	 * Get title.
	 *
	 * @param int $booking_id Booking ID.
	 * @return string
	 */
	public static function get_title( $booking_id ) {
		/* translators: %s: Booking */
		return sprintf( __( 'Booking #%s', 'knit-pay-lang' ), $booking_id );
	}

	/**
	 * Get description.
	 *
	 * @param array $knit_pay_settings Knit Pay Settings.
	 * @param int   $booking_id Booking ID.
	 * @return string
	 */
	public static function get_description( $knit_pay_settings, $booking_id ) {
		$description = $knit_pay_settings['payment_description'];

		if ( empty( $description ) ) {
			$description = self::get_title( $booking_id );
		}

		// Replacements.
		$replacements = [
			'{booking_id}' => $booking_id,
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
		
		if ( is_array( $array[ $key ] ) ) {
			return self::get_value_from_array( $array[ $key ], key( $array[ $key ] ) );
		}

		return $array[ $key ];
	}

	/**
	 * Get customer from order.
	 */
	public static function get_customer( $order_data ) {
		return CustomerHelper::from_array(
			[
				'name'    => self::get_name( $order_data ),
				'email'   => self::get_value_from_array( $order_data, 'wp_travel_email_traveller' ),
				'phone'   => self::get_value_from_array( $order_data, 'wp_travel_phone_traveller' ),
				'user_id' => null,
			]
		);
	}

	/**
	 * Get name from order.
	 *
	 * @return ContactName|null
	 */
	public static function get_name( $order_data ) {
		return ContactNameHelper::from_array(
			[
				'first_name' => self::get_value_from_array( $order_data, 'wp_travel_fname_traveller' ),
				'last_name'  => self::get_value_from_array( $order_data, 'wp_travel_lname_traveller' ),
			]
		);
	}

	/**
	 * Get address from order.
	 *
	 * @return Address|null
	 */
	public static function get_address( $order_data ) {
		return AddressHelper::from_array(
			[
				'name'         => self::get_name( $order_data ),
				'line_1'       => self::get_value_from_array( $order_data, 'wp_travel_address' ),
				'city'         => self::get_value_from_array( $order_data, 'billing_city' ),
				'postal_code'  => self::get_value_from_array( $order_data, 'billing_postal' ),
				'country_code' => self::get_value_from_array( $order_data, 'wp_travel_country' ),
				'email'        => self::get_value_from_array( $order_data, 'wp_travel_email_traveller' ),
				'phone'        => self::get_value_from_array( $order_data, 'wp_travel_phone_traveller' ),
			]
		);
	}
}
