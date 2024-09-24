<?php

namespace KnitPay\Extensions\MycredBuycred;

use Pronamic\WordPress\Pay\Address;
use Pronamic\WordPress\Pay\AddressHelper;
use Pronamic\WordPress\Pay\ContactName;
use Pronamic\WordPress\Pay\ContactNameHelper;
use Pronamic\WordPress\Pay\CustomerHelper;

/**
 * Title: myCRED buyCRED Helper
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   3.5.0
 */
class Helper {
	/**
	 * Get title.
	 *
	 * @param int $order_id Order ID.
	 * @return string
	 */
	public static function get_title( $transaction_id ) {
		return \sprintf(
			/* translators: %s: Purchase of myCRED */
			__( 'Purchase of myCRED - {transaction_id}', 'knit-pay-lang' ),
			$transaction_id
		);
	}

	/**
	 * Get description.
	 *
	 * @return string
	 */
	public static function get_description( $prefs, Gateway $gateway ) {
		$description = $prefs['payment_description'];

		if ( empty( $description ) ) {
			$description = self::get_title( $gateway->transaction_id );
		}

		// Replacements.
		$replacements = [
			'{transaction_id}' => $gateway->transaction_id,
			'{point_count}'    => $gateway->amount,
		];

		$item_name = strtr( $description, $replacements );
		$item_name = $gateway->core->template_tags_general( $item_name );
		return $item_name;
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
	public static function get_customer( Gateway $gateway ) {
		$user = get_userdata( $gateway->buyer_id );
		return CustomerHelper::from_array(
			[
				'name'    => self::get_name( $user ),
				'email'   => self::get_value_from_object( $user, 'user_email' ),
				'phone'   => null,
				'user_id' => $gateway->buyer_id,
			]
		);
	}

	/**
	 * Get name from order.
	 *
	 * @return ContactName|null
	 */
	public static function get_name( $user ) {
		return ContactNameHelper::from_array(
			[
				'first_name' => self::get_value_from_object( $user, 'first_name' ),
				'last_name'  => self::get_value_from_object( $user, 'last_name' ),
			]
		);
	}

	/**
	 * Get address from order.
	 *
	 * @return Address|null
	 */
	public static function get_address( $gateway ) {
		$user = get_userdata( $gateway->buyer_id );

		return AddressHelper::from_array(
			[
				'name'  => self::get_name( $user ),
				'email' => self::get_value_from_object( $user, 'user_email' ),
			]
		);
	}
}
