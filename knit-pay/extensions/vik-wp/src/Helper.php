<?php

namespace KnitPay\Extensions\VikWP;

use Pronamic\WordPress\Pay\Address;
use Pronamic\WordPress\Pay\AddressHelper;
use Pronamic\WordPress\Pay\ContactName;
use Pronamic\WordPress\Pay\ContactNameHelper;
use Pronamic\WordPress\Pay\CustomerHelper;

/**
 * Title: Vik WP Helper
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   6.69.0.0
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
			/* translators: %s: Vik Order */
			__( 'Vik Order %s', 'knit-pay-lang' ),
			$order_id
		);
	}

	/**
	 * Get description.
	 *
	 * @return string
	 */
	public static function get_description( AbstractKnitPayPayment $gateway ) {
		$description = $gateway->getParam( 'payment_description' );

		if ( empty( $description ) ) {
			$description = self::get_title( $gateway->get( 'id' ) );
		}

		// Replacements.
		$replacements = [
			'{order_id}'         => $gateway->get( 'id' ),
			'{room_name}'        => $gateway->get( 'rooms_name' ),
			'{transaction_name}' => $gateway->get( 'transaction_name' ),
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
	
	private static function get_customer_data( $customer_data ) {
		$customer_details = [];
		$custdata_parts   = explode( "\n", $customer_data );
		foreach ( $custdata_parts as $custdet ) {
			$custdet_parts = explode( ':', $custdet );
			if ( empty( $custdet_parts[0] ) || empty( $custdet_parts[1] ) ) {
				continue;
			}
			$customer_details[ $custdet_parts[0] ] = $custdet_parts[1];
		}
		return $customer_details;
	}

	/**
	 * Get customer from order.
	 */
	public static function get_customer_from_order( AbstractKnitPayPayment $gateway ) {
		$customer_details = self::get_customer_data( $gateway->get( 'custdata' ) );
		return CustomerHelper::from_array(
			[
				'name'    => self::get_name_from_order( $customer_details ),
				'email'   => $gateway->get( 'custmail' ),
				'phone'   => $gateway->get( 'phone' ),
				'user_id' => null,
			]
		);
	}

	/**
	 * Get name from order.
	 *
	 * @return ContactName|null
	 */
	public static function get_name_from_order( $customer_details ) {
		return ContactNameHelper::from_array(
			[
				'first_name' => self::get_value_from_array( $customer_details, 'Name' ),
				'last_name'  => self::get_value_from_array( $customer_details, 'Last Name' ),
			]
		);
	}

	/**
	 * Get address from order.
	 *
	 * @return Address|null
	 */
	public static function get_address_from_order( $gateway ) {
		$customer_details = self::get_customer_data( $gateway->get( 'custdata' ) );
		return AddressHelper::from_array(
			[
				'name'        => self::get_name_from_order( $customer_details ),
				'line_1'      => self::get_value_from_array( $customer_details, 'Company Name' ),
				'line_2'      => self::get_value_from_array( $customer_details, 'Address' ),
				'postal_code' => self::get_value_from_array( $customer_details, 'Zip Code' ),
				'city'        => self::get_value_from_array( $customer_details, 'City' ),
				'email'       => $gateway->get( 'custmail' ),
				'phone'       => $gateway->get( 'phone' ),
			]
		);
	}
}
