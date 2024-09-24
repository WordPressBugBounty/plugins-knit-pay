<?php

namespace KnitPay\Extensions\KnitPayPaymentButton;

use Pronamic\WordPress\Money\Currency;
use Pronamic\WordPress\Money\Money;
use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Payments\Payment;

/**
 * Title: Knit Pay - Payment Button Gateway
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   8.75.0.0
 */

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit();

class Gateway {

	/**
	 * Bootstrap
	 */
	public function __construct() {
		// Initiate Gutenberg Payment Button Block.
		global $wp_version;
		if ( function_exists( 'register_block_type' ) && version_compare( $wp_version, '6.1', '>=' ) ) {
			$blocks_module = new PaymentButtonBlock();
			$blocks_module->setup();
		}

		// Initialize Elementor Payment Button Widget.
		add_action( 'elementor/widgets/register', [ $this, 'register_payment_button_widget' ] );

		// Add Javascript Dependencies.
		add_action( 'wp_enqueue_scripts', [ $this, 'knit_pay_payment_button_dependencies' ] );

		// Add Ajax listener.
		add_action( 'wp_ajax_nopriv_knit_pay_payment_button_submit', [ $this, 'ajax_payment_button_submit' ] );
		add_action( 'wp_ajax_knit_pay_payment_button_submit', [ $this, 'ajax_payment_button_submit' ] );
	}

	/**
	 * Register scripts.
	 */
	function knit_pay_payment_button_dependencies() {
		/* Scripts */
		wp_register_script( 'knit-pay-payment-button-frontend', plugins_url( 'build/view.js', __FILE__ ), [ 'jquery' ], KNITPAY_VERSION );

		wp_localize_script(
			'jquery',
			'knit_pay_payment_button_ajax_object',
			[
				'ajaxurl'      => admin_url( 'admin-ajax.php' ),
				'loading_icon' => KNITPAY_URL . '/images/loading.gif',
			]
		);
	}

	function register_payment_button_widget( $widgets_manager ) {
		if ( trait_exists( 'Elementor\Includes\Widgets\Traits\Button_Trait' ) ) {
			$widgets_manager->register( new ElementorPaymentButtonWidget() );
		}
	}

	public function ajax_payment_button_submit() {
		$amount              = filter_input( INPUT_POST, 'amount', FILTER_SANITIZE_STRING );
		$currency            = filter_input( INPUT_POST, 'currency', FILTER_SANITIZE_STRING );
		$payment_description = filter_input( INPUT_POST, 'payment_description', FILTER_SANITIZE_STRING );
		$config_id           = filter_input( INPUT_POST, 'config_id', FILTER_SANITIZE_STRING );
		$nonce_action        = "knit_pay_payment_button|{$amount}|{$currency}|{$payment_description}|{$config_id}";

		if ( ! wp_verify_nonce( filter_input( INPUT_POST, 'knit_pay_nonce', FILTER_SANITIZE_STRING ), $nonce_action ) ) {
			echo wp_json_encode(
				[
					'status'    => 'error',
					'error_msg' => __( 'Nonce Missmatch!', 'knit-pay-lang' ),
				]
			);
			exit;
		}

		if ( empty( $amount ) ) {
			$amount = 0;
		}

		$payment_method = 'knit_pay';
		$current_user   = wp_get_current_user();

		// Use default gateway if no configuration has been set.
		if ( empty( $config_id ) ) {
			$config_id = get_option( 'pronamic_pay_config_id' );
		}

		$gateway = Plugin::get_gateway( $config_id );

		if ( ! $gateway ) {
			echo wp_json_encode(
				[
					'status'    => 'error',
					'error_msg' => __( 'Gateway configuration was not found. If you are an admin, kindly create a payment gateway configuration.', 'knit-pay-lang' ),
				]
			);
			exit;
		}

		/**
		 * Build payment.
		 */
		$payment = new Payment();

		$payment->source   = 'knit-pay-payment-button';
		$payment->order_id = uniqid();
		$payment->source   = uniqid();

		$payment->set_description( Helper::get_description( $payment, $payment_description ) );

		$payment->title = Helper::get_title( $payment );

		// Customer.
		$payment->set_customer( Helper::get_customer( $current_user ) );

		// Address.
		$payment->set_billing_address( Helper::get_address( $current_user ) );

		// Currency.
		$currency = Currency::get_instance( $currency );

		// Amount.
		$payment->set_total_amount( new Money( $amount, $currency ) );

		// Method.
		$payment->set_payment_method( $payment_method );

		// Configuration.
		$payment->config_id = $config_id;

		try {
			$payment = Plugin::start_payment( $payment );

			// Execute a redirect.
			echo wp_json_encode(
				[
					'status'       => 'success',
					'redirect_url' => $payment->get_pay_redirect_url(),
				]
			);
			exit;
		} catch ( \Exception $e ) {
			echo wp_json_encode(
				[
					'status'    => 'error',
					'error_msg' => $e->getMessage(),
				]
			);
			exit;
		}
	}

	public static function instance() {
		return new self();
	}
}
