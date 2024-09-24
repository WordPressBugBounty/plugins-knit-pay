<?php

namespace KnitPay\Extensions\ProfilePress;

use ProfilePress\Core\Membership\Models\Customer\CustomerEntity;
use ProfilePress\Core\Membership\Models\Order\OrderEntity;
use Pronamic\WordPress\Pay\Address;
use Pronamic\WordPress\Pay\AddressHelper;
use Pronamic\WordPress\Pay\ContactName;
use Pronamic\WordPress\Pay\ContactNameHelper;
use Pronamic\WordPress\Pay\CustomerHelper;

/**
 * Title: ProfilePress Helper
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   8.79.0.0
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
			/* translators: %s: ProfilePress Order */
			__( 'ProfilePress Order %s', 'knit-pay-lang' ),
			$order_id
		);
	}

	/**
	 * Get description.
	 *
	 * @return string
	 */
	public static function get_description( $gateway, OrderEntity $order ) {
		$description = $gateway->get_value( 'payment_description' );

		if ( empty( $description ) ) {
			$description = self::get_title( $order->get_id() );
		}

		// Replacements.
		$replacements = [
			'{order_id}'  => $order->get_id(),
			'{plan_name}' => $order->get_plan()->get_name(),
		];

		return strtr( $description, $replacements );
	}

	/**
	 * Get customer from order.
	 */
	public static function get_customer( CustomerEntity $customer ) {
		return CustomerHelper::from_array(
			[
				'name'    => self::get_name_from_customer( $customer ),
				'email'   => $customer->get_email(),
				'phone'   => $customer->get_billing_details( 'ppress_billing_phone' ),
				'user_id' => $customer->get_id(),
			]
		);
	}

	/**
	 * Get name from order.
	 *
	 * @return ContactName|null
	 */
	public static function get_name_from_customer( CustomerEntity $customer ) {
		return ContactNameHelper::from_array(
			[
				'first_name' => $customer->get_first_name(),
				'last_name'  => $customer->get_last_name(),
			]
		);
	}

	/**
	 * Get address from order.
	 *
	 * @return Address|null
	 */
	public static function get_address_from_customer( CustomerEntity $customer ) {
		return AddressHelper::from_array(
			[
				'name'         => self::get_name_from_customer( $customer ),
				'line_1'       => $customer->get_billing_details( 'ppress_billing_address' ),
				'postal_code'  => $customer->get_billing_details( 'ppress_billing_postcode' ),
				'city'         => $customer->get_billing_details( 'ppress_billing_city' ),
				'region'       => $customer->get_billing_details( 'ppress_billing_state' ),
				'country_code' => $customer->get_billing_details( 'ppress_billing_country' ),
				'email'        => $customer->get_email(),
				'phone'        => $customer->get_billing_details( 'ppress_billing_phone' ),
			]
		);
	}
}
