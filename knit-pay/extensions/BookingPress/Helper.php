<?php

namespace KnitPay\Extensions\BookingPress;

use Pronamic\WordPress\Pay\Address;
use Pronamic\WordPress\Pay\AddressHelper;
use Pronamic\WordPress\Pay\ContactName;
use Pronamic\WordPress\Pay\ContactNameHelper;
use Pronamic\WordPress\Pay\CustomerHelper;

/**
 * Title: BookingPress Helper
 * Description:
 * Copyright: 2020-2026 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   8.90.0.0
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
			/* translators: %s: BookingPress Entry */
			__( 'BookingPress Entry %s', 'knit-pay' ),
			$entry_id
		);
	}

	/**
	 * Get description.
	 *
	 * @return string
	 */
	public static function get_description( $bookingpress_return_data ) {
		global $BookingPress;

		$description = $BookingPress->bookingpress_get_settings( 'knit_pay_payment_description', 'payment_setting' );

		// Get service/package name
		$service_name = '';
		if ( isset( $bookingpress_return_data['service_data']['bookingpress_service_name'] ) ) {
			$service_name = $bookingpress_return_data['service_data']['bookingpress_service_name'];
		} elseif ( isset( $bookingpress_return_data['selected_package_details']['bookingpress_package_name'] ) ) {
			$service_name = $bookingpress_return_data['selected_package_details']['bookingpress_package_name'];
		}

		if ( empty( $description ) ) {
			$description = self::get_title( $bookingpress_return_data['entry_id'] );
		}

		// Replacements.
		$replacements = [
			'{entry_id}'     => $bookingpress_return_data['entry_id'],
			'{service_name}' => $service_name,
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
	private static function get_value_from_array( $array, $var ) {
		if ( isset( $array[ $var ] ) ) {
			return $array[ $var ];
		}
		return null;
	}

	/**
	 * Get customer from order.
	 */
	public static function get_customer_from_customer_details( $customer_details ) {
		return CustomerHelper::from_array(
			[
				'name'    => self::get_name_from_customer_detials( $customer_details ),
				'email'   => self::get_value_from_array( $customer_details, 'customer_email' ),
				'phone'   => self::get_value_from_array( $customer_details, 'customer_phone' ),
				'user_id' => null,
			]
		);
	}

	/**
	 * Get name from order.
	 *
	 * @return ContactName|null
	 */
	public static function get_name_from_customer_detials( $customer_details ) {
		return ContactNameHelper::from_array(
			[
				'first_name' => self::get_value_from_array( $customer_details, 'customer_firstname' ),
				'last_name'  => self::get_value_from_array( $customer_details, 'customer_lastname' ),
			]
		);
	}

	/**
	 * Get address from order.
	 *
	 * @return Address|null
	 */
	public static function get_address_from_customer_details( $customer_details ) {
		return AddressHelper::from_array(
			[
				'name'  => self::get_name_from_customer_detials( $customer_details ),
				'email' => self::get_value_from_array( $customer_details, 'customer_email' ),
				'phone' => self::get_value_from_array( $customer_details, 'customer_phone' ),
			]
		);
	}
}
