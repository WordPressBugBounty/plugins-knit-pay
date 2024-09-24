<?php

namespace KnitPay\Extensions\LifterLMS;

use Pronamic\WordPress\Money\Currency;
use Pronamic\WordPress\Money\Money;
use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Payments\Payment;
use LLMS_Access_Plan;
use LLMS_Coupon;
use LLMS_Order;
use LLMS_Payment_Gateway;
use LLMS_Student;

/**
 * Title: Lifter LMS Gateway
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   1.8.0
 */

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit();

class Gateway extends LLMS_Payment_Gateway {

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

		$this->id                   = 'knit_pay';
		$this->admin_description    = __( 'This payment method does not use a predefined payment method for the payment. Some payment providers list all activated payment methods for your account to choose from. Use payment method specific gateways (such as "Instamojo") to let customers choose their desired payment method at checkout.', 'lifterlms' );
		$this->admin_title          = __( 'Knit Pay', 'lifterlms' );
		$this->title                = __( 'Pay Online', 'lifterlms' );
		$this->description          = __( '', 'lifterlms' );
		$this->payment_instructions = 'This payment method does not use a predefined payment method for the payment. Some payment providers list all activated payment methods for your account to choose from. Use payment method specific gateways (such as "Instamojo") to let customers choose their desired payment method at checkout.';
		// $this->test_mode_title = "Test mode title";

		$this->supports = [
			'checkout_fields'    => false,
			'refunds'            => false, // manual refunds are available always for all gateways and are not handled by this class
			'single_payments'    => true,
			'recurring_payments' => false, // TODO: add support for recurring payments
			'test_mode'          => false,
		];

		add_filter( 'llms_get_gateway_settings_fields', [ $this, 'get_settings_fields' ], 10, 2 );

