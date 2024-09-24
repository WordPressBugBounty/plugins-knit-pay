<?php

namespace KnitPay\Extensions\WPTravelEngine;

use Pronamic\WordPress\Money\Currency;
use Pronamic\WordPress\Money\Money;
use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Payments\Payment;

/**
 * Title: WP Travel Engine extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   1.9
 */

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit();

class Gateway {
	private $knit_pay_settings;
	protected $config_id;
	protected $payment_description;

	/**
	 * @var string
	 */
	public $id = 'knit_pay';

	/**
	 * Payment method.
	 *
	 * @var string
	 */
	private $payment_method;

	/**
	 * Bootstrap
	 *
	 * @param array $args Gateway properties.
	 */
	public function __construct( /* $args */ ) {
		// Get settings.
		$wp_travel_engine_settings = get_option( 'wp_travel_engine_settings' );
		$this->knit_pay_settings   = isset( $wp_travel_engine_settings['knit_pay_settings'] ) ? $wp_travel_engine_settings['knit_pay_settings'] : [];

		// Update the configuration format if old configuration exists
		// @since 6.67.4.0
		// TODO Remove this block after 2024
		if ( ! empty( $wp_travel_engine_settings['knit_pay_config_id'] ) ) {
			$this->knit_pay_settings['config_id']           = $wp_travel_engine_settings['knit_pay_config_id'];
			$wp_travel_engine_settings['knit_pay_settings'] = $this->knit_pay_settings;
			unset( $wp_travel_engine_settings['knit_pay_config_id'] );
			update_option( 'wp_travel_engine_settings', $wp_travel_engine_settings );
		}

		add_action( 'wte_payment_gateway_knit_pay', [ $this, 'process' ], 12, 3 );
	}

	public function process( $wte_payment_id, $type, $gateway ) {
		if ( ! $wte_payment_id ) {
			return;
		}

		$booking_id = get_post_meta( $wte_payment_id, 'booking_id', ! 0 );

		if ( ! $booking_id ) {
			return;
		}

		// TODO hardcoded knit_pay for now. remove it
		// Check if knit_pay is selected.
		if ( ! isset( $_POST['wpte_checkout_paymnet_method'] ) || 'knit_pay' !== $_POST['wpte_checkout_paymnet_method'] ) {
			return;
		}

		$config_id      = ! empty( $this->knit_pay_settings['config_id'] ) ? $this->knit_pay_settings['config_id'] : get_option( 'pronamic_pay_config_id' );
		$payment_method = $this->id;

		$gateway = Plugin::get_gateway( $config_id );

		if ( ! $gateway ) {
			return false;
		}

		$booking_details = get_post_meta( $booking_id, 'wp_travel_engine_booking_setting', true )['place_order']['booking'];
		$wte_payment     = get_post( $wte_payment_id );

		/**
		 * Build payment.
		 */
		$payment = new Payment();

		$payment->source    = 'wp-travel-engine';
		$payment->source_id = $booking_id;
		$payment->order_id  = $booking_id;

		$payment->set_description( Helper::get_description( $booking_id, $this ) );

		$payment->title = Helper::get_title( $booking_id );

		// Customer.
		$payment->set_customer( Helper::get_customer( $booking_details ) );

		// Address.
		$payment->set_billing_address( Helper::get_address( $booking_details ) );

		// Currency.
		$currency = Currency::get_instance( \wp_travel_engine_get_currency_code() );

		// Amount.
		$payment->set_total_amount( new Money( $wte_payment->payable['amount'], $currency ) );

		// Method.
		$payment->set_payment_method( $payment_method );

		// Configuration.
		$payment->config_id = $config_id;

		try {
			$payment = Plugin::start_payment( $payment );

			// Set Payment ID (only in WP Travel Engine).
			$payment->set_meta( 'wte_payment_id', $wte_payment_id );
			$payment->save();

			wp_redirect( $payment->get_pay_redirect_url() );
			exit;
		} catch ( \Exception $e ) {
			WTE()->notices->add( Plugin::get_default_error_message(), 'error' );
			WTE()->notices->add( $e->getMessage(), 'error' );
			wp_redirect( get_permalink( get_option( 'wp_travel_engine_wp-travel-engine-checkout_page_id' ) ) );
			exit;
		}
		return;

	}

	public static function instance() {
		return new self();
	}
}
