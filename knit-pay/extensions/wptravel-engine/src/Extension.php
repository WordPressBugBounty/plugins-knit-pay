<?php

namespace KnitPay\Extensions\WPTravelEngine;

use Pronamic\WordPress\Pay\AbstractPluginIntegration;
use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;
use Pronamic\WordPress\Pay\Core\Util;
use Pronamic\WordPress\Pay\Payments\Payment;
use WPTravelEngine\Core\Booking as WTE_Booking;

/**
 * Title: WP Travel Engine extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   1.9
 * @version 8.87.13.1
 */
class Extension extends AbstractPluginIntegration {
	/**
	 * Slug
	 *
	 * @var string
	 */
	const SLUG = 'wp-travel-engine';

	/**
	 * Constructs and initialize WP Travel Engine extension.
	 */
	public function __construct() {
		parent::__construct(
			[
				'name' => __( 'WP Travel Engine', 'knit-pay-lang' ),
			]
		);

		// Dependencies.
		$dependencies = $this->get_dependencies();

		$dependencies->add( new WPTravelEngineDependency() );
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

		Gateway::instance();

		// TODO add customer phone fileld
		// wp_travel_engine_booking_fields_display

		add_filter( 'wp_travel_engine_available_payment_gateways', [ $this, 'add_payment_gateways' ] );
		add_filter( 'wpte_settings_get_global_tabs', [ $this, 'settings_get_global_tabs' ] );
		add_action( 'wp_travel_engine_before_billing_form', [ $this, 'wp_travel_engine_before_billing_form' ], 10 );
		// TODO add payment details box on booking page.
		// add_action( 'add_meta_boxes', array( $this, 'wpte_payu_add_meta_boxes' ) );
	}

	public static function wp_travel_engine_before_billing_form() {
		return wp_travel_engine_print_notices();
	}

	public function add_payment_gateways( $gateways_list ) {
		$wp_travel_engine_settings = get_option( 'wp_travel_engine_settings' );
		$knit_pay_settings         = isset( $wp_travel_engine_settings['knit_pay_settings'] ) ? $wp_travel_engine_settings['knit_pay_settings'] : [];
		$title                     = ! empty( $knit_pay_settings['title'] ) ? $knit_pay_settings['title'] : __( 'Pay Online', 'knit-pay-lang' );
		$icon                      = ! empty( $knit_pay_settings['icon'] ) ? $knit_pay_settings['icon'] : '';

		$gateways_list['knit_pay'] = [
			'label'        => $title,
			'input_class'  => 'knit-pay-payment',
			'public_label' => '',
			'icon_url'     => $icon,
			'info_text'    => '',
		];

		if ( is_admin() ) {
			$gateways_list['knit_pay']['label'] = __( 'Knit Pay', 'knit-pay-lang' );
		}

		return $gateways_list;
	}

	public static function settings_get_global_tabs( $global_tabs ) {
		$global_tabs['wpte-payment']['sub_tabs']['knit_pay'] = [
			'label'        => __( 'Knit Pay Settings', 'wp-travel-engine' ),
			'content_path' => __DIR__ . '/admin_setting.php',
			'current'      => true,
		];

		return $global_tabs;
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
		$booking_id     = (int) $payment->get_source_id();
		$return_url     = wp_travel_engine_get_booking_confirm_url();
		$wte_payment_id = $payment->get_meta( 'wte_payment_id' );

		switch ( $payment->get_status() ) {
			case Core_Statuses::CANCELLED:
			case Core_Statuses::EXPIRED:
			case Core_Statuses::FAILURE:
				// TODO redirect to fail page
				return WTE_Booking::get_cancel_url( $booking_id, $wte_payment_id, $payment->get_payment_method() );

				break;

			case Core_Statuses::SUCCESS:
				return WTE_Booking::get_return_url( $booking_id, $wte_payment_id, $payment->get_payment_method() );
				break;

			case Core_Statuses::AUTHORIZED:
			case Core_Statuses::OPEN:
				return home_url( '/' );
		}

		return $url;
	}

	/**
	 * Update the status of the specified payment
	 *
	 * @param Payment $payment Payment.
	 */
	public static function status_update( Payment $payment ) {
		$booking_id     = (int) $payment->get_source_id();
		$wte_payment_id = $payment->get_meta( 'wte_payment_id' );

		$wte_payment = get_post( $wte_payment_id );
		if ( isset( $wte_payment->payable ) ) {
			$payable = $wte_payment->payable;
		}

		$booking_metas = get_post_meta( $booking_id, 'wp_travel_engine_booking_setting', true );
		$booking       = get_post( $booking_id );

		// payment completed.
		// Update booking status and Payment args.
		$booking_metas['place_order']['payment']['payment_gateway'] = $payment->get_payment_method();
		$booking_metas['place_order']['payment']['payment_status']  = $payment->get_status();
		$booking_metas['place_order']['payment']['transaction_id']  = $payment->get_transaction_id();

		update_post_meta( $booking_id, 'wp_travel_engine_booking_setting', $booking_metas );

		// TODO: remove hardcoded
		update_post_meta( $booking_id, 'wp_travel_engine_booking_payment_gateway', 'Knit Pay' );

		switch ( $payment->get_status() ) {
			case Core_Statuses::CANCELLED:
			case Core_Statuses::EXPIRED:
				WTE_Booking::update_booking(
					$booking_id,
					[
						'meta_input' => [
							'wp_travel_engine_booking_payment_status' => 'cancelled',
							'wp_travel_engine_booking_status' => 'canceled',
						],
					]
				);
				update_post_meta( $wte_payment_id, 'payment_status', 'cancelled' );

				break;
			case Core_Statuses::FAILURE:
				WTE_Booking::update_booking(
					$booking_id,
					[
						'meta_input' => [
							'wp_travel_engine_booking_payment_status' => 'failed',
							'wp_travel_engine_booking_status' => 'pending',
						],
					]
				);
				update_post_meta( $wte_payment_id, 'payment_status', 'failed' );

				break;
			case Core_Statuses::SUCCESS:
				if ( empty( $booking->due_amount ) ) {
					return;
				}
				$payment_status = 'complete';
				$paid_amount    = $booking->paid_amount + $payable['amount'];
				$due_amount     = $booking->due_amount - $payable['amount'];
				if ( ! empty( $due_amount ) ) {
					$payment_status = 'partially-paid';
				}
				WTE_Booking::update_booking(
					$booking_id,
					[
						'meta_input' => [
							'paid_amount' => $paid_amount,
							'due_amount'  => $due_amount,
							'wp_travel_engine_booking_payment_status' => $payment_status,
							'wp_travel_engine_booking_status' => 'booked',
						],
					]
				);
				update_post_meta( $wte_payment_id, 'payment_status', $payment_status );

				// Send Notification emails on order confirmation.
				WTE_Booking::send_emails( $wte_payment_id, 'order_confirmation', 'all' );

				break;
			case Core_Statuses::OPEN:
			default:
				WTE_Booking::update_booking(
					$booking_id,
					[
						'meta_input' => [
							'wp_travel_engine_booking_payment_status' => 'pending',
							'wp_travel_engine_booking_status' => 'pending',
						],
					]
				);
				update_post_meta( $wte_payment_id, 'payment_status', 'pending' );
				break;
		}
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
		$text = __( 'WP Travel Engine', 'knit-pay-lang' ) . '<br />';

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
		return __( 'WP Travel Engine Booking', 'knit-pay-lang' );
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
