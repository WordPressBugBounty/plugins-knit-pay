<?php

namespace KnitPay\Extensions\Camptix;

use Pronamic\WordPress\Pay\Address;
use Pronamic\WordPress\Pay\AddressHelper;
use Pronamic\WordPress\Pay\ContactName;
use Pronamic\WordPress\Pay\ContactNameHelper;
use Pronamic\WordPress\Pay\CustomerHelper;

/**
 * Title: CampTix Helper
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   8.74.0.0
 */
class Helper {
	/**
	 * Get title.
	 *
	 * @param int $attendee_id Attendee ID.
	 * @return string
	 */
	public static function get_title( $attendee_id ) {
		return \sprintf(
			/* translators: %s: Ticket Booking */
			__( 'Ticket Booking %s', 'knit-pay-lang' ),
			$attendee_id
		);
	}

	/**
	 * Get description.
	 *
	 * @return string
	 */
	public static function get_description( $gateway, $order ) {
		$description = $gateway->camptix_options[ 'payment_options_' . $gateway->id ]['payment_description'];
		
		if ( empty( $description ) ) {
			$description = self::get_title( $order['attendee_id'] );
		}
		
		$tickets      = $order['items'];
		$ticket_names = [];
		foreach ( $tickets as $ticket ) {
			$ticket_names[] = $ticket['name'];
		}

		// Replacements.
		$replacements = [
			'{attendee_id}'       => $order['attendee_id'],
			'{event_name}'        => $gateway->camptix_options['event_name'],
			'{first_ticket_name}' => reset( $ticket_names ),
			'{ticket_names}'      => implode( ', ', $ticket_names ),
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
	private static function get_value_from_array( $array, $var ) {
		if ( isset( $array[ $var ] ) ) {
			return reset( $array[ $var ] );
		}
		return null;
	}

	/**
	 * Get customer from order.
	 */
	public static function get_customer_from_attendee_detail( $attendee_detail ) {
		return CustomerHelper::from_array(
			[
				'name'    => self::get_name_from_attendee_detail( $attendee_detail ),
				'email'   => self::get_value_from_array( $attendee_detail, 'tix_receipt_email' ),
				// 'phone'   => self::get_value_from_array( $attendee_detail, 'tix_receipt_phone' ),
				'user_id' => null,
			]
		);
	}

	/**
	 * Get name from order.
	 *
	 * @return ContactName|null
	 */
	public static function get_name_from_attendee_detail( $attendee_detail ) {
		return ContactNameHelper::from_array(
			[
				'first_name' => self::get_value_from_array( $attendee_detail, 'tix_first_name' ),
				'last_name'  => self::get_value_from_array( $attendee_detail, 'tix_last_name' ),
			]
		);
	}

	/**
	 * Get address from order.
	 *
	 * @return Address|null
	 */
	public static function get_address_from_attendee_detail( $attendee_detail ) {
		return AddressHelper::from_array(
			[
				'name'  => self::get_name_from_attendee_detail( $attendee_detail ),
				'email' => self::get_value_from_array( $attendee_detail, 'tix_receipt_email' ),
				// 'phone'        => self::get_value_from_array( $attendee_detail, 'tix_receipt_email' ),
			]
		);
	}
}
