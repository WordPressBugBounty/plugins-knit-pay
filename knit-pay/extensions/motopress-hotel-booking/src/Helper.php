<?php

namespace KnitPay\Extensions\MotopressHotelBooking;

use Pronamic\WordPress\Pay\Address;
use Pronamic\WordPress\Pay\AddressHelper;
use Pronamic\WordPress\Pay\ContactName;
use Pronamic\WordPress\Pay\ContactNameHelper;
use Pronamic\WordPress\Pay\CustomerHelper;
use MPHB\Entities\Customer;

/**
 * Title: MotoPress Hotel Booking Helper
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   3.6.0
 */
class Helper {

	/**
	 * Get title.
	 *
	 * @param int $booking_id Booking ID.
	 * @return string
	 */
	public static function get_title( $booking_id ) {
		/* translators: %s: MotoPress Hotel Booking */
		return sprintf( __( 'MotoPress Hotel Booking %s', 'knit-pay-lang' ), $booking_id );
	}

	/**
	 * Get description.
	 *
	 * @return string
	 */
	public static function get_description( $payment_description, $booking, $payment ) {
		$description = $payment_description;

		if ( empty( $description ) ) {
			$description = self::get_title( $booking->getId() );
		}

		// Replacements.
		$replacements = [
			'{booking_id}' => $booking->getId(),
			'{payment_id}' => $payment->getID(),
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
	private static function get_value_from_object( $object, $key ) {
		if ( ! empty( $object->$key() ) ) {
			return $object->$key();
		}
		return null;
	}

	/**
	 * Get customer from order.
	 */
	public static function get_customer( Customer $customer ) {
		return CustomerHelper::from_array(
			[
				'name'    => self::get_name( $customer ),
				'email'   => self::get_value_from_object( $customer, 'getEmail' ),
				'phone'   => self::get_value_from_object( $customer, 'getPhone' ),
				'user_id' => null,
			]
		);
	}

	/**
	 * Get name from order.
	 *
	 * @return ContactName|null
	 */
	public static function get_name( Customer $customer ) {
		return ContactNameHelper::from_array(
			[
				'first_name' => self::get_value_from_object( $customer, 'getFirstName' ),
				'last_name'  => self::get_value_from_object( $customer, 'getLastName' ),
			]
		);
	}

	/**
	 * Get address from order.
	 *
	 * @return Address|null
	 */
	public static function get_address( Customer $customer ) {

		return AddressHelper::from_array(
			[
				'name'         => self::get_name( $customer ),
				'line_1'       => self::get_value_from_object( $customer, 'getAddress1' ),
				'line_2'       => null,
				'postal_code'  => self::get_value_from_object( $customer, 'getZip' ),
				'city'         => self::get_value_from_object( $customer, 'getCity' ),
				'region'       => self::get_value_from_object( $customer, 'getState' ),
				'country_code' => self::get_value_from_object( $customer, 'getCountry' ),
				'email'        => self::get_value_from_object( $customer, 'getEmail' ),
				'phone'        => self::get_value_from_object( $customer, 'getPhone' ),
			]
		);
	}
}
