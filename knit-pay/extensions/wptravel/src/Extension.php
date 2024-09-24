<?php

namespace KnitPay\Extensions\WPTravel;

use Pronamic\WordPress\Pay\AbstractPluginIntegration;
use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Plugin;
use WP_Session;
use WP_Travel;

/**
 * Title: WP Travel extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   8.78.0.0
 */
class Extension extends AbstractPluginIntegration {
	/**
	 * Slug
	 *
	 * @var string
	 */
	const SLUG = 'wp-travel';

	/**
	 * Constructs and initialize WP Travel extension.
	 */
	public function __construct() {
		parent::__construct(
			[
				'name' => __( 'WP Travel', 'knit-pay-lang' ),
			]
		);

		// Dependencies.
		$dependencies = $this->get_dependencies();

		$dependencies->add( new WPTravelDependency() );
	}

	/**
	 * Setup plugin integration.
	 *
	 * @return void
	 */
	public function setup() {
		add_filter( 'pronamic_payment_source_text_' . self::SLUG, [ $this, 'source_text' ], 10, 2 );
		add_filter( 'pronamic_payment_source_description_' . self::SLUG, [ $this, 'source_description' ], 10, 2 );
		add_filter( 'pronamic_payment_source_url_' . self::SLUG, [ $this, 'source_url' ], 10, 2 );

		// Check if dependencies are met and integration is active.
		if ( ! $this->is_active() ) {
			return;
		}

		add_filter( 'pronamic_payment_redirect_url_' . self::SLUG, [ $this, 'redirect_url' ], 10, 2 );
		add_action( 'pronamic_payment_status_update_' . self::SLUG, [ $this, 'status_update' ], 10 );

		new Gateway();

		add_filter( 'wp_travel_payment_gateway_lists', [ $this, 'wp_travel_payment_gateway_lists' ] );
				
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
		
		// Could not find option to show error message in WP Travel Checkout page, this is workaround.
		add_action( 'wp_travel_before_checkout_page_wrap', [ $this, 'show_error_on_checkout_page' ] );

		// Show booking confirmation message.
		add_action( 'wp_travel_before_content_start', [ $this, 'wptravel_booking_message' ] );

		// Get settings.
		$this->wt_settings = wptravel_get_settings();
	}
	
	public static function show_error_on_checkout_page() {
		$wp_session = WP_Session::get_instance();
		if ( ! isset( $wp_session['wp_travel_knit_pay_error'] ) ) {
			return;
		}
		echo '<li style="color: red;">' . $wp_session['wp_travel_knit_pay_error'] . '</li>';
		unset( $wp_session['wp_travel_knit_pay_error'] );
	}
	
	public function wptravel_booking_message() {
		if ( ! is_singular( WP_TRAVEL_POST_TYPE ) ) {
			return;
		}

		if ( ! WP_Travel::verify_nonce( true ) ) {
			return;
		}

		if ( ! filter_has_var( INPUT_GET, 'kp_payment_id' ) ) {
			return;
		}

		$payment_id = filter_input( INPUT_GET, 'kp_payment_id', FILTER_SANITIZE_NUMBER_INT );

		$payment = get_pronamic_payment( $payment_id );

		if ( null === $payment ) {
			return;
		}

		$booking_conf_message = esc_html( apply_filters( 'wp_travel_booked_message', __( "Thank you for booking! We'll reach out to you soon.", 'knit-pay-lang' ) ) );
		$booking_details      = esc_html( apply_filters( 'wp_travel_booked_message_after_text', sprintf( '(Booking Option : Booking with Payment, Payment Methode : %s, Transaction ID : %s.)', $this->get_gateway_public_title(), $payment->get_transaction_id() ) ) );

		switch ( $payment->get_status() ) {
			case Core_Statuses::CANCELLED:
			case Core_Statuses::EXPIRED:
			case Core_Statuses::FAILURE:
				$message = sprintf( '<p class="col-xs-12 wp-travel-notice-danger wp-travel-notice">%s %s</p>', __( 'Transaction Failed.', 'knit-pay-lang' ), $booking_details );

				break;

			case Core_Statuses::SUCCESS:
				$message = sprintf(
					'<p class="col-xs-12 wp-travel-notice-success wp-travel-notice">%s %s</p>',
					$booking_conf_message,
					$booking_details
				);

				break;

			case Core_Statuses::OPEN:
			default:
				$message = sprintf(
					'<p class="col-xs-12 wp-travel-notice-success wp-travel-notice">%s %s %s</p>',
					$booking_conf_message,
					__( 'We are still waiting for the payment status.', 'knit-pay-lang' ),
					$booking_details
				);

				break;
		}

		echo $message;
	}

	/**
	 * Enqueue scripts.
	 */
	public function admin_enqueue_scripts() {
		$build_dir = 'admin/settings/build/';
		$deps      = include_once sprintf( $build_dir . 'index.asset.php' );
		wp_enqueue_script( 'wp_travel_knit_pay_admin_settings', plugins_url( $build_dir . 'index.js', __FILE__ ), $deps['dependencies'], $deps['version'], true );
	   
		$payment_configurations = Plugin::get_config_select_options();
		foreach ( $payment_configurations as $key => $payment_config ) {
			$payment_config_options[] = [
				'value' => $key,
				'label' => $payment_config,
			];
		}
	   
		$payment_desciption_help = sprintf( __( 'Available tags: %s', 'knit-pay-lang' ), sprintf( '%s', '{booking_id}' ) );
	   
		wp_add_inline_script(
			'wp_travel_knit_pay_admin_settings',
			sprintf(
				'window.knit_pay_wp_travel = {configs:%s, payment_description_help:%s};',
				wp_json_encode( $payment_config_options ),
				wp_json_encode( $payment_desciption_help )
			),
			'before'
		);
	}

