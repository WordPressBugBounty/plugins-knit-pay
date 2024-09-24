<?php

namespace KnitPay\Extensions\RestroPress;

use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Core\PaymentMethods;
use Pronamic\WordPress\Money\Currency;
use Pronamic\WordPress\Money\Money;
use Pronamic\WordPress\Pay\Payments\Payment;

/**
 * Title: Restro Press extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   2.6
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
	public function __construct( $title, $id ) {

		$this->id    = $id;
		$this->title = $title;

		// Create Setting Form.
		add_filter( 'rpress_settings_gateways', [ $this, 'rpress_settings_gateways' ], 1, 1 );

		// Create Setting Form Title.
		add_filter( 'rpress_settings_sections', [ $this, 'rpress_get_registered_settings_sections' ] );

		// Knit Pay does not need a CC form, so remove it.
		add_action( 'rpress_' . $this->id . '_cc_form', '__return_false' );

		// Initiate Payment.
		add_action( 'rpress_gateway_' . $this->id, [ $this, 'process_purchase' ] );

		// Register Payment Gateway.
		add_filter( 'rpress_payment_gateways', [ $this, 'rpress_payment_gateways' ] );
	}

	public function rpress_payment_gateways( $gateways ) {
		$checkout_lable = rpress_get_option( $this->id . '_title', 'Pay Online' );

		$gateways[ $this->id ] = [
			'admin_label'    => $this->title,
			'checkout_label' => $checkout_lable,
		];

		return $gateways;
	}

	/**
	 * Get the settings sections for each tab
	 * Uses a static to avoid running the filters on every request to this function
	 *
	 * @since  1.0.0
	 * @return array Array of tabs and sections
	 */
	function rpress_get_registered_settings_sections( $sections ) {
		$sections['gateways'][ $this->id ] = $this->title;
		return $sections;
	}

	/**
	 * Registers the PayPal Standard settings for the PayPal Standard subsection
	 *
	 * @since  1.0
	 * @param  array $gateway_settings  Gateway tab settings
	 * @return array                    Gateway tab settings with the PayPal Standard settings
	 */
	function rpress_settings_gateways( $gateway_settings ) {
		$this->payment_method = $this->id;

		$settings = [
			$this->id . '_settings'            => [
				'id'   => $this->id . '_settings',
				'name' => '<strong>' . __( 'Knit Pay Settings', 'restropress' ) . '</strong>',
				'type' => 'header',
			],
			$this->id . '_title'               => [
				'id'   => $this->id . '_title',
				'name' => __( 'Title', 'knit-pay-lang' ),
				'std'  => PaymentMethods::get_name( $this->payment_method, __( 'Knit Pay', 'knit-pay-lang' ) ),
				'type' => 'text',
				'size' => 'regular',
				'desc' => __( 'This controls the title which the user sees during checkout.', 'knit-pay-lang' ),
			],
			$this->id . '_config_id'           => [
				'id'      => $this->id . '_config_id',
				'name'    => __( 'Configuration', 'knit-pay-lang' ),
				'desc'    => '<br>' . __( 'Configurations can be created in Knit Pay gateway configurations page at <a href="' . admin_url() . 'edit.php?post_type=pronamic_gateway">"Knit Pay >> Configurations"</a>.', 'knit-pay-lang' ),
				'default' => get_option( 'pronamic_pay_config_id' ),
				'type'    => 'select',
				'size'    => 'regular',
				'options' => Plugin::get_config_select_options( $this->payment_method ),
			],
			$this->id . '_payment_description' => [
				'id'   => $this->id . '_payment_description',
				'name' => __( 'Payment Description', 'knit-pay-lang' ),
				'std'  => __( 'Restro Press Order {order_id}', 'knit-pay-lang' ),
				'type' => 'text',
				'size' => 'regular',
				'desc' => sprintf( __( 'Available tags: %s', 'knit-pay-lang' ), sprintf( '<code>%s</code>', '{order_id}' ) ),
			],
		];

		$gateway_settings[ $this->id ] = $settings;

		return $gateway_settings;
	}

	/**
	 * Process the purchase and create the charge in Amazon
	 *
	 * @since 1.0
	 * @param  $purchase_data array Cart details
	 * @return void
	 */
	public function process_purchase( $purchase_data ) {

		if ( ! wp_verify_nonce( $purchase_data['gateway_nonce'], 'rpress-gateway' ) ) {
			wp_die( __( 'Nonce verification has failed', 'restropress' ), __( 'Error', 'restropress' ), [ 'response' => 403 ] );
		}

		// Record the pending payment
		$payment_id = \rpress_insert_payment( $purchase_data );

		// Check payment
		if ( ! $payment_id ) {
			// Record the error
			rpress_record_gateway_error( __( 'Payment Error', 'knit-pay-lang' ), sprintf( __( 'Payment creation failed before sending buyer to ' . $this->title . '. Payment data: %s', 'restropress' ), wp_json_encode( $payment_data ) ), $payment );
			// Problems? send back
			rpress_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['rpress-gateway'] );
		}

		// Initiating Payment.
		$config_id      = rpress_get_option( $this->id . '_config_id' );
		$payment_method = $this->id;

		// Use default gateway if no configuration has been set.
		if ( empty( $config_id ) ) {
			$config_id = get_option( 'pronamic_pay_config_id' );
		}

		$gateway = Plugin::get_gateway( $config_id );

		if ( ! $gateway ) {
			return false;
		}

		/**
		 * Build payment.
		 */
		$payment = new Payment();

		$payment->source    = 'restropress';
		$payment->source_id = $payment_id;
		$payment->order_id  = $payment_id;

		$payment->set_description( Helper::get_description( $purchase_data, $payment_id ) );

		$payment->title = Helper::get_title( $payment_id );

		// Customer.
		$payment->set_customer( Helper::get_customer( $purchase_data ) );

		// Address.
		$payment->set_billing_address( Helper::get_address_from_order( $purchase_data ) );

		// Currency.
		$currency = Currency::get_instance( \rpress_get_currency() );

		// Amount.
		$payment->set_total_amount( new Money( $purchase_data['price'], $currency ) );

		// Method.
		$payment->set_payment_method( $payment_method );

		// Configuration.
		$payment->config_id = $config_id;

		try {
			$payment = Plugin::start_payment( $payment );

			// Redirect to Payment Gateway.
			wp_redirect( $payment->get_pay_redirect_url() );
			exit;
		} catch ( \Exception $e ) {
			rpress_set_error( 'knit_pay_error', $e->getMessage() );
			rpress_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['rpress-gateway'] );
		}
	}
}
