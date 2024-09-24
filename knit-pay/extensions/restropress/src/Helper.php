<?php

namespace KnitPay\Extensions\RestroPress;

use Pronamic\WordPress\Pay\Address;
use Pronamic\WordPress\Pay\AddressHelper;
use Pronamic\WordPress\Pay\ContactName;
use Pronamic\WordPress\Pay\ContactNameHelper;
use Pronamic\WordPress\Pay\CustomerHelper;

/**
 * Title: Restro Press Helper
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   2.6
 */
class Helper {

	/**
	 * Get title.
	 *
	 * @param int $payment_id Payment ID.
	 * @return string
	 */
	public static function get_title( $payment_id ) {
		/* translators: %s: Restro Press Order  */
		return sprintf( __( 'Restro Press Order %s', 'knit-pay-lang' ), $payment_id );
	}

	/**
	 * Get description.
	 *
	 * @return string
	 */
	public static function get_description( $purchase_data, $payment_id ) {
		$description = rpress_get_option( $purchase_data['gateway'] . '_payment_description' );

		if ( empty( $description ) ) {
			$description = self::get_title( $payment_id );
		}

		// Replacements.
		$replacements = [
			'{order_id}' => $payment_id,
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
	public static function get_customer( $purchase_data ) {
		return CustomerHelper::from_array(
			[
				'name'    => self::get_name( $purchase_data['user_info'] ),
				'email'   => self::get_value_from_array( $purchase_data['user_info'], 'email' ),
				'phone'   => self::get_value_from_array( $purchase_data['post_data'], 'rpress_phone' ),
				'user_id' => null,
			]
		);
	}

	/**
	 * Get name from order.
	 *
	 * @return ContactName|null
	 */
	public static function get_name( $user_info ) {
		return ContactNameHelper::from_array(
			[
				'first_name' => self::get_value_from_array( $user_info, 'first_name' ),
				'last_name'  => self::get_value_from_array( $user_info, 'last_name' ),
			]
		);
	}

	/**
	 * Get address from order.
	 *
	 * @return Address|null
	 */
	public static function get_address_from_order( $purchase_data ) {
		$post_data = $purchase_data['post_data'];

		return AddressHelper::from_array(
			[
				'name'         => self::get_name( $purchase_data['user_info'] ),
				'line_1'       => self::get_value_from_array( $post_data, 'rpress_street_address' ),
				'line_2'       => self::get_value_from_array( $post_data, 'rpress_apt_suite' ),
				'postal_code'  => self::get_value_from_array( $post_data, 'rpress_postcode' ),
				'city'         => self::get_value_from_array( $post_data, 'rpress_city' ),
				'region'       => \rpress_get_option( 'base_state', null ),
				'country_code' => \rpress_get_option( 'base_country', null ),
				'email'        => self::get_value_from_array( $purchase_data['user_info'], 'email' ),
				'phone'        => self::get_value_from_array( $purchase_data['post_data'], 'rpress_phone' ),
			]
		);
	}
}
