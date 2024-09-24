<?php

namespace KnitPay\Extensions\EngineThemes;

use Pronamic\WordPress\Pay\Address;
use Pronamic\WordPress\Pay\AddressHelper;
use Pronamic\WordPress\Pay\ContactName;
use Pronamic\WordPress\Pay\ContactNameHelper;
use Pronamic\WordPress\Pay\CustomerHelper;
use ET_Order;

/**
 * Title: Engine Themes Helper
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   4.7.0
 */
class Helper {
	/**
	 * Get title.
	 *
	 * @param ET_Order $order ET_Order.
	 * @return string
	 */
	public static function get_title( $order ) {
		return \sprintf(
			/* translators: %s: Engine Themes Order */
			__( 'Engine Themes Order %s', 'knit-pay-lang' ),
			$order['ID']
		);
	}

	/**
	 * Get description.
	 *
	 * @return string
	 */
	public static function get_description( $order, $setting ) {
		$description = $setting['payment_description'];

		if ( empty( $description ) ) {
			$description = self::get_title( $order );
		}

		// Replacements.
		$replacements = [
			'{order_id}' => $order['ID'],
		];

		return strtr( $description, $replacements );
	}

	/**
	 * Get customer from order.
	 */
	public static function get_customer( $payer ) {
		return CustomerHelper::from_array(
			[
				'name'    => self::get_name( $payer ),
				'email'   => $payer->user_email,
				'phone'   => null,
				'user_id' => $payer->ID,
			]
		);
	}

	/**
	 * Get name from order.
	 *
	 * @return ContactName|null
	 */
	public static function get_name( $payer ) {
		return ContactNameHelper::from_array(
			[
				'first_name' => get_user_meta( $payer->ID, 'first_name', true ),
				'last_name'  => get_user_meta( $payer->ID, 'last_name', true ),
			]
		);
	}

	/**
	 * Get address from order.
	 *
	 * @return Address|null
	 */
	public static function get_address( $payer ) {
		return AddressHelper::from_array(
			[
				'name'  => self::get_name( $payer ),
				'email' => $payer->user_email,
				'phone' => null,
			]
		);
	}
}
