<?php

namespace KnitPay\Extensions\LatePoint;

use Pronamic\WordPress\Pay\Address;
use Pronamic\WordPress\Pay\AddressHelper;
use Pronamic\WordPress\Pay\ContactName;
use Pronamic\WordPress\Pay\ContactNameHelper;
use Pronamic\WordPress\Pay\CustomerHelper;
use OsSettingsHelper;
use OsCustomerModel;

/**
 * Title: LatePoint Helper
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   4.4.0
 */
class Helper {
	/**
	 * Get title.
	 *
	 * @param int $generated_form_id generated_form_id.
	 * @return string
	 */
	public static function get_title( $generated_form_id ) {
		return \sprintf(
			/* translators: %s: LatePoint Booking */
			__( 'LatePoint Booking %s', 'knit-pay-lang' ),
			$generated_form_id
		);
	}

	/**
	 * Get description.
	 *
	 * @return string
	 */
	public static function get_description( $booking ) {
		$description = OsSettingsHelper::get_settings_value( 'knit_pay_payment_description' );

		if ( empty( $description ) ) {
			$description = self::get_title( $booking->generated_form_id );
		}

		// Replacements.
		$replacements = [
			'{service_name}'      => $booking->service->name,
			'{agent_name}'        => $booking->agent->full_name,
			'{generated_form_id}' => $booking->generated_form_id,
		];

		return strtr( $description, $replacements );
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
	public static function get_customer( OsCustomerModel $customer ) {
		return CustomerHelper::from_array(
			[
				'name'    => self::get_name( $customer ),
				'email'   => self::get_value_from_object( $customer, 'email' ),
				'phone'   => self::get_value_from_object( $customer, 'phone' ),
				'user_id' => $customer->id,
			]
		);
	}

	/**
	 * Get name from order.
	 *
	 * @return ContactName|null
	 */
	public static function get_name( OsCustomerModel $customer ) {
		return ContactNameHelper::from_array(
			[
				'first_name' => self::get_value_from_object( $customer, 'first_name' ),
				'last_name'  => self::get_value_from_object( $customer, 'last_name' ),
			]
		);
	}

	/**
	 * Get address from order.
	 *
	 * @return Address|null
	 */
	public static function get_address( $customer ) {
		return AddressHelper::from_array(
			[
				'name'  => self::get_name( $customer ),
				'email' => self::get_value_from_object( $customer, 'email' ),
				'phone' => self::get_value_from_object( $customer, 'phone' ),
			]
		);
	}
}
