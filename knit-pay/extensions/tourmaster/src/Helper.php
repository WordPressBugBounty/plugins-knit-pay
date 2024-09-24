<?php

namespace KnitPay\Extensions\TourMaster;

use Pronamic\WordPress\Pay\Address;
use Pronamic\WordPress\Pay\AddressHelper;
use Pronamic\WordPress\Pay\ContactName;
use Pronamic\WordPress\Pay\ContactNameHelper;
use Pronamic\WordPress\Pay\CustomerHelper;

/**
 * Title: Tour Master Helper
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   2.1.0
 * @version 8.85.13.0
 */
class Helper {

	/**
	 * Get title.
	 *
	 * @param int $tid Tour ID.
	 * @return string
	 */
	public static function get_title( $tid ) {
		return sprintf(
			/* translators: %s: Tour Master Order */
			__( 'Tour Master Order %s', 'knit-pay-lang' ),
			$tid
		);
	}

	/**
	 * Get description.
	 *
	 * @param string $id Payment Method ID.
	 * @param int    $tid Tour ID.
	 * @return string
	 */
	public static function get_description( $id, $tid ) {
		$description = tourmaster_get_option( 'payment', $id . '-payment-description' );

		if ( empty( $description ) ) {
			$description = self::get_title( $tid );
		}

		$replacements = [
			'{order_id}' => $tid,
		];

		return strtr( $description, $replacements );
	}

	public static function get_amount( $tid ) {
		$t_data = apply_filters( 'goodlayers_payment_get_transaction_data', [], $tid, [ 'price' ] );

		if ( array_key_exists( 'deposit-price', $t_data['price'] ) && $t_data['price']['deposit-price'] ) {
			$price = $t_data['price']['deposit-price'];
		} else {
			$price = $t_data['price']['pay-amount'];
		}

		return $price;
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
	public static function get_customer( $billing_info ) {
		return CustomerHelper::from_array(
			[
				'name'    => self::get_name( $billing_info ),
				'email'   => self::get_value_from_object( $billing_info, 'email' ),
				'phone'   => self::get_value_from_object( $billing_info, 'phone' ),
				'user_id' => null,
			]
		);
	}

	/**
	 * Get name from order.
	 *
	 * @return ContactName|null
	 */
	public static function get_name( $billing_info ) {
		return ContactNameHelper::from_array(
			[
				'first_name' => self::get_value_from_object( $billing_info, 'first_name' ),
				'last_name'  => self::get_value_from_object( $billing_info, 'last_name' ),
			]
		);
	}

	/**
	 * Get address from order.
	 *
	 * @return Address|null
	 */
	public static function get_address( $billing_info ) {
		if ( empty( $billing_info ) ) {
			return;
		}

		$address = AddressHelper::from_array(
			[
				'name'   => self::get_name( $billing_info ),
				'line_1' => self::get_value_from_object( $billing_info, 'contact_address' ),
				'email'  => self::get_value_from_object( $billing_info, 'email' ),
				'phone'  => self::get_value_from_object( $billing_info, 'phone' ),
			]
		);
		$address->set_country_name( self::get_value_from_object( $billing_info, 'country' ) );
		return $address;
	}
}
