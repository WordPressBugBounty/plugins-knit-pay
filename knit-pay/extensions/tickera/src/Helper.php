<?php

namespace KnitPay\Extensions\Tickera;

use Pronamic\WordPress\Pay\Address;
use Pronamic\WordPress\Pay\AddressHelper;
use Pronamic\WordPress\Pay\ContactName;
use Pronamic\WordPress\Pay\ContactNameHelper;
use Pronamic\WordPress\Pay\CustomerHelper;

/**
 * Title: Tickera Helper
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   8.84.0.0
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
			/* translators: %s: Tickera Order */
			__( 'Tickera Order %s', 'knit-pay-lang' ),
			$order_id
		);
	}

	/**
	 * Get description.
	 *
	 * @return string
	 */
	public static function get_description( $gateway, $order_id ) {
		$description = $gateway->payment_description;

		if ( empty( $description ) ) {
			$description = self::get_title( $order_id );
		}

		// Replacements.
		$replacements = [
			'{order_id}' => $order_id,
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
	public static function get_customer_from_order( $cart_info ) {
		return CustomerHelper::from_array(
			[
				'name'    => self::get_name_from_order( $cart_info['buyer_data'] ),
				'email'   => self::get_value_from_array( $cart_info['buyer_data'], 'email_post_meta' ),
				'user_id' => null,
			]
		);
	}

	/**
	 * Get name from order.
	 *
	 * @return ContactName|null
	 */
	public static function get_name_from_order( $buyer_data ) {
		return ContactNameHelper::from_array(
			[
				'first_name' => self::get_value_from_array( $buyer_data, 'first_name_post_meta' ),
				'last_name'  => self::get_value_from_array( $buyer_data, 'last_name_post_meta' ),
			]
		);
	}

	/**
	 * Get address from order.
	 *
	 * @return Address|null
	 */
	public static function get_address_from_order( $cart_info ) {
		return AddressHelper::from_array(
			[
				'name'  => self::get_name_from_order( $cart_info['buyer_data'] ),
				'email' => self::get_value_from_array( $cart_info['buyer_data'], 'email_post_meta' ),
			]
		);
	}
}
