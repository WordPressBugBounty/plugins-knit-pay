<?php
namespace KnitPay\Extensions\WPTravelEngine;

use Pronamic\WordPress\Pay\Address;
use Pronamic\WordPress\Pay\AddressHelper;
use Pronamic\WordPress\Pay\ContactName;
use Pronamic\WordPress\Pay\ContactNameHelper;
use Pronamic\WordPress\Pay\CustomerHelper;

/**
 * Title: WP Travel Engine Helper
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   1.9
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
	 * @param int $booking_id Booking ID.
	 * @return string
	 */
	public static function get_description( $booking_id, $gateway ) {
		$description = ! empty( $gateway->knit_pay_settings['payment_description'] ) ? $gateway->knit_pay_settings['payment_description'] : '';

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

		return $array[ $key ];
	}

	/**
	 * Get customer from order.
	 */
	public static function get_customer( $booking_details ) {
		return CustomerHelper::from_array(
			[
				'name'    => self::get_name( $booking_details ),
				'email'   => self::get_value_from_array( $booking_details, 'email' ),
				'phone'   => null, // TODO
				'user_id' => null,
			]
		);
	}

	/**
	 * Get name from order.
	 *
	 * @return ContactName|null
	 */
	public static function get_name( $booking_details ) {
		return ContactNameHelper::from_array(
			[
				'first_name' => self::get_value_from_array( $booking_details, 'fname' ),
				'last_name'  => self::get_value_from_array( $booking_details, 'lname' ),
			]
		);
	}

	/**
	 * Get address from order.
	 *
	 * @return Address|null
	 */
	public static function get_address( $booking_details ) {

		$address = AddressHelper::from_array(
			[
				'name'   => self::get_name( $booking_details ),
				'line_1' => self::get_value_from_array( $booking_details, 'address' ),
				'city'   => self::get_value_from_array( $booking_details, 'city' ),
				'email'  => self::get_value_from_array( $booking_details, 'email' ),
				'phone'  => null, // TODO
			]
		);
		$address->set_country_name( self::get_value_from_array( $booking_details, 'country' ) );
		return $address;
	}
}
