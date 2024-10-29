<?php

namespace KnitPay\Extensions\WPTravelEngine;

use Pronamic\WordPress\Pay\AbstractPluginIntegration;
use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;
use WPTravelEngine\Core\Booking as WTE_Booking;

/**
 * Title: WP Travel Engine extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   1.9
 * @version 8.89.1.0
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

		// TODO add payment details box on booking page.
		// add_action( 'add_meta_boxes', array( $this, 'wpte_knit_pay_add_meta_boxes' ) );

		add_filter( 'wptravelengine_rest_prepare_settings', [ $this, 'add_payment_gateways' ], 10, 3 );
		add_filter( 'wptravelengine_settings:tabs:payments', [ $this, 'add_knit_pay_sub_tabs' ], 10, 1 );
		add_action( 'wptravelengine_api_update_settings', [ $this, 'wptravelengine_api_update_settings' ], 10, 2 );
		add_filter( 'wptravelengine_settings_api_schema', [ $this, 'wptravelengine_settings_api_schema' ], 10, 2 );

		add_action( 'wp_travel_engine_before_billing_form', [ $this, 'wp_travel_engine_before_billing_form' ], 10 );

		// TODO Deprecated, remove after 31 Dec 2025
		add_filter( 'wp_travel_engine_available_payment_gateways', [ $this, 'add_payment_gateways_old' ] );
		add_filter( 'wpte_settings_get_global_tabs', [ $this, 'settings_get_global_tabs' ] );
	}

	// Display error message on checkout page.
	public static function wp_travel_engine_before_billing_form() {
		return wp_travel_engine_print_notices();
	}

	public function add_payment_gateways_old( $gateways_list ) {
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

	public static function add_payment_gateways( $settings, $request, $settings_controller ) {
		$plugin_settings = $settings_controller->plugin_settings;

		$settings['payment_gateways'][] = [
			'id'     => 'knit_pay',
			'name'   => 'Knit Pay',
			'enable' => wptravelengine_toggled( $plugin_settings->get( 'knit_pay' ) ),
			'icon'   => esc_attr( KNITPAY_URL ) . '/images/knit-pay/icon.svg',
		];
		$settings['knit_pay_settings']  = [
			'title'               => (string) $plugin_settings->get( 'knit_pay_settings.title', __( 'Pay Online', 'knit-pay-lang' ) ),
			'description'         => (string) $plugin_settings->get( 'knit_pay_settings.description', '' ),
			'icon'                => (string) $plugin_settings->get( 'knit_pay_settings.icon', '' ),
			'payment_description' => (string) $plugin_settings->get( 'knit_pay_settings.payment_description', __( 'WTE Booking {booking_id}', 'knit-pay-lang' ) ),
			'config_id'           => (string) $plugin_settings->get( 'knit_pay_settings.config_id', get_option( 'pronamic_pay_config_id' ) ),
		];

		return $settings;
	}

	public function wptravelengine_settings_api_schema( $_schema, $settings_controller ) {
		$_schema['knit_pay_settings'] = [
			'description' => __( 'Knit Pay Payment Gateway Settings', 'knit-pay-lang' ),
			'type'        => 'object',
			'properties'  => [
				'title'               => [
					'description' => __( 'Knit Pay Title', 'knit-pay-lang' ),
					'type'        => 'string',
				],
				'description'         => [
					'description' => __( 'Knit Pay Description', 'knit-pay-lang' ),
					'type'        => 'string',
				],
				'icon'                => [
					'description' => __( 'Knit Pay Icon URL', 'knit-pay-lang' ),
					'type'        => 'string',
				],
				'payment_description' => [
					'description' => __( 'Knit Pay Payment Description', 'knit-pay-lang' ),
					'type'        => 'string',
				],
				'config_id'           => [
					'description' => __( 'Knit Pay Configuration', 'knit-pay-lang' ),
					'type'        => 'string',
				],
			],
		];

		return $_schema;
	}

	public function wptravelengine_api_update_settings( $request, $settings_controller ) {
		$plugin_settings = $settings_controller->plugin_settings;
		$setting_id      = 'knit_pay_settings';
		if ( isset( $request[ $setting_id ] ) ) {
			if ( isset( $request[ $setting_id ]['title'] ) ) {
				$plugin_settings->set( $setting_id . '.title', $request[ $setting_id ]['title'] );
			}

			if ( isset( $request[ $setting_id ]['description'] ) ) {
				$plugin_settings->set( $setting_id . '.description', $request[ $setting_id ]['description'] );
			}

			if ( isset( $request[ $setting_id ]['icon'] ) ) {
				$plugin_settings->set( $setting_id . '.icon', $request[ $setting_id ]['icon'] );
			}

			if ( isset( $request[ $setting_id ]['payment_description'] ) ) {
				$plugin_settings->set( $setting_id . '.payment_description', $request[ $setting_id ]['payment_description'] );
			}

			if ( isset( $request[ $setting_id ]['config_id'] ) ) {
				$plugin_settings->set( $setting_id . '.config_id', $request[ $setting_id ]['config_id'] );
			}
		}
	}

	public static function add_knit_pay_sub_tabs( $tab_settings ) {
		$payment_method         = 'knit_pay';
		$payment_config_options = [];
		$payment_configurations = Plugin::get_config_select_options( $payment_method );
		foreach ( $payment_configurations as $key => $payment_config ) {
			$payment_config_options[] = [
				'value' => $key,
				'label' => $payment_config,
			];
		}

		$tab_settings['sub_tabs'][] = [
			'title'  => __( 'Knit Pay', 'knit-pay-lang' ),
			'order'  => 11,
			'id'     => 'payment-knit-pay',
			'fields' => [
				[
					'field_type' => 'ALERT',
					'content'    => __( 'WP Travel Engine has done major changes in payment processing in v 4.3.0 and the new version of WP Travel Engine Payments is currently not stable and still under development. Knit Pay is now compatible with the new version of WP Travel Engine and will not work with the old version of WP Travel Engine.', 'knit-pay-lang' ),
				],
				[
					'divider'    => true,
					'field_type' => 'ALERT',
					'content'    => __( 'This version is currently under Beta and you might face some issues while using it. Kindly report the issue to Knit Pay if you find any bugs.', 'knit-pay-lang' ),
				],
				[
					'divider'    => true,
					'label'      => __( 'Title', 'knit-pay-lang' ),
					'help'       => __( 'This controls the title which the user sees during checkout.', 'knit-pay-lang' ),
					'field_type' => 'TEXT',
					'name'       => 'knit_pay_settings.title',
				],
				[
					'divider'    => true,
					'label'      => __( 'Description', 'knit-pay-lang' ),
					'help'       => sprintf(
						/* translators: %s: payment method title */
						__( 'Give the customer instructions for paying via %s.', 'knit-pay-lang' ),
						__( 'Knit Pay', 'knit-pay-lang' )
					),
					'field_type' => 'TEXT',
					'name'       => 'knit_pay_settings.description',
				],
				[
					'divider'     => true,
					'label'       => __( 'Icon URL', 'knit-pay-lang' ),
					'description' => __( 'This controls the icon which the user sees during checkout.', 'knit-pay-lang' ),
					'field_type'  => 'TEXT',
					'name'        => 'knit_pay_settings.icon',
				],
				[
					'divider'     => true,
					'label'       => __( 'Payment Description', 'knit-pay-lang' ),
					'description' => sprintf( __( 'Available tags: %s', 'knit-pay-lang' ), sprintf( '<code>%s</code>', '{booking_id}' ) ),
					'field_type'  => 'TEXT',
					'name'        => 'knit_pay_settings.payment_description',
				],
				[
					'divider'     => true,
					'label'       => __( 'Configuration', 'knit-pay-lang' ),
					'description' => 'Configurations can be created in Knit Pay gateway configurations page at <a href="' . admin_url() . 'edit.php?post_type=pronamic_gateway">"Knit Pay >> Configurations"</a>.',
					'field_type'  => 'SELECT',
					'name'        => 'knit_pay_settings.config_id',
					'options'     => $payment_config_options,
				],
			],
		];

		return $tab_settings;
	}

	public static function settings_get_global_tabs( $global_tabs ) {
		$global_tabs['wpte-payment']['sub_tabs']['knit_pay'] = [
			'label'        => __( 'Knit Pay Settings', 'knit-pay-lang' ),
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
