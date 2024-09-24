<?php
namespace KnitPay\Extensions\PaidMembershipsPro;

use Pronamic\WordPress\Pay\Address;
use Pronamic\WordPress\Pay\AddressHelper;
use Pronamic\WordPress\Pay\ContactName;
use Pronamic\WordPress\Pay\ContactNameHelper;
use Pronamic\WordPress\Pay\CustomerHelper;
use Pronamic\WordPress\Money\Currency;
use Pronamic\WordPress\Money\Money;
use MemberOrder;

/**
 * Title: Paid Memberships Pro Helper
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author knitpay
 * @since 2.0.0
 */
class Helper {

	/**
	 * Get title.
	 *
	 * @param int $order_id Order ID.
	 * @return string
	 */
	public static function get_title( $order_id ) {
		/* translators: %s: Paid Memberships Pro */
		return sprintf( __( 'Paid Memberships Pro %s', 'knit-pay-lang' ), $order_id );
	}

	/**
	 * Get description.
	 *
	 * @param string      $payment_method Payment Method.
	 * @param MemberOrder $morder Member Order.
	 * @return string
	 */
	public static function get_description( $payment_method, $morder ) {
		$description = pmpro_getOption( $payment_method . '_payment_description' );
		if ( empty( $description ) ) {
			$description = self::get_title( $morder->id );
		}

		// Replacements.
		$replacements = [
			'{order_id}'        => $morder->id,
			'{code}'            => $morder->code,
			'{invoice_id}'      => $morder->code,
			'{membership_name}' => $morder->membership_name,
		];

		return strtr( $description, $replacements );
	}

	public static function get_amount( $morder ) {
		global $pmpro_currency;

		$initial_payment     = $morder->InitialPayment;
		$initial_payment_tax = $morder->getTaxForPrice( $initial_payment );
		$initial_payment     = \pmpro_round_price( (float) $initial_payment + (float) $initial_payment_tax );

		// Currency.
		$currency = Currency::get_instance( $pmpro_currency );

		$amount = new Money( $initial_payment, $currency, null, $initial_payment_tax );

		return $amount;
	}

	/**
	 * Get email.
	 *
	 * @return string|null
	 */
	public static function get_email( $morder ) {
		if ( isset( $morder->Email ) ) {
			return $morder->Email;
		}
		if ( isset( $_POST['bemail'] ) ) {
			return $_POST['bemail'];
		}
		return null;
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
	public static function get_customer( $morder ) {
		if ( isset( $morder->billing ) ) {
			$billing = $morder->billing;
		}

		return CustomerHelper::from_array(
			[
				'name'    => self::get_name( $morder ),
				'email'   => self::get_email( $morder ),
				'phone'   => self::get_value_from_object( $billing, 'phone' ),
				'user_id' => null,
			]
		);
	}

	/**
	 * Get name from order.
	 *
	 * @return ContactName|null
	 */
	public static function get_name( $morder ) {
		return ContactNameHelper::from_array(
			[
				'first_name' => self::get_value_from_object( $morder, 'FirstName' ),
				'last_name'  => self::get_value_from_object( $morder, 'LastName' ),
			]
		);
	}

	/**
	 * Get address from order.
	 *
	 * @return Address|null
	 */
	public static function get_address( MemberOrder $morder ) {
		if ( isset( $morder->billing ) ) {
			$billing = $morder->billing;
		}

		return AddressHelper::from_array(
			[
				'name'         => self::get_name( $morder ),
				'line_1'       => self::get_value_from_object( $billing, 'street' ),
				'line_2'       => null,
				'postal_code'  => self::get_value_from_object( $billing, 'zip' ),
				'city'         => self::get_value_from_object( $billing, 'city' ),
				'region'       => self::get_value_from_object( $billing, 'state' ),
				'country_code' => self::get_value_from_object( $billing, 'country' ),
				'email'        => self::get_email( $morder ),
				'phone'        => self::get_value_from_object( $billing, 'phone' ),
			]
		);
	}

}
