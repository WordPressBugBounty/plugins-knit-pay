<?php

namespace KnitPay\Extensions\BooklyPro;

use Pronamic\WordPress\Pay\Address;
use Pronamic\WordPress\Pay\AddressHelper;
use Pronamic\WordPress\Pay\ContactName;
use Pronamic\WordPress\Pay\ContactNameHelper;
use Pronamic\WordPress\Pay\CustomerHelper;
use Bookly\Lib\UserBookingData;

/**
 * Title: Bookly Pro Helper
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   3.4
 */
class Helper {

	/**
	 * Get title.
	 *
	 * @return string
	 */
	public static function get_title( $userData ) {
		return $userData->cart->getItemsTitle( 128, false );
	}

	/**
	 * Get description
	 *
	 * @return string
	 */
	public static function get_description( $payment_method, $form_id, $userData, $bookly_payment ) {
		$description = get_option( 'bookly_' . $payment_method . '_payment_description' );

		if ( empty( $description ) ) {
			$description = self::get_title( $userData );
		}

		// Replacements.
		$replacements = [
			'{form_id}'      => $form_id,
			'{service_name}' => $userData->cart->getItemsTitle( 128, false ),
			'{payment_id}'   => $bookly_payment->getId(),
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
	public static function get_customer( UserBookingData $user_data ) {
		return CustomerHelper::from_array(
			[
				'name'    => self::get_name( $user_data ),
				'email'   => self::get_value_from_object( $user_data, 'getEmail' ),
				'phone'   => self::get_value_from_object( $user_data, 'getPhone' ),
				'user_id' => null,
			]
		);
	}

	/**
	 * Get name from order.
	 *
	 * @return ContactName|null
	 */
	public static function get_name( UserBookingData $user_data ) {
		$contact_name = new ContactName();
		$contact_name->set_first_name( self::get_value_from_object( $user_data, 'getFirstName' ) );
		$contact_name->set_last_name( self::get_value_from_object( $user_data, 'getLastName' ) );
		$contact_name->set_full_name( self::get_value_from_object( $user_data, 'getFullName' ) );

		return $contact_name;
	}

	/**
	 * Get address from order.
	 *
	 * @return Address|null
	 */
	public static function get_address( UserBookingData $user_data ) {

		return AddressHelper::from_array(
			[
				'name'         => self::get_name( $user_data ),
				'line_1'       => self::get_value_from_object( $user_data, 'getStreet' ),
				'line_2'       => self::get_value_from_object( $user_data, 'getAdditionalAddress' ),
				'postal_code'  => self::get_value_from_object( $user_data, 'getPostcode' ),
				'city'         => self::get_value_from_object( $user_data, 'getCity' ),
				'region'       => self::get_value_from_object( $user_data, 'getState' ),
				'country_code' => null,
				'email'        => self::get_value_from_object( $user_data, 'getEmail' ),
				'phone'        => self::get_value_from_object( $user_data, 'getPhone' ),
			]
		);
	}
}
