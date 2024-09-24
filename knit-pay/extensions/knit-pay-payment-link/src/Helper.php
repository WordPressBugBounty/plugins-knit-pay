<?php

namespace KnitPay\Extensions\KnitPayPaymentLink;

use Pronamic\WordPress\Pay\Address;
use Pronamic\WordPress\Pay\AddressHelper;
use Pronamic\WordPress\Pay\ContactName;
use Pronamic\WordPress\Pay\ContactNameHelper;
use Pronamic\WordPress\Pay\CustomerHelper;
use Pronamic\WordPress\Pay\Payments\Payment;

/**
 * Title: Knit Pay - Payment Link Helper
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   4.6.0
 */
class Helper {
	/**
	 * Get title.
	 *
	 * @param Payment $payment
	 * @return string
	 */
	public static function get_title( Payment $payment ) {
		return \sprintf(
			/* translators: %s: Payment Link  */
			__( 'Payment Link %s', 'knit-pay-lang' ),
			$payment->get_order_id()
		);
	}

	/**
	 * Get description.
	 *
	 * @param Payment $payment
	 * @return string
	 */
	public static function get_description( Payment $payment ) {
		$description = \sanitize_text_field( $_GET['payment_description'] );

		if ( empty( $description ) ) {
			$description = self::get_title( $payment );
		}

		return $description;
	}

	/**
	 * Get customer from order.
	 */
	public static function get_customer() {
		return CustomerHelper::from_array(
			[
				'name'    => self::get_name(),
				'email'   => filter_input( INPUT_GET, 'customer_email', FILTER_SANITIZE_EMAIL ),
				'phone'   => \sanitize_text_field( $_GET['customer_phone'] ),
				'user_id' => null,
			]
		);
	}

	/**
	 * Get name from order.
	 *
	 * @return ContactName|null
	 */
	public static function get_name() {
		$name       = \sanitize_text_field( $_GET['customer_name'] );
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
	public static function get_address() {
		return AddressHelper::from_array(
			[
				'name'  => self::get_name(),
				'email' => filter_input( INPUT_GET, 'customer_email', FILTER_SANITIZE_EMAIL ),
				'phone' => \sanitize_text_field( $_GET['customer_phone'] ),
			]
		);
	}
}
