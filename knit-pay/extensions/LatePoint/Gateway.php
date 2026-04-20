<?php

namespace KnitPay\Extensions\LatePoint;

use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;
use OsFormHelper;
use OsPaymentsHelper;
use OsRouterHelper;
use OsSettingsHelper;
use Pronamic\WordPress\Money\Currencies;



if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Title: LatePoint Gateway
 * Description:
 * Copyright: 2020-2026 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   4.4.0
 * @version 9.3.1.0
 */

class Gateway {

	/**
	 * Addon version.
	 */
	public $version = KNITPAY_VERSION;

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

		// Payment step content (renders the "Pay Now" wrapper inside the payment step)
		add_action( 'latepoint_step_payment__pay_content', [ $this, 'output_payment_step_contents' ] );

		add_filter( 'latepoint_payment_processors', [ $this, 'register_payment_processor' ], 10 );

		add_filter( 'latepoint_get_all_payment_times', [ $this, 'add_all_payment_methods_to_payment_times' ] );
		add_filter( 'latepoint_get_enabled_payment_times', [ $this, 'add_enabled_payment_methods_to_payment_times' ] );

		add_filter( 'latepoint_localized_vars_front', [ $this, 'localized_vars_for_front' ] );

		// New order-intent based payment processing (replaces latepoint_process_payment_for_booking)
		add_filter( 'latepoint_process_payment_for_order_intent', [ $this, 'process_payment_for_order_intent' ], 10, 2 );

