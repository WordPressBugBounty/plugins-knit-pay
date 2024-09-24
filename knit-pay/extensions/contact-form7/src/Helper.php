<?php

namespace KnitPay\Extensions\ContactForm7;

use Pronamic\WordPress\Pay\Address;
use Pronamic\WordPress\Pay\AddressHelper;
use Pronamic\WordPress\Pay\ContactName;
use Pronamic\WordPress\Pay\ContactNameHelper;
use Pronamic\WordPress\Pay\CustomerHelper;
use WPCF7R_Action_Knit_Pay;

/**
 * Title: Contact Form 7 Helper
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   5.60.0.0
 */
class Helper {
	/**
	 * Get title.
	 *
	 * @param int $order_id Order ID.
	 * @return string
	 */
	public static function get_title( $action, $unique_id ) {
		return sprintf(
			/* translators: %s: payment data title */
			__( 'Payment for %s', 'knit-pay-lang' ),
			sprintf(
				/* translators: %s: order id */
				__( 'Contact Form 7 Entry @ %s', 'knit-pay-lang' ),
				$unique_id
			)
		);
	}

	/**
	 * Get description.
	 *
	 * @return string
	 */
	public static function get_description( $action, $unique_id ) {
		$description = self::get_value_from_tag( $action, 'payment_description' );

		if ( empty( $description ) ) {
			$description = sprintf(
				/* translators: %s: payment number */
				__( 'Payment %s', 'knit-pay-lang' ),
				$unique_id
			);
		}

		// Replacements.
		$replacements = [];

		return strtr( $description, $replacements );
	}

	/**
	 * Get value from object.
	 *
	 * @param object $action WPCF7R_Action_Knit_Pay.
	 * @param string $tag   Key.
	 * @return string|null
	 */
	public static function get_value_from_tag( WPCF7R_Action_Knit_Pay $action, $tag ) {
		$value = $action->get( $tag );
		
		$value = $action->replace_tags( $value, [] );
		return $value;
	}

	/**
	 * Get customer from order.
	 */
	public static function get_customer( $action ) {
		return CustomerHelper::from_array(
			[
				'name'    => self::get_name( $action ),
				'email'   => self::get_value_from_tag( $action, 'buyer_email' ),
				'phone'   => self::get_value_from_tag( $action, 'buyer_phone' ),
				'user_id' => null,
			]
		);
	}

	/**
	 * Get name from order.
	 *
	 * @return ContactName|null
	 */
	public static function get_name( $action ) {
		return ContactNameHelper::from_array(
			[
				'first_name' => self::get_value_from_tag( $action, 'first_name' ),
				'last_name'  => self::get_value_from_tag( $action, 'last_name' ),
			]
		);
	}

	/**
	 * Get address from order.
	 *
	 * @return Address|null
	 */
	public static function get_address( $action ) {
		return AddressHelper::from_array(
			[
				'name'         => self::get_name( $action ),
				'line_1'       => self::get_value_from_tag( $action, 'billing_address_1' ),
				'line_2'       => self::get_value_from_tag( $action, 'billing_address_2' ),
				'postal_code'  => self::get_value_from_tag( $action, 'billing_zip' ),
				'city'         => self::get_value_from_tag( $action, 'billing_city' ),
				'region'       => self::get_value_from_tag( $action, 'billing_state' ),
				'country_code' => self::get_value_from_tag( $action, 'billing_country' ),
				'email'        => self::get_value_from_tag( $action, 'buyer_email' ),
				'phone'        => self::get_value_from_tag( $action, 'buyer_phone' ),
			]
		);
	}
}
