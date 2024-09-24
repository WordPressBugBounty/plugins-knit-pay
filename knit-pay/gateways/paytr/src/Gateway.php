<?php
namespace KnitPay\Gateways\Paytr;

use KnitPay\Gateways\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Payments\Payment;

/**
 * Title: PayTR Gateway
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 8.86.0.0
 * @since 8.86.0.0
 */
class Gateway extends Core_Gateway {
	private $test_mode;
	private $config;
	private $api;

	/**
	 * Initializes an PayTR gateway
	 *
	 * @param Config $config
	 *            Config.
	 */
	public function init( Config $config ) {
		$this->set_method( self::METHOD_HTTP_REDIRECT );

		$this->config = $config;

		$this->test_mode = 0;
		if ( self::MODE_TEST === $this->mode ) {
			$this->test_mode = 1;
		}
	}

	/**
	 * Start.
	 *
	 * @see Core_Gateway::start()
	 *
	 * @param Payment $payment
	 *            Payment.
	 */
	public function start( Payment $payment ) {
		$paytr_order_id = uniqid() . uniqid();
		$payment->set_transaction_id( $paytr_order_id );

		$api_client      = new Client();
		$paytr_api_token = $api_client->get_token( $this->get_payment_data( $payment ) );

		$payment->set_action_url( 'https://www.paytr.com/odeme/guvenli/' . $paytr_api_token );
	}

	/**
	 * Get Payment Data.
	 *
	 * @param Payment $payment
	 *            Payment.
	 *
	 * @return array
	 */
	private function get_payment_data( Payment $payment ) {
		$merchant_id   = $this->config->merchant_id;
		$merchant_key  = $this->config->merchant_key;
		$merchant_salt = $this->config->merchant_salt;

		$merchant_oid    = $payment->get_transaction_id();
		$customer        = $payment->get_customer();
		$billing_address = $payment->get_billing_address();

		$payment_amount = $payment->get_total_amount()->get_minor_units()->format( 0, '.', '' );
		$currency       = $payment->get_total_amount()->get_currency()->get_alphabetic_code();

		$user_name      = substr( trim( ( html_entity_decode( $customer->get_name(), ENT_QUOTES, 'UTF-8' ) ) ), 0, 60 );
		$customer_email = $customer->get_email();
		$user_ip        = $customer->get_ip_address();

		$user_phone = '';
		if ( ! empty( $billing_address ) && ! empty( $billing_address->get_phone() ) ) {
			$user_phone = $billing_address->get_phone();
		}

		$no_installment  = 1;
		$max_installment = 0;
		$test_mode       = $this->test_mode;
		$debug_on        = pronamic_pay_plugin()->is_debug_mode();

		$user_basket = base64_encode(
			json_encode(
				[
					[ $payment->get_description(), $payment->get_total_amount()->number_format( null, '.', '' ), 1 ],
				]
			)
		);

		$hash_str    = $merchant_id . $user_ip . $merchant_oid . $customer_email . $payment_amount . $user_basket . $no_installment . $max_installment . $currency . $test_mode;
		$paytr_token = base64_encode( hash_hmac( 'sha256', $hash_str . $merchant_salt, $merchant_key, true ) );

		// @see https://dev.paytr.com/en/iframe-api/iframe-api-1-adim
		return [
			'merchant_id'       => $merchant_id,
			'user_ip'           => $user_ip,
			'merchant_oid'      => $merchant_oid,
			'email'             => $customer_email,
			'payment_amount'    => $payment_amount,
			'paytr_token'       => $paytr_token,
			'user_basket'       => $user_basket,
			'debug_on'          => $debug_on,
			'no_installment'    => $no_installment,
			'max_installment'   => $max_installment,
			'user_name'         => $user_name,
			'user_address'      => (string) $billing_address,
			'user_phone'        => $user_phone,
			'merchant_ok_url'   => $payment->get_return_url(),
			'merchant_fail_url' => $payment->get_return_url(),
			'currency'          => $currency,
			'test_mode'         => $test_mode,
			'lang'              => $customer->get_language(),
		];
	}
}
