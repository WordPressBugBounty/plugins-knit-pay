<?php

namespace KnitPay\Extensions\TeamBooking;

use Pronamic\WordPress\Pay\Address;
use Pronamic\WordPress\Pay\AddressHelper;
use Pronamic\WordPress\Pay\ContactName;
use Pronamic\WordPress\Pay\ContactNameHelper;
use Pronamic\WordPress\Pay\CustomerHelper;
use TeamBooking_ReservationData;

/**
 * Title: Team Booking Helper
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   5.1.0
 */
class Helper {
	/**
	 * Get title.
	 *
	 * @param int $order_id Order ID.
	 * @return string
	 */
	public static function get_title( $reservation_id ) {
		return \sprintf(
			/* translators: %s: Team Booking Reservation */
			__( 'Team Booking Reservation %s', 'knit-pay-lang' ),
			$reservation_id
		);
	}

	/**
	 * Get description.
	 *
	 * @return string
	 */
	public static function get_description( Gateway $team_booking_gateway, TeamBooking_ReservationData $reservation_data ) {
		$description = $team_booking_gateway->getPaymentDescription();

		if ( empty( $description ) ) {
			$description = self::get_title( $reservation_data->getDatabaseId() );
		}

		// Replacements.
		$replacements = [
			'{reservation_id}' => $reservation_data->getDatabaseId(),
			'{service_name}'   => $reservation_data->getServiceName(),
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
	private static function get_value_from_form_fileds( $form_fields, $field_name ) {
		$return = '';
		foreach ( $form_fields as $field ) {
			if ( $field->getName() === $field_name ) {
				$return = $field->getValue();
			}
		}
		return $return;
	}

	/**
	 * Get customer from order.
	 */
	public static function get_customer( $reservation_data ) {
		return CustomerHelper::from_array(
			[
				'name'    => self::get_name( $reservation_data ),
				'email'   => $reservation_data->getCustomerEmail(),
				'phone'   => $reservation_data->getCustomerPhone(),
				'user_id' => null,
			]
		);
	}

	/**
	 * Get name from order.
	 *
	 * @return ContactName|null
	 */
	public static function get_name( $reservation_data ) {
		return ContactNameHelper::from_array(
			[
				'first_name' => self::get_value_from_form_fileds( $reservation_data->getFormFields(), 'first_name' ),
				'last_name'  => self::get_value_from_form_fileds( $reservation_data->getFormFields(), 'second_name' ),
			]
		);
	}

	/**
	 * Get address from order.
	 *
	 * @return Address|null
	 */
	public static function get_address( $reservation_data ) {
		return AddressHelper::from_array(
			[
				'name'   => self::get_name( $reservation_data ),
				'line_1' => $reservation_data->getCustomerAddress(),
				'email'  => $reservation_data->getCustomerEmail(),
				'phone'  => $reservation_data->getCustomerPhone(),
			]
		);
	}
}