		$this->init();
	}

	/**
	 * Get admin setting fields
	 *
	 * @param    array  $fields      default fields
	 * @param    string $gateway_id  gateway ID
	 * @return   array
	 * @since    3.0.0
	 * @version  3.0.0
	 */
	public function get_settings_fields( $fields, $gateway_id ) {

		if ( $this->id !== $gateway_id ) {
			return $fields;
		}

		$fields[] = [
			'title'   => __( 'Configuration', 'lifterlms' ),
			'desc'    => '<br>' . __( 'Configurations can be created in Knit Pay gateway configurations page at <a href="' . admin_url() . 'edit.php?post_type=pronamic_gateway">"Knit Pay >> Configurations"</a>.', 'lifterlms' ),
			'id'      => $this->get_option_name( 'config_id' ),
			'default' => get_option( 'pronamic_pay_config_id' ),
			'type'    => 'select',
			'options' => Plugin::get_config_select_options( $this->payment_method ),
		];

		$fields[] = [
			'title'   => __( 'Payment Description', 'lifterlms' ),
			'id'      => $this->get_option_name( 'payment_description' ),
			'default' => __( 'Lifter LMS Order {order_id}', 'knit-pay-lang' ),
			'type'    => 'text',
			'desc'    => sprintf( __( 'Available tags: %s', 'knit-pay-lang' ), sprintf( '<code>%s</code>', '{order_id}' ) ),
		];

		return $fields;

	}

	/**
	 * Called when the Update Payment Method form is submitted from a single order view on the student dashboard
	 *
	 * Gateways should do whatever the gateway needs to do to validate the new payment method and save it to the order
	 * so that future payments on the order will use this new source
	 *
	 * @param    LLMS_Order $order      Instance of the LLMS_Order
	 * @param    array      $form_data  Additional data passed from the submitted form (EG $_POST)
	 * @return   void
	 * @since    3.10.0
	 * @version  3.10.0
	 */
	public function handle_payment_source_switch( $order, $form_data = [] ) {
		die();
		// TODO

		$previous_gateway = $order->get( 'payment_gateway' );

		if ( $this->get_id() === $previous_gateway ) {
			return;
		}

		$order->set( 'payment_gateway', $this->get_id() );
		$order->set( 'gateway_customer_id', '' );
		$order->set( 'gateway_source_id', '' );
		$order->set( 'gateway_subscription_id', '' );

		$order->add_note( sprintf( __( 'Payment method switched from "%1$s" to "%2$s"', 'lifterlms' ), $previous_gateway, $this->get_admin_title() ) );

	}

	/**
	 * Handle a Pending Order
	 * Called by LLMS_Controller_Orders->create_pending_order() on checkout form submission
	 * All data will be validated before it's passed to this function
	 *
	 * @param   LLMS_Order        $order   Instance LLMS_Order for the order being processed
	 * @param   LLMS_Access_Plan  $plan    Instance LLMS_Access_Plan for the order being processed
	 * @param   LLMS_Student      $person  Instance of LLMS_Student for the purchasing customer
	 * @param   LLMS_Coupon|false $coupon  Instance of LLMS_Coupon applied to the order being processed, or false when none is being used
	 * @return  void
	 * @since   3.0.0
	 * @version 3.10.0
	 */
	public function handle_pending_order( $order, $plan, $person, $coupon = false ) {

		// no payment (free orders)
		if ( floatval( 0 ) === $order->get_initial_price( [], 'float' ) ) {

			// free access plans do not generate receipts
			if ( $plan->is_free() ) {

				$order->set( 'status', 'llms-completed' );

				// free trial, reduced to free via coupon, etc...
				// we do want to record a transaction and then generate a receipt
			} else {

				// record a $0.00 transaction to ensure a receipt is sent
				$order->record_transaction(
					[
						'amount'             => floatval( 0 ),
						'source_description' => __( 'Free', 'lifterlms' ),
						'transaction_id'     => uniqid(),
						'status'             => 'llms-txn-succeeded',
						'payment_gateway'    => 'manual',
						'payment_type'       => 'single',
					]
				);

			}

			$this->complete_transaction( $order );
			return;
		}

		$config_id      = $this->config_id;
		$payment_method = $this->id;

		// Use default gateway if no configuration has been set.
		if ( empty( $config_id ) ) {
			$config_id = get_option( 'pronamic_pay_config_id' );
		}

		$gateway = Plugin::get_gateway( $config_id );

		if ( ! $gateway ) {
			return false;
		}

		$order_id = $order->id;

		/**
		 * Build payment.
		 */
		$payment = new Payment();

		$payment->source    = 'lifterlms';
		$payment->source_id = $order_id;
		$payment->order_id  = $order_id;

		$payment->set_description( Helper::get_description( $order->get_gateway(), $order_id ) );

		$payment->title = Helper::get_title( $order_id );

		// Customer.
		$payment->set_customer( Helper::get_customer_from_order( $order ) );

		// Address.
		$payment->set_billing_address( Helper::get_address_from_order( $order ) );

		// Currency.
		$currency = Currency::get_instance( \get_lifterlms_currency() );

		// Amount.
		$payment->set_total_amount( new Money( $order->get_initial_price( [], 'float' ), $currency ) );

		// Method.
		$payment->set_payment_method( $payment_method );

		// Configuration.
		$payment->config_id = $config_id;

		try {
			$payment = Plugin::start_payment( $payment );

			// Execute a redirect.
			llms_redirect_and_exit(
				$payment->get_pay_redirect_url(),
				[
					'safe' => false,
				]
			);
		} catch ( \Exception $e ) {
			llms_add_notice( Plugin::get_default_error_message(), 'error' );
			llms_add_notice( $e->getMessage(), 'error' );
			$order->set_status( 'llms-failed' );
			return;
		}

	}

	/**
	 * Called by scheduled actions to charge an order for a scheduled recurring transaction
	 * This function must be defined by gateways which support recurring transactions
	 *
	 * @param  LLMS_Order $order   Instance LLMS_Order for the order being processed
	 * @return   mixed
	 * @since    3.10.0
	 * @version  3.10.0
	 */
	public function handle_recurring_transaction( $order ) {
		// TODO: Add support for recurring payment
		die();

		// switch to order on hold if it's a paid order
		if ( $order->get_price( 'total', [], 'float' ) > 0 ) {

			// update status
			$order->set_status( 'on-hold' );

			/**
			 * @hooked LLMS_Notification: manual_payment_due - 10
			 */
			do_action( 'llms_manual_payment_due', $order, $this );

		}

	}

	/**
	 * Determine if the gateway is enabled according to admin settings checkbox
	 *
	 * @return   boolean
	 * @since    3.0.0
	 * @version  3.0.0
	 */
	public function is_enabled() {
		return ( 'yes' === $this->get_enabled() ) ? true : false;
	}

	/**
	 * Init.
	 */
	private function init() {
		$this->config_id = $this->get_option( 'config_id' );
	}
}
