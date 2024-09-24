<?php

namespace KnitPay\Extensions\WPAdverts;

use Pronamic\WordPress\Pay\Address;
use Pronamic\WordPress\Pay\AddressHelper;
use Pronamic\WordPress\Pay\ContactName;
use Pronamic\WordPress\Pay\ContactNameHelper;
use Pronamic\WordPress\Pay\CustomerHelper;

/**
 * Title: WP Adverts Helper
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   4.0.0
 */
class Helper {
	/**
	 * Get title.
	 *
	 * @param int $order_id Order ID.
	 * @return string
	 */
	public static function get_title( $data ) {
		return \sprintf(
			/* translators: %s: WP Adverts Payment */
			__( 'WP Adverts Payment %s', 'knit-pay-lang' ),
			$data['payment_id']
		);
	}

	/**
	 * Get description.
	 *
	 * @return string
	 */
	public static function get_description( $data ) {
		$description = adverts_config( $data['gateway_name'] . '.payment_description' );

		if ( empty( $description ) ) {
			$description = self::get_title( $data );
		}

		// Replacements.
		$replacements = [
			'{payment_id}'   => $data['payment_id'],
			'{listing_type}' => get_post( $data['listing_id'] )->post_title,
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
	public static function get_customer( $data ) {
		return CustomerHelper::from_array(
			[
				'name'    => self::get_name( $data ),
				'email'   => self::get_value_from_array( $data['form'], 'adverts_email' ),
				'phone'   => self::get_value_from_array( $data['form'], 'adverts_phone' ),
				'user_id' => null,
			]
		);
	}

	/**
	 * Get name from order.
	 *
	 * @return ContactName|null
	 */
	public static function get_name( $data ) {
		$name       = self::get_value_from_array( $data['form'], 'adverts_person' );
		$last_name  = ( strpos( $name, ' ' ) === false ) ? '' : preg_replace( '#.*\s([\w-]*)$#', '$1', $name );
		$first_name = trim( preg_replace( '#' . preg_quote( $last_name, '#' ) . '#', '', $name ) );

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
	public static function get_address( $data ) {
		return AddressHelper::from_array(
			[
				'name'  => self::get_name( $data ),
				'email' => self::get_value_from_array( $data['form'], 'adverts_email' ),
				'phone' => self::get_value_from_array( $data['form'], 'adverts_phone' ),
			]
		);
	}
}
