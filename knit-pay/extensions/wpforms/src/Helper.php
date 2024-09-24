<?php

namespace KnitPay\Extensions\WPForms;

use Pronamic\WordPress\Pay\Address;
use Pronamic\WordPress\Pay\AddressHelper;
use Pronamic\WordPress\Pay\ContactName;
use Pronamic\WordPress\Pay\ContactNameHelper;
use Pronamic\WordPress\Pay\CustomerHelper;

/**
 * Title: WPForms Helper
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   5.9.0.0
 */
class Helper {
	/**
	 * Get title.
	 *
	 * @param int $order_id Order ID.
	 * @return string
	 */
	public static function get_title( $entry_id ) {
		return \sprintf(
			/* translators: %s: WPForms Entry */
			__( 'WPForms Entry %s', 'knit-pay-lang' ),
			$entry_id
		);
	}

	/**
	 * Get description.
	 *
	 * @return string
	 */
	public static function get_description( $form_data, $entry_id, $payment_settings ) {
		$description = self::get_value_from_array( $payment_settings, 'payment_description' );

		if ( empty( $description ) ) {
			$description = self::get_title( $entry_id );
		}

		// Replacements.
		$replacements = [
			'{form_title}' => self::get_value_from_array( $form_data['settings'], 'form_title' ),
			'{form_desc}'  => self::get_value_from_array( $form_data['settings'], 'form_desc' ),
			'{form_id}'    => self::get_value_from_array( $form_data, 'id' ),
			'{entry_id}'   => $entry_id,          
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
	public static function get_customer( $entry_fields, $payment_settings ) {
		return CustomerHelper::from_array(
			[
				'name'    => self::get_name( $entry_fields[ $payment_settings['buyer_name'] ] ),
				'email'   => self::get_value_from_array( $entry_fields, $payment_settings['buyer_email'] ),
				'phone'   => self::get_value_from_array( $entry_fields, $payment_settings['buyer_phone'] ),
				'user_id' => null,
			]
		);
	}

	/**
	 * Get name from order.
	 *
	 * @return ContactName|null
	 */
	public static function get_name( $name ) {
		if ( is_string( $name ) ) {
			return \KnitPay\Utils::get_contact_name_from_string( $name );
		}

		$first_name = self::get_value_from_array( $name, 'first' );
		$last_name  = '';
		if ( array_key_exists( 'middle', $name ) ) {
			$last_name .= self::get_value_from_array( $name, 'middle' ) . ' ';
		}
		if ( array_key_exists( 'last', $name ) ) {
			$last_name .= self::get_value_from_array( $name, 'last' );
		}

		return ContactNameHelper::from_array(
			[
				'first_name' => $first_name,
				'last_name'  => $last_name,
			]
		);
	}

	/**
	 * Get address from order.
	 *
	 * @return Address|null
	 */
	public static function get_address( $entry_fields, $payment_settings ) {
		return AddressHelper::from_array(
			[
				'name'  => self::get_name( $entry_fields[ $payment_settings['buyer_name'] ] ),
				'email' => self::get_value_from_array( $entry_fields, $payment_settings['buyer_email'] ),
				'phone' => self::get_value_from_array( $entry_fields, $payment_settings['buyer_phone'] ),
			]
		);
	}
}
