<?php

namespace KnitPay\Extensions\BaBookEverything;

use Pronamic\WordPress\Pay\Address;
use Pronamic\WordPress\Pay\AddressHelper;
use Pronamic\WordPress\Pay\ContactName;
use Pronamic\WordPress\Pay\ContactNameHelper;
use Pronamic\WordPress\Pay\CustomerHelper;
use Pronamic\WordPress\Pay\Region;

/**
 * Title: BA Book Everything Helper
 * Description:
 * Copyright: 2020-2023 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   9.1.0.0
 */
class Helper {
	/**
	 * Get title.
	 *
	 * @param int $order_id Order ID.
	 * @return string
	 */
	public static function get_title( $order_id ) {
		return \sprintf(
			/* translators: %s: Ticket Booking */
			__( 'Order %s', 'knit-pay-lang' ),
			$order_id
		);
	}

	/**
	 * Get description.
	 *
	 * @return string
	 */
	public static function get_description( $description, $args ) {
		if ( empty( $description ) ) {
			$description = self::get_title( self::get_value_from_array( $args, 'order_id' ) );
		}

		// Replacements.
		$replacements = [
			'{order_id}'     => self::get_value_from_array( $args, 'order_id' ),
			'{order_number}' => self::get_value_from_array( $args, 'order_num' ),
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
		if ( isset( $array[ $key ] ) ) {
			return $array[ $key ];
		}
		return null;
	}

	/**
	 * Get customer from order.
	 */
	public static function get_customer( $args ) {
		return CustomerHelper::from_array(
			[
				'name'    => self::get_name( $args ),
				'email'   => self::get_value_from_array( $args, 'email' ),
				'phone'   => self::get_value_from_array( $args, 'phone' ),
				'user_id' => null,
			]
		);
	}

	/**
	 * Get name from order.
	 *
	 * @return ContactName|null
	 */
	public static function get_name( $args ) {
		return ContactNameHelper::from_array(
			[
				'first_name' => self::get_value_from_array( $args, 'first_name' ),
				'last_name'  => self::get_value_from_array( $args, 'last_name' ),
			]
		);
	}

	/**
	 * Get address from order.
	 *
	 * @return Address|null
	 */
	public static function get_address( $args ) {
		$address = self::get_value_from_array( $args, 'billing_address' );
		$region  = new Region();
		$region->set_code( self::get_value_from_array( $address, 'state' ) );

		return AddressHelper::from_array(
			[
				'name'         => self::get_name( $args ),
				'line_1'       => self::get_value_from_array( $address, 'address' ),
				'city'         => self::get_value_from_array( $address, 'city' ),
				'region'       => $region,
				'country_code' => self::get_value_from_array( $address, 'country' ),
				'email'        => self::get_value_from_array( $args, 'email' ),
				'phone'        => self::get_value_from_array( $args, 'phone' ),
			]
		);
	}
}
