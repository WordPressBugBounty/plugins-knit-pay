<?php

namespace KnitPay\Extensions\LatePoint;

use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;
use OsFormHelper;
use OsPaymentsHelper;
use OsRouterHelper;
use OsSettingsHelper;



if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Title: LatePoint Gateway
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   4.4.0
 */

class Gateway {

	/**
	 * Addon version.
	 */
	public $version = '4.4.0';

	public $processor_code = 'knit_pay';


	/**
	 * LatePoint Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	public static function public_javascripts() {
		return plugin_dir_url( __FILE__ ) . 'assets/js/';
	}

	public static function images_url() {
		return 'https://ps.w.org/knit-pay/assets/icon.svg';
	}


	public function init_hooks() {
		add_action( 'latepoint_payment_processor_settings', [ $this, 'add_settings_fields' ], 10 );
		add_action( 'latepoint_wp_enqueue_scripts', [ $this, 'load_front_scripts_and_styles' ] );
		add_action( 'latepoint_booking_created_frontend', [ $this, 'update_booking_id_in_payment' ] );
		add_action( 'latepoint_booking_updated_frontend', [ $this, 'update_booking_id_in_payment' ] );

		add_filter( 'latepoint_payment_processors', [ $this, 'register_payment_processor' ], 10, 2 );
		add_filter( 'latepoint_all_payment_methods', [ $this, 'register_payment_methods' ] );
		add_filter( 'latepoint_enabled_payment_methods', [ $this, 'register_enabled_payment_methods' ] );

		add_filter( 'latepoint_localized_vars_front', [ $this, 'localized_vars_for_front' ] );

		add_filter( 'latepoint_process_payment_for_booking', [ $this, 'process_payment' ], 10, 3 );

		add_filter( 'latepoint_sub_step_for_payment_step', [ $this, 'sub_step_for_payment_step' ] );

		add_filter( 'latepoint_need_to_show_payment_step', [ $this, 'need_to_show_payment_step' ] );
	}

	public function need_to_show_payment_step( $need ) {
		return true;
	}

	public function process_payment( $result, $booking, $customer ) {
		if ( OsPaymentsHelper::is_payment_processor_enabled( $this->processor_code ) && 'knit_pay' === $booking->payment_method ) {

			if ( $booking->payment_token ) {
				$payment = new Payment( $booking->payment_token );
				if ( is_null( $payment ) ) {
					exit;
				}

				if ( PaymentStatus::SUCCESS === $payment->get_status() ) {
					$result['status']       = LATEPOINT_STATUS_SUCCESS;
					$result['processor']    = $this->processor_code;
					$result['charge_id']    = $payment->get_transaction_id();
					$result['funds_status'] = LATEPOINT_TRANSACTION_STATUS_APPROVED;
				} else {
					$result['status']  = LATEPOINT_STATUS_ERROR;
					$result['message'] = 'Payment Failed.';
					$booking->add_error( 'payment_error', $result['message'] );
					$booking->add_error( 'send_to_step', $result['message'], 'payment' );
				}
			} else {
				  $result['status']  = LATEPOINT_STATUS_ERROR;
				  $result['message'] = __( 'Payment ID undefined.', 'knit-pay-lang' );
				  $booking->add_error( 'payment_error', $result['message'] );
			}
		}
		return $result;
	}

	public static function update_booking_id_in_payment( $booking ) {
		$payment = new Payment( $booking->payment_token );
		if ( is_null( $payment ) ) {
			exit;
		}
		$payment->source_id = $booking->id;
		$payment->order_id  = $booking->id;
		$payment->save();
	}

	// TODO remove this function if not required.
	public function sub_step_for_payment_step( $sub_step ) {
		if ( OsPaymentsHelper::is_payment_processor_enabled( $this->processor_code ) ) {
			$sub_step = 'payment-method-content';
		}
		return $sub_step;
	}

	public function get_supported_payment_methods() {
		return [
			$this->processor_code => [
				'name'      => __( 'Knit Pay', 'knit-pay-lang' ),
				'label'     => __( 'Pay Online', 'knit-pay-lang' ),
				'image_url' => $this->images_url(),
				'code'      => $this->processor_code,
				'time_type' => 'now',
			],
		];
	}

	// adds payment method to payment settings
	public function register_payment_methods( $payment_methods ) {
		$payment_methods = array_merge( $payment_methods, $this->get_supported_payment_methods() );
		return $payment_methods;
	}


	public function register_payment_processor( $payment_processors, $enabled_only ) {
		$payment_processors[ $this->processor_code ] = [
			'code'      => $this->processor_code,
			'name'      => __( 'Knit Pay', 'knit-pay-lang' ),
			'image_url' => $this->images_url(),

		];
		return $payment_processors;
	}

	public function register_enabled_payment_methods( $enabled_payment_methods ) {
		if ( OsPaymentsHelper::is_payment_processor_enabled( $this->processor_code ) ) {
			$enabled_payment_methods = array_merge( $enabled_payment_methods, $this->get_supported_payment_methods() );
		}
		return $enabled_payment_methods;
	}

	public function add_settings_fields( $processor_code ) {
		$configuration_list = [];
		foreach ( Plugin::get_config_select_options( $processor_code ) as $value => $label ) {
			$configuration_list[] = [
				'label' => $label,
				'value' => $value,
			];
		}

		if ( $processor_code != $this->processor_code ) {
			return false;
		} ?>
		<h3><?php _e( 'Knit Pay Settings', 'knit-pay-lang' ); ?></h3>
			<?php echo OsFormHelper::select_field( 'settings[knit_pay_config_id]', __( 'Configuration', 'knit-pay-lang' ), $configuration_list, OsSettingsHelper::get_settings_value( 'knit_pay_config_id', get_option( 'pronamic_pay_config_id' ) ) ); ?>
	   
		<div class="os-row">
			<div class="os-col-6">
				<?php echo OsFormHelper::text_field( 'settings[knit_pay_payment_description]', __( 'Payment Description', 'knit-pay-lang' ), OsSettingsHelper::get_settings_value( 'knit_pay_payment_description', '{service_name}' ) ); ?>
			</div>
			<div class="os-col-6">
				<?php echo sprintf( __( 'Available tags: %s', 'knit-pay-lang' ), sprintf( '<code>%s</code>', '{service_name}, {agent_name}, {generated_form_id}' ) ); ?>
			</div>
		</div>
	  
		<?php echo OsFormHelper::text_field( 'settings[knit_pay_currency_iso_code]', __( 'ISO Currency Code', 'knit-pay-lang' ), OsSettingsHelper::get_settings_value( 'knit_pay_currency_iso_code' ) ); ?>
	  
		<?php
	}

	public function load_front_scripts_and_styles() {
		if ( OsPaymentsHelper::is_payment_processor_enabled( $this->processor_code ) ) {
			// Stylesheets

			// Javascripts
			wp_enqueue_script( 'latepoint-payments-knitpay', $this->public_javascripts() . 'latepoint-payments-knitpay.js', [ 'jquery', 'latepoint-main-front' ], $this->version );
		}
	}

	public function localized_vars_for_front( $localized_vars ) {
		if ( OsPaymentsHelper::is_payment_processor_enabled( $this->processor_code ) ) {
			$localized_vars['is_knit_pay_active']             = true;
			$localized_vars['knit_pay_payment_options_route'] = OsRouterHelper::build_route_name( 'payments_knit_pay', 'get_payment_options' );
		} else {
			$localized_vars['is_knit_pay_active'] = false;

		}
		return $localized_vars;
	}

}

