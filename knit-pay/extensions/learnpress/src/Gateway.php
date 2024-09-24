<?php

namespace KnitPay\Extensions\LearnPress;

use Pronamic\WordPress\Money\Currency;
use Pronamic\WordPress\Money\Money;
use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Payments\Payment;
use Exception;
use LP_Gateway_Abstract;
use LP_Settings;
use LearnPress;

/**
 * Title: Learn Press extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   1.6.0
 */

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit();

class Gateway extends LP_Gateway_Abstract {
	/**
	 * @var LP_Settings
	 */
	public $settings;

	/**
	 * @var string
	 */
	public $id;

	/**
	 * Payment method.
	 *
	 * @var string
	 */
	private $payment_method;

	/**
	 * Supports.
	 *
	 * @var array
	 */
	private $supports;

	/**
	 * Bootstrap
	 *
	 * @param array $args Gateway properties.
	 */
	public function __construct( $args = [] ) {
		$this->id = $args['id'];

		parent::__construct();
		$this->init();

		$this->payment_method     = $args['payment_method'];
		$this->method_title       = $args['method_title'];
		$this->method_description = $args['method_description'];
		$this->icon               = isset( $args['icon'] ) ? $args['icon'] : '';
		$this->title              = $this->settings->get( 'title' ) ? $this->settings->get( 'title' ) : $args['title'];
		$this->description        = $this->settings->get( 'description' ) ? $this->settings->get( 'description' ) : sprintf( __( 'Pay with %s', 'knit-pay-lang' ), $this->title );
	}

	public function get_settings() {

		return apply_filters(
			'learn-press/gateway-payment/' . $this->id . '/settings',
			[
				[
					'type' => 'title',
				],
				[
					'title'   => __( 'Enable', 'knit-pay-lang' ),
					'id'      => '[enable]',
					'default' => 'no',
					'type'    => 'yes-no',
					'desc'    => sprintf( __( 'Enable %s', 'knit-pay-lang' ), $this->method_title ),
				],
				[
					'title'   => __( 'Title', 'knit-pay-lang' ),
					'id'      => '[title]',
					'default' => $this->title,
					'type'    => 'text',
				],
				[
					'title'   => __( 'Instruction', 'knit-pay-lang' ),
					'id'      => '[description]',
					'default' => $this->description,
					'type'    => 'textarea',
					'editor'  => [ 'textarea_rows' => 5 ],
				],
				[
					'title'   => __( 'Configuration', 'knit-pay-lang' ),
					'id'      => '[config_id]',
					'default' => get_option( 'pronamic_pay_config_id' ),
					'type'    => 'select',
					'options' => Plugin::get_config_select_options( $this->payment_method ),
					'desc'    => 'Configurations can be created in Knit Pay gateway configurations page at <a href="' . admin_url() . 'edit.php?post_type=pronamic_gateway">"Knit Pay >> Configurations"</a>.',
				],
				[
					'title'   => __( 'Payment Description', 'knit-pay-lang' ),
					'id'      => '[payment_description]',
					'default' => __( 'Learn Press Order {order_id}', 'knit-pay-lang' ),
					'type'    => 'text',
					'desc'    => sprintf( __( 'Available tags: %s', 'knit-pay-lang' ), sprintf( '<code>%s</code>', '{order_id}' ) ),
				],
				[
					'type' => 'sectionend',
				],
			]
		);
	}

	/**
	 * Init.
	 */
	private function init() {
		$this->config_id = $this->settings->get( 'config_id' );

		add_filter( 'learn-press/payment-gateway/' . $this->id . '/available', [ $this, 'is_enabled' ] );
	}

	public function process_payment( $order_id ) {
		$config_id = $this->config_id;

		// Use default gateway if no configuration has been set.
		if ( empty( $config_id ) ) {
			$config_id = get_option( 'pronamic_pay_config_id' );
		}

		$gateway = Plugin::get_gateway( $config_id );

		if ( ! $gateway ) {
			return [
				'result' => 'fail',
			];
		}

		$order      = learn_press_get_order( $order_id );
		$user_data  = get_userdata( $order->get_user_id() );
		$learnpress = LearnPress::instance();
		$checkout   = $learnpress->checkout();

		/**
		 * Build payment.
		 */
		$payment = new Payment();

		$payment->source    = 'learnpress';
		$payment->source_id = $order_id;
		$payment->order_id  = $order_id;

		$payment->set_description( Helper::get_description( $this->settings, $order_id ) );

		$payment->title = Helper::get_title( $order_id );

		// Customer.
		$payment->set_customer( Helper::get_customer( $checkout, $user_data ) );

		// Address.
		$payment->set_billing_address( Helper::get_address( $checkout, $user_data ) );

		// Currency.
		$currency = Currency::get_instance( \learn_press_get_currency() );

		// Amount.
		$payment->set_total_amount( new Money( $learnpress->get_cart()->total, $currency ) );

		// Method.
		$payment->set_payment_method( $this->payment_method );

		// Configuration.
		$payment->config_id = $config_id;

		try {
			$payment = Plugin::start_payment( $payment );

			return [
				'redirect' => $payment->get_pay_redirect_url(),
				'result'   => $payment->get_pay_redirect_url() ? 'success' : 'fail',
			];
		} catch ( \Exception $e ) {
			$error_message = Plugin::get_default_error_message() . '<br>' . $e->getMessage();

			throw new Exception( $error_message );
		}

		return [
			'result' => 'fail',
		];
	}

	/**
	 * Icon for the gateway
	 *
	 * @return string
	 */
	// FIXME
	/*
	 public function get_icon() {
		if ( empty( $this->icon ) ) {
			$this->icon =PaymentMethods::get_icon_url( $this->payment_method );
		}

		return parent::get_icon();
	} */
}
