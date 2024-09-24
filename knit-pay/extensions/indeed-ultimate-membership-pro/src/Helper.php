<?php

namespace KnitPay\Extensions\IndeedUltimateMembershipPro;

use Pronamic\WordPress\Pay\Address;
use Pronamic\WordPress\Pay\AddressHelper;
use Pronamic\WordPress\Pay\ContactName;
use Pronamic\WordPress\Pay\ContactNameHelper;
use Pronamic\WordPress\Pay\CustomerHelper;
use WP_User;

/**
 * Title: Indeed Ultimate Membership Pro Helper
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   5.5.0
 */
class Helper {
	/**
	 * Get title.
	 *
	 * @param int $order_id Order ID.
	 * @return string
	 */
	public static function get_title( $payment_data ) {
		return \sprintf(
			/* translators: %s: Ultimate Membership Pro Order */
			__( 'Ultimate Membership Pro Order %s', 'knit-pay-lang' ),
			self::get_value_from_array( $payment_data, 'order_id' )
		);
	}

	/**
	 * Get description.
	 *
	 * @return string
	 */
	public static function get_description( $payment_data ) {
		$description = '{level_label}';// TODO

		if ( empty( $description ) ) {
			$description = self::get_title( $payment_data );
		}

		// Replacements.
		$replacements = [
			'{order_id}'            => self::get_value_from_array( $payment_data, 'order_id' ),
			'{level_id}'            => self::get_value_from_array( $payment_data, 'lid' ),
			'{level_label}'         => self::get_value_from_array( $payment_data, 'level_label' ),
			'{order_identificator}' => self::get_value_from_array( $payment_data, 'order_identificator' ),
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
	public static function get_customer( $payment_data ) {
		$user = new WP_User( $payment_data['uid'] );
		return CustomerHelper::from_array(
			[
				'name'    => self::get_name( $user ),
				'email'   => self::get_value_from_array( $payment_data, 'customer_email' ),
				'phone'   => $user->phone,
				'user_id' => $payment_data['uid'],
			]
		);
	}

	/**
	 * Get name from order.
	 *
	 * @return ContactName|null
	 */
	public static function get_name( WP_User $user ) {
		return ContactNameHelper::from_array(
			[
				'first_name' => $user->first_name,
				'last_name'  => $user->last_name,
			]
		);
	}

	/**
	 * Get address from order.
	 *
	 * @return Address|null
	 */
	public static function get_address( $payment_data ) {
		$user = new WP_User( $payment_data['uid'] );
		return AddressHelper::from_array(
			[
				'name'  => self::get_name( $user ),
				'email' => self::get_value_from_array( $payment_data, 'customer_email' ),
				'phone' => $user->phone,
			]
		);
	}
}
