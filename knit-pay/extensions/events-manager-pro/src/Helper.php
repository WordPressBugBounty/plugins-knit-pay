<?php

namespace KnitPay\Extensions\EventsManagerPro;

use Pronamic\WordPress\Pay\Address;
use Pronamic\WordPress\Pay\AddressHelper;
use Pronamic\WordPress\Pay\ContactName;
use Pronamic\WordPress\Pay\ContactNameHelper;
use Pronamic\WordPress\Pay\CustomerHelper;
use EM_Booking;
use EM_Gateways;
use EM_Person;

/**
 * Title: Events Manager Pro Helper
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   3.2.0
 */
class Helper {

	/**
	 * Get title.
	 *
	 * @return string
	 */
	public static function get_title( $booking_id ) {
		/* translators: %s: Events Manager Pro */
		return sprintf( __( 'Events Manager Pro %s', 'knit-pay-lang' ), $booking_id );
	}

	/**
	 * Get description.
	 *
	 * @return string
	 */
	public static function get_description( $payment_method, $booking_id ) {
		$description = get_option( 'em_' . $payment_method . '_payment_description' );

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
	 * Get address.
	 *
	 * @return null|string
	 */
	private static function get_value_from_booking( $EM_Booking, $key ) {
		if ( EM_Gateways::get_customer_field( $key, $EM_Booking ) != '' ) {
			return EM_Gateways::get_customer_field( $key, $EM_Booking );
		}
		return null;
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
	public static function get_customer( EM_Person $person ) {
		return CustomerHelper::from_array(
			[
				'name'    => self::get_name( $person ),
				'email'   => self::get_value_from_object( $person, 'user_email' ),
				'phone'   => self::get_value_from_object( $person, 'phone' ),
				'user_id' => null,
			]
		);
	}

	/**
	 * Get name from order.
	 *
	 * @return ContactName|null
	 */
	public static function get_name( EM_Person $person ) {
		return ContactNameHelper::from_array(
			[
				'first_name' => self::get_value_from_object( $person, 'first_name' ),
				'last_name'  => self::get_value_from_object( $person, 'last_name' ),
			]
		);
	}

	/**
	 * Get address from order.
	 *
	 * @return Address|null
	 */
	public static function get_address( EM_Booking $EM_Booking ) {
		$person = $EM_Booking->get_person();

		$address = AddressHelper::from_array(
			[
				'name'        => self::get_name( $person ),
				'line_1'      => self::get_value_from_booking( $EM_Booking, 'address' ),
				'line_2'      => self::get_value_from_booking( $EM_Booking, 'address_2' ),
				'postal_code' => self::get_value_from_booking( $EM_Booking, 'zip' ),
				'city'        => self::get_value_from_booking( $EM_Booking, 'city' ),
				'region'      => self::get_value_from_booking( $EM_Booking, 'state' ),
				'email'       => self::get_value_from_object( $person, 'user_email' ),
				'phone'       => self::get_value_from_object( $person, 'phone' ),
			]
		);

		$country = self::get_value_from_booking( $EM_Booking, 'country' );
		if ( 2 !== strlen( $country ) ) {
			$address->set_country_name( $country );
		} else {
			$address->set_country_code( $country );
		}

		return $address;
	}
}
