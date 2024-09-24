<?php

namespace KnitPay\Extensions\SproutInvoices;

use SI_Invoice;
use Pronamic\WordPress\Pay\Address;
use Pronamic\WordPress\Pay\AddressHelper;
use Pronamic\WordPress\Pay\ContactName;
use Pronamic\WordPress\Pay\ContactNameHelper;
use Pronamic\WordPress\Pay\CustomerHelper;

/**
 * Title: Sprout Invoices Helper
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   5.8.0
 */
class Helper {
	/**
	 * Get title.
	 *
	 * @param int $order_id Order ID.
	 * @return string
	 */
	public static function get_title( $invoice ) {
		return \sprintf(
			/* translators: %s: Invoice */
			__( 'Invoice %s', 'knit-pay-lang' ),
			$invoice->get_id()
		);
	}

	/**
	 * Get description.
	 *
	 * @return string
	 */
	public static function get_description( SI_Invoice $invoice, $payment_description ) {
		$description = $payment_description;

		if ( empty( $description ) ) {
			$description = self::get_title( $invoice );
		}

		// Replacements.
		$replacements = [
			'{invoice_id}'   => $invoice->get_id(),
			'{invoice_name}' => $invoice->get_title(),
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

	private static function get_email( $invoice ) {
		$user = si_who_is_paying( $invoice );

		// User email or none.
		return ( $user ) ? $user->user_email : '';
	}

	private static function get_phone( $client ) {
		$phone = null;
		if ( is_a( $client, 'SI_Client' ) && ! empty( $client->get_phone() ) ) {
			$phone = $client->get_phone();
		}

		return $phone;
	}

	private static function get_address_from_client( $client ) {
		$address = [];
		if ( is_a( $client, 'SI_Client' ) ) {
			$address = $client->get_address();
		}

		return $address;
	}

	/**
	 * Get customer from order.
	 */
	public static function get_customer( SI_Invoice $invoice ) {

		$client = $invoice->get_client();

		return CustomerHelper::from_array(
			[
				'name'    => self::get_name( $client ),
				'email'   => self::get_email( $invoice ),
				'phone'   => self::get_phone( $client ),
				'user_id' => null,
			]
		);
	}

	/**
	 * Get name from order.
	 *
	 * @return ContactName|null
	 */
	public static function get_name( $client ) {
		if ( ! is_a( $client, 'SI_Client' ) ) {
			return ContactNameHelper::from_array(
				[
					'first_name' => ' ',
					'last_name'  => ' ',
				]
			);
		}

		$name       = $client->get_title();
		$last_name  = ( strpos( $name, ' ' ) === false ) ? '' : preg_replace( '#.*\s([\w-]*)$#', '$1', $name );
		$first_name = trim( preg_replace( '#' . preg_quote( $last_name, '#' ) . '#', '', $name ) );

		if ( empty( $first_name ) ) {
			$first_name = ' ';
		}
		if ( empty( $last_name ) ) {
			$last_name = ' ';
		}

		return ContactNameHelper::from_array(
			[
				'first_name' => $first_name,
				'last_name'  => $last_name,
			]
		);
	}

	/**
	 * Get address from order.
	 *
	 * @return Address|null
	 */
	public static function get_address( SI_Invoice $invoice ) {
		$client = $invoice->get_client();

		$address = self::get_address_from_client( $invoice );

		return AddressHelper::from_array(
			[
				'name'         => self::get_name( $client ),
				'line_1'       => self::get_value_from_array( $address, 'street' ),
				'postal_code'  => self::get_value_from_array( $address, 'postal_code' ),
				'city'         => self::get_value_from_array( $address, 'city' ),
				'country_code' => self::get_value_from_array( $address, 'country' ),
				'email'        => self::get_email( $invoice ),
				'phone'        => self::get_phone( $client ),
			]
		);
	}
}
