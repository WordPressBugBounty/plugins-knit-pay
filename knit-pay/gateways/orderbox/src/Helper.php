<?php

namespace KnitPay\Gateways\OrderBox;

use Pronamic\WordPress\Money\Money;
use Pronamic\WordPress\Number\Number;
use Pronamic\WordPress\Pay\Address;
use Pronamic\WordPress\Pay\AddressHelper;
use Pronamic\WordPress\Pay\ContactName;
use Pronamic\WordPress\Pay\ContactNameHelper;
use Pronamic\WordPress\Pay\CustomerHelper;
use Pronamic\WordPress\Money\Currency;
use Pronamic\WordPress\Money\TaxedMoney;

/**
 * Title: Orderbox Helper
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 6.65.0.0
 * @since   6.65.0.0
 */
class Helper {

	private static function get_phone( $payment_data ) {
		return '+' . self::get_value_from_array( $payment_data, 'telNoCc' ) . self::get_value_from_array( $payment_data, 'telNo' );
	}

	/**
	 * Get value from array.
	 *
	 * @param array  $array Array.
	 * @param string $key   Key.
	 * @return string|null
	 */
	public static function get_value_from_array( $array, $key ) {
		if ( ! array_key_exists( $key, $array ) ) {
			return null;
		}

		return $array[ $key ];
	}

	/**
	 * Get customer from order.
	 */
	public static function get_customer( $payment_data ) {
		return CustomerHelper::from_array(
			[
				'name'    => self::get_name( $payment_data ),
				'email'   => self::get_value_from_array( $payment_data, 'emailAddr' ),
				'phone'   => self::get_phone( $payment_data ),
				'user_id' => null,
			]
		);
	}

	/**
	 * Get name from order.
	 *
	 * @return ContactName|null
	 */
	public static function get_name( $payment_data ) {
		$name = self::get_value_from_array( $payment_data, 'name' );
		
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
	public static function get_address( $payment_data ) {

		return AddressHelper::from_array(
			[
				'name'         => self::get_name( $payment_data ),
				'line_1'       => self::get_value_from_array( $payment_data, 'address1' ),
				'line_2'       => self::get_value_from_array( $payment_data, 'address2' ),
				'postal_code'  => self::get_value_from_array( $payment_data, 'zip' ),
				'city'         => self::get_value_from_array( $payment_data, 'city' ),
				'region'       => self::get_value_from_array( $payment_data, 'state' ),
				'country_code' => self::get_value_from_array( $payment_data, 'country' ),
				'email'        => self::get_value_from_array( $payment_data, 'emailAddr' ),
				'phone'        => self::get_phone( $payment_data ),
			]
		);
	}

	public static function get_payment_currency( $payment_data, $gateway_currency ) {
		if ( ! empty( $gateway_currency ) ) {
			return $gateway_currency;
		}

		return self::get_value_from_array( $payment_data, 'resellerCurrency' );
	}

	public static function get_amount( $payment_data, Config $config ) {
		// Currency.
		$currency = Currency::get_instance( self::get_payment_currency( $payment_data, $config->gateway_currency ) );

		// Exchange amount to gateway currency.
		$exchanged_amount = new TaxedMoney( self::get_exchanged_payment_amount( $payment_data, $config->exchange_rate ), $currency );

		// Add Transaction fees.
		return self::get_amount_with_transaction_fees( $exchanged_amount, $config->transaction_fees_percentage, $config->transaction_fees_fix );
	}

	private static function get_exchanged_payment_amount( $payment_data, $exchange_rate ) {
		$payment_amount = self::get_value_from_array( $payment_data, 'sellingcurrencyamount' );

		return $exchange_rate * $payment_amount;
	}

	private static function get_amount_with_transaction_fees( TaxedMoney $amount, $transaction_fees_percentage, $transaction_fees_fix ) {
		if ( empty( $transaction_fees_percentage ) && empty( $transaction_fees_fix ) ) {
			return $amount;
		}

		try {
			$transaction_fees_percentage = Number::from_string( $transaction_fees_percentage );
			if ( 59 < $transaction_fees_percentage->get_value() ) {
				throw new \Exception( 'The maximum allowed Transaction Fees Percentage is 59.' );
			}
			$transaction_fees_fix_amount = new Money( $transaction_fees_fix, $amount->get_currency() );
		} catch ( \Exception $e ) {
			throw new \Exception( 'Invalid Transaction Fees. ' . $e->getMessage() );
		}

		$transaction_fees_percentage_divide = ( new Number( 100 ) )->subtract( $transaction_fees_percentage )->divide( new Number( 100 ) );
		$amount                             = $amount->divide( $transaction_fees_percentage_divide ); // Amount after addition Transaction Fees Percentage.

		$amount = $amount->add( $transaction_fees_fix_amount ); // Amount after addition of Fix Transaction Fees.

		return $amount;
	}
}