	public function wp_travel_payment_gateway_lists( $gateway ) {
		$gateway['knit_pay'] = $this->get_gateway_public_title();

		return $gateway;
	}

	private function get_gateway_public_title() {
		if ( current_user_can( 'manage_options' ) || empty( $this->wt_settings ) ) {
			$gateway_public_title = __( 'Knit Pay', 'knit-pay-lang' );
		} elseif ( ! empty( $this->wt_settings['wp_travel_knit_pay_settings']['title'] ) ) {
			$gateway_public_title = $this->wt_settings['wp_travel_knit_pay_settings']['title'];
		} else {
			$gateway_public_title = __( 'Pay Online', 'knit-pay-lang' );
		}

		return $gateway_public_title;
	}

	/**
	 * Payment redirect URL filter.
	 *
	 * @param string  $url     Redirect URL.
	 * @param Payment $payment Payment.
	 *
	 * @return string
	 */
	public static function redirect_url( $url, $payment ) {
		$booking_id   = (int) $payment->get_source_id();
		$itinerary_id = get_post_meta( $booking_id, 'wp_travel_post_id', true );
		
		$payment_id   = wptravel_get_payment_id( $booking_id );
		$payment_mode = get_post_meta( $payment_id, 'wp_travel_payment_mode', true );
		
		$return_url = wptravel_thankyou_page_url( $itinerary_id );

		$query_arg = [
			'booking_id'    => $booking_id,
			'booked'        => true,
			'_nonce'        => WP_Travel::create_nonce(),
			'kp_payment_id' => $payment->get_id(),
		];
		
		if ( 'partial' === $payment_mode ) {
			$query_arg['partial'] = true;
		} else {
			$query_arg['order_id'] = $booking_id;
		}

		switch ( $payment->get_status() ) {
			case Core_Statuses::CANCELLED:
			case Core_Statuses::EXPIRED:
			case Core_Statuses::FAILURE:
				$query_arg['status'] = 'cancel';

				break;

			case Core_Statuses::SUCCESS:
				$query_arg['status'] = 'success';
				
				break;

			case Core_Statuses::OPEN:
			default:
				$query_arg['status'] = 'pending';

				break;
		}

		return add_query_arg(
			$query_arg,
			$return_url
		);
	}

	/**
	 * Update the status of the specified payment
	 *
	 * @param Payment $payment Payment.
	 */
	public static function status_update( Payment $payment ) {
		$booking_id           = (int) $payment->get_source_id();
		$wp_travel_payment_id = get_post_meta( $booking_id, 'wp_travel_payment_id', true );
		
		update_post_meta( $booking_id, 'txn_id', $payment->get_transaction_id() );

		switch ( $payment->get_status() ) {
			case Core_Statuses::CANCELLED:
			case Core_Statuses::EXPIRED:
			case Core_Statuses::FAILURE:
				update_post_meta( $booking_id, 'wp_travel_booking_status', 'canceled' );
				$amount = 0;

				break;
			case Core_Statuses::SUCCESS:
				// TODO Test with partial payments
				// update_post_meta( $booking_id, 'wp_travel_booking_status', 'booked' );
				// update_post_meta( $wp_travel_payment_id, 'wp_travel_payment_status', 'paid' );// TODO partial.
				
				$amount                        = $payment->get_total_amount()->number_format( null, '.', '' );
				$detail['amount']              = $amount;
				$detail['txn_id']              = $payment->get_transaction_id();
				$detail['knit_pay_payment_id'] = $payment->get_id();
				
				wptravel_update_payment_status( $booking_id, $amount, 'paid', $detail, sprintf( '_%s_args', 'knit_pay' ), $wp_travel_payment_id );
				
				do_action( 'wp_travel_after_successful_payment', $booking_id );

				break;
			case Core_Statuses::OPEN:
			default:
				update_post_meta( $booking_id, 'wp_travel_booking_status', 'pending' );
				$amount = 0;
			
				break;
		}
		update_post_meta( $wp_travel_payment_id, 'wp_travel_payment_amount', $amount );
	}

	/**
	 * Source column
	 *
	 * @param string  $text    Source text.
	 * @param Payment $payment Payment.
	 *
	 * @return string $text
	 */
	public function source_text( $text, Payment $payment ) {
		$text = __( 'WP Travel', 'knit-pay-lang' ) . '<br />';

		$text .= sprintf(
			'<a href="%s">%s</a>',
			get_edit_post_link( $payment->source_id ),
			/* translators: %s: source id */
			sprintf( __( 'Booking #%s', 'knit-pay-lang' ), $payment->source_id )
		);

		return $text;
	}

	/**
	 * Source description.
	 *
	 * @param string  $description Description.
	 * @param Payment $payment     Payment.
	 *
	 * @return string
	 */
	public function source_description( $description, Payment $payment ) {
		return __( 'WP Travel Booking', 'knit-pay-lang' );
	}

	/**
	 * Source URL.
	 *
	 * @param string  $url     URL.
	 * @param Payment $payment Payment.
	 *
	 * @return string
	 */
	public function source_url( $url, Payment $payment ) {
		return get_edit_post_link( $payment->source_id );
	}

}