		// Update Knit Pay payment source_id when the LatePoint order is created
		add_action( 'latepoint_order_created', [ $this, 'update_source_id_in_payment' ] );
	}

	/**
	 * Output the payment step UI content for Knit Pay.
	 * All inner panels are hidden by default; JS reveals them as needed.
	 *
	 * Panels:
	 *   .lp-knitpay-loading      – AJAX in progress (preparing the payment link)
	 *   .lp-knitpay-pay-notice   – link ready; user should click "Pay Now"
	 *   .lp-knitpay-waiting      – popup is open; waiting for completion
	 *   .lp-knitpay-blocked      – browser blocked the popup; offers a fallback link
	 *
	 * @param \OsCartModel $cart
	 */
	public function output_payment_step_contents( $cart ) {
		if ( ! OsPaymentsHelper::should_processor_handle_payment_for_cart( $this->processor_code, $cart ) ) {
			return;
		}
		?>
		<div class="lp-payment-method-content" data-payment-method="<?php echo esc_attr( $this->processor_code ); ?>">
			<div class="lp-payment-method-content-i">

				<!-- Loading: AJAX preparing the payment -->
				<div class="lp-knitpay-loading" style="display:none;">
					<div class="os-loading-line"></div>
					<p class="lp-knitpay-msg"><?php _e( 'Preparing your payment&hellip;', 'knit-pay-lang' ); ?></p>
				</div>

				<!-- Ready: prompt the user to click Pay Now -->
				<div class="lp-knitpay-pay-notice" style="display:none;">
					<p class="lp-knitpay-msg">🔒 <?php _e( 'Your payment is ready. Click <strong>Pay Now</strong> below to complete it in a secure window.', 'knit-pay-lang' ); ?></p>
				</div>

				<!-- Waiting: payment popup is open -->
				<div class="lp-knitpay-waiting" style="display:none;">
					<div class="os-loading-line"></div>
					<p class="lp-knitpay-msg"><?php _e( 'Waiting for payment&hellip; Complete the payment in the opened window, then return here.', 'knit-pay-lang' ); ?></p>
				</div>

				<!-- Blocked: browser blocked the popup window -->
				<div class="lp-knitpay-blocked" style="display:none;">
					<p class="lp-knitpay-msg"><?php _e( 'Your browser blocked the payment window. Please use one of the options below:', 'knit-pay-lang' ); ?></p>
					<p>
						<a class="lp-knitpay-blocked-link latepoint-btn latepoint-btn-outline" href="#" target="_blank" rel="noopener noreferrer">
							&#8599; <?php _e( 'Open Payment Page', 'knit-pay-lang' ); ?>
						</a>
					</p>
					<p class="lp-knitpay-msg"><?php _e( 'After completing payment in the new tab, click below:', 'knit-pay-lang' ); ?></p>
					<p>
						<button type="button" class="lp-knitpay-done-btn latepoint-btn latepoint-btn-main">
							&#10003; <?php _e( "I've Completed Payment", 'knit-pay-lang' ); ?>
						</button>
						&nbsp;
						<button type="button" class="lp-knitpay-cancel-btn latepoint-btn latepoint-btn-outline">
							<?php _e( 'Cancel', 'knit-pay-lang' ); ?>
						</button>
					</p>
				</div>

			</div>
		</div>
		<?php
	}

	/**
	 * Process payment for an order intent.
	 *
	 * @param array              $result
	 * @param \OsOrderIntentModel $order_intent
	 * @return array
	 */
	public function process_payment_for_order_intent( array $result, $order_intent ): array {
		if ( ! OsPaymentsHelper::should_processor_handle_payment_for_order_intent( $this->processor_code, $order_intent ) ) {
			return $result;
		}

		$payment_method = $order_intent->get_payment_data_value( 'method' );

		if ( 'knit_pay' !== $payment_method ) {
			return $result;
		}

		$payment_token = $order_intent->get_payment_data_value( 'token' );

		if ( empty( $payment_token ) ) {
			$result['status']  = LATEPOINT_STATUS_ERROR;
			$result['message'] = __( 'Payment ID undefined.', 'knit-pay-lang' );
			$order_intent->add_error( 'payment_error', $result['message'] );
			return $result;
		}

		$payment = new Payment( (int) $payment_token );

		if ( ! $payment->get_id() ) {
			$result['status']  = LATEPOINT_STATUS_ERROR;
			$result['message'] = __( 'Payment not found.', 'knit-pay-lang' );
			$order_intent->add_error( 'payment_error', $result['message'] );
			$order_intent->add_error( 'send_to_step', $result['message'], 'payment' );
			return $result;
		}

		if ( PaymentStatus::SUCCESS === $payment->get_status() ) {
			$result['status']    = LATEPOINT_STATUS_SUCCESS;
			$result['processor'] = $this->processor_code;
			$result['charge_id'] = $payment->get_transaction_id();
			$result['kind']      = LATEPOINT_TRANSACTION_KIND_CAPTURE;
		} else {
			$result['status']  = LATEPOINT_STATUS_ERROR;
			$result['message'] = __( 'Payment was not completed. Please try again.', 'knit-pay-lang' );
			$order_intent->add_error( 'payment_error', $result['message'] );
			$order_intent->add_error( 'send_to_step', $result['message'], 'payment' );
		}

		return $result;
	}

	/**
	 * Update the Knit Pay payment's source_id with the actual LatePoint order ID
	 * once the order is created from an order intent.
	 *
	 * @param \OsOrderModel $order
	 */
	public static function update_source_id_in_payment( $order ) {
		if ( empty( $order->id ) ) {
			return;
		}

		$payment_method = $order->get_initial_payment_data_value( 'method' );
		$payment_token  = $order->get_initial_payment_data_value( 'token' );

		if ( 'knit_pay' !== $payment_method || empty( $payment_token ) ) {
			return;
		}

		$payment = new Payment( (int) $payment_token );
		if ( ! $payment->get_id() ) {
			return;
		}

		$payment->source_id = $order->id;
		$payment->order_id  = $order->id;
		$payment->save();
	}

	public function get_supported_payment_methods() {
		return [
			$this->processor_code => [
				'name'      => __( 'Knit Pay', 'knit-pay-lang' ),
				'label'     => __( 'Online Payment', 'knit-pay-lang' ),
				'image_url' => $this->images_url(),
			],
		];
	}

	public function add_all_payment_methods_to_payment_times( array $payment_times ): array {
		$payment_methods = $this->get_supported_payment_methods();
		foreach ( $payment_methods as $payment_method_code => $payment_method_info ) {
			$payment_times[ LATEPOINT_PAYMENT_TIME_NOW ][ $payment_method_code ][ $this->processor_code ] = $payment_method_info;
		}
		return $payment_times;
	}

	public function add_enabled_payment_methods_to_payment_times( array $payment_times ): array {
		if ( OsPaymentsHelper::is_payment_processor_enabled( $this->processor_code ) ) {
			$payment_times = $this->add_all_payment_methods_to_payment_times( $payment_times );
		}
		return $payment_times;
	}

	public function register_payment_processor( $payment_processors ) {
		$payment_processors[ $this->processor_code ] = [
			'code'      => $this->processor_code,
			'name'      => __( 'Knit Pay', 'knit-pay-lang' ),
			'image_url' => $this->images_url(),
		];
		return $payment_processors;
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
				<?php echo OsFormHelper::text_field( 'settings[knit_pay_payment_description]', __( 'Payment Description', 'knit-pay-lang' ), OsSettingsHelper::get_settings_value( 'knit_pay_payment_description', '{order_key}' ) ); ?>
			</div>
			<div class="os-col-6">
				<?php printf( __( 'Available tags: %s', 'knit-pay-lang' ), sprintf( '<code>%s</code>', '{order_key}' ) ); ?>
			</div>
		</div>
	  
		<?php
			$currency_list = [];
			foreach ( Currencies::get_currencies() as $currency ) {
				$currency_list[ $currency->get_alphabetic_code() ] = $currency->get_name() . ' (' . $currency->get_alphabetic_code() . ')';
			}
			echo OsFormHelper::select_field( 'settings[knit_pay_currency_iso_code]', __( 'ISO Currency Code', 'knit-pay-lang' ), $currency_list, OsSettingsHelper::get_settings_value( 'knit_pay_currency_iso_code' ) );
		?>
		<?php
	}

	public function load_front_scripts_and_styles() {
		if ( OsPaymentsHelper::is_payment_processor_enabled( $this->processor_code ) ) {
			wp_enqueue_script( 'latepoint-payments-knitpay', $this->public_javascripts() . 'latepoint-payments-knitpay.js', [ 'jquery', 'latepoint-main-front' ], $this->version );
		}
	}

	public function localized_vars_for_front( $localized_vars ) {
		if ( OsPaymentsHelper::is_payment_processor_enabled( $this->processor_code ) ) {
			$localized_vars['is_knit_pay_active']             = true;
			$localized_vars['knit_pay_payment_options_route'] = OsRouterHelper::build_route_name( 'payments_knit_pay', 'get_payment_options' );
			// Translatable label for the "Pay Now" button (overrides LatePoint's default "Next")
			$localized_vars['knit_pay_pay_btn_label']         = __( 'Pay Now', 'knit-pay-lang' );
		} else {
			$localized_vars['is_knit_pay_active'] = false;
		}
		return $localized_vars;
	}
}
