<?php

namespace KnitPay;

use Pronamic\WordPress\Pay\Payments\Payment;
use KnitPay\Gateways\Gateway;
use Exception;

/**
 * Title: Knit Pay Currency Converter
 * Copyright: 2020-2026 Knit Pay
 *
 * @author Knit Pay
 */
class CurrencyConverter {
	/**
	 * Convert Unsupported Currency to Supported Currency.
	 *
	 * @param Payment $payment The payment.
	 * @return Payment
	 */
	public static function converted_unsuppored_currency( Payment $payment ) {
		$payment_currency = $payment->get_total_amount()->get_currency()->get_alphabetic_code();
		
		/** @var Gateway $gateway */
		$gateway = $payment->get_gateway();

		// Check if gateway is an instance of KnitPay Gateway with currency conversion support
		if ( property_exists( $gateway, 'supported_currencies' )
			&& property_exists( $gateway, 'default_currency' )
			&& method_exists( $gateway, 'change_currency' )
			&& 'auto_free' === get_option( 'knit_pay_currency_exchange_mode', 'auto_free' )
			&& ! empty( $gateway->supported_currencies )
			&& ! in_array( $payment_currency, $gateway->supported_currencies ) ) {
				$currency_exchange_rate = self::get_currency_exchange_rate( $payment_currency, $gateway->default_currency );
				$gateway->change_currency( $payment, $gateway->default_currency, $currency_exchange_rate );
		}

		return $payment;
	}
	
	/**
	 * Fetches the latest currency conversion rate using multiple fallback APIs.
	 *
	 * @param string $base Base currency code (e.g., "USD")
	 * @param string $to   Target currency code (e.g., "EUR")
	 * @return float|Exception The conversion rate as a float, or false if all APIs fail.
	 */
	private static function get_currency_exchange_rate( $base, $to ) {
		$base      = strtoupper( $base );
		$to        = strtoupper( $to );
		$baseLower = strtolower( $base );
		$toLower   = strtolower( $to );

		// Define APIs in order of priority
		$apis = [
			// 1. Frankfurter (Official/Stable)
			[
				'url'    => "https://api.frankfurter.dev/v1/latest?base={$base}&symbols={$to}",
				'parser' => function ( $data ) use ( $to ) {
					return $data['rates'][ $to ] ?? null;
				},
			],
			// 2. fawazahmed0 (CDN Version)
			[
				'url'    => "https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/{$baseLower}.json",
				'parser' => function ( $data ) use ( $baseLower, $toLower ) {
					return $data[ $baseLower ][ $toLower ] ?? null;
				},
			],
			// 3. fawazahmed0 (Pages/Cloudflare Version)
			[
				'url'    => "https://latest.currency-api.pages.dev/v1/currencies/{$baseLower}.json",
				'parser' => function ( $data ) use ( $baseLower, $toLower ) {
					return $data[ $baseLower ][ $toLower ] ?? null;
				},
			],
			// 4. VATComply (No Key Required)
			[
				'url'    => "https://api.vatcomply.com/rates?base={$base}",
				'parser' => function ( $data ) use ( $to ) {
					return $data['rates'][ $to ] ?? null;
				},
			],
			// 5. AwesomeAPI (Great for Crypto/Fiat pairs)
			[
				'url'    => "https://economia.awesomeapi.com.br/json/last/{$base}-{$to}",
				'parser' => function ( $data ) use ( $base, $to ) {
					$key = $base . $to;
					return $data[ $key ]['bid'] ?? null;
				},
			],
		];

		foreach ( $apis as $api ) {
			$response = wp_remote_get( $api['url'] );

			if ( wp_remote_retrieve_response_code( $response ) === 200 ) {
				$data = json_decode( wp_remote_retrieve_body( $response ), true );
				if ( $data ) {
					$rate = $api['parser']( $data );
					if ( $rate !== null ) {
						return (float) $rate;
					}
				}
			}
		}

		throw new Exception( 'Could not fetch the currency exchange rate from any source.' );
	}
}

\add_action( 'pronamic_pay_pre_create_payment', [ '\KnitPay\CurrencyConverter', 'converted_unsuppored_currency' ] );
