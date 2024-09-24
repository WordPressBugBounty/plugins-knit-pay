<?php

namespace KnitPay\Extensions\WPTravel;

use Pronamic\WordPress\Money\Currency;
use Pronamic\WordPress\Money\Money;
use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Payments\Payment;
use WP_Session;

/**
 * Title: WP Travel extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   8.78.0.0
 */

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit();

class Gateway {

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
		if ( self::uses_wp_travel_payment_prototype() ) {
			// Hooks for payment setup.
			add_action( 'wp_travel_after_frontend_booking_save', [ $this, 'process' ], 0 );

			// TODO Test with partial. Not tested with partial payment, hence disabled.
			// add_action( 'wp_travel_before_partial_payment_complete', [ $this, 'process' ], 10, 2 );
		}

		add_filter( 'wp_travel_settings_fields', [ $this, 'wptravel_knit_pay_default_settings_fields' ] );
	}
	
	/**
	 * Determine if booking used your payment method( wp-travel-payment-prototype ).
	 */
	private static function uses_wp_travel_payment_prototype() {
		return 'POST' === $_SERVER['REQUEST_METHOD'] && array_key_exists( 'wp_travel_booking_option', $_REQUEST ) && 'booking_with_payment' === $_REQUEST['wp_travel_booking_option'] && array_key_exists( 'wp_travel_payment_gateway', $_REQUEST ) && 'knit_pay' === $_REQUEST['wp_travel_payment_gateway'];
	}
	
	public function wptravel_knit_pay_default_settings_fields( $settings_fields ) {
		$settings_fields['payment_option_knit_pay']     = 'no';
		$settings_fields['wp_travel_knit_pay_settings'] = [ 'title' => 'Pay Online' ];
		
		return $settings_fields;
	}

	public function process( $booking_id, $complete_partial_payment = false ) {
		if ( ! $booking_id ) {
			return;
		}
		/**
		 * Before payment process action [ not needed in partial payment ].
		 * wptravel_update_payment_status_booking_payment() // add/update payment id.
		 */
		if ( ! $complete_partial_payment ) { // updated in 1.8.5 for partial payment.
			do_action( 'wt_before_payment_process', $booking_id );
		}
		// Check if paypal is selected.
	    if ( ! isset( $_POST['wp_travel_payment_gateway'] ) || 'knit_pay' !== $_POST['wp_travel_payment_gateway'] ) { //@phpcs:ignore
			return;
		}
		// Check if Booking with payment is selected.
	    if ( ! isset( $_POST['wp_travel_booking_option'] ) || 'booking_with_payment' !== $_POST['wp_travel_booking_option'] ) { //@phpcs:ignore
			return;
		}
		

		// Get settings.
		$wt_settings = wptravel_get_settings();

		$config_id      = $wt_settings['wp_travel_knit_pay_settings']['config_id'];
		$payment_method = 'knit_pay';

		// If config id is not set, set it to default.
		if ( empty( $config_id ) ) {
			$config_id = get_option( 'pronamic_pay_config_id' );
		}

		$gateway = Plugin::get_gateway( $config_id );

		if ( ! $gateway ) {
			return false;
		}

		$order_data           = get_post_meta( $booking_id, 'order_data', true );
		$booking_data         = wptravel_booking_data( $booking_id );
		$wp_travel_payment_id = wptravel_get_payment_id( $booking_id );
		$payment_mode         = get_post_meta( $wp_travel_payment_id, 'wp_travel_payment_mode', true ); // is Payment mode Partial or full.

		$total = $booking_data['total'];
		if ( 'partial' === $payment_mode ) {
			$total = $booking_data['total_partial'];
		}
		$paid = $booking_data['paid_amount'];
		
		$amount = wptravel_get_formated_price( $total - $paid );

		/**
		 * Build payment.
		 */
		$payment = new Payment();

		$payment->source    = 'wp-travel';
		$payment->source_id = $booking_id;
		$payment->order_id  = $booking_id;

		$payment->set_description( Helper::get_description( $wt_settings['wp_travel_knit_pay_settings'], $booking_id ) );

		$payment->title = Helper::get_title( $booking_id );

		// Customer.
		$payment->set_customer( Helper::get_customer( $order_data ) );

		// Address.
		$payment->set_billing_address( Helper::get_address( $order_data ) );

		// Currency.
		$currency = Currency::get_instance( $wt_settings['currency'] );

		// Amount.
		$payment->set_total_amount( new Money( $amount, $currency ) );

		// Method.
		$payment->set_payment_method( $payment_method );

		// Configuration.
		$payment->config_id = $config_id;

		try {
			$payment = Plugin::start_payment( $payment );

			wp_safe_redirect( $payment->get_pay_redirect_url() );
			exit;
		} catch ( \Exception $e ) {
			// Could not find option to show error message in WP Travel Checkout page, this is workaround.
			$wp_session                             = WP_Session::get_instance();
			$wp_session['wp_travel_knit_pay_error'] = $e->getMessage();
			
			wp_safe_redirect( get_permalink( $wt_settings['checkout_page_id'] ) );
			exit;
		}

	}
}
