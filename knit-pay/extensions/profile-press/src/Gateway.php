<?php

namespace KnitPay\Extensions\ProfilePress;

use ProfilePress\Core\Membership\Controllers\CheckoutResponse;
use ProfilePress\Core\Membership\Models\Order\OrderFactory;
use ProfilePress\Core\Membership\PaymentMethods\AbstractPaymentMethod;
use Pronamic\WordPress\Money\Currency;
use Pronamic\WordPress\Money\Money;
use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Core\PaymentMethods;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Refunds\Refund;

/**
 * Title: ProfilePress Gateway
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   8.79.0.0
 */

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit();

class Gateway extends AbstractPaymentMethod {

	protected $config_id;
	protected $payment_description;

	/**
	 * @var string
	 */
	public $id = 'knit_pay';

	/**
	 * Bootstrap
	 *
	 * @param array $args Gateway properties.
	 */
	public function __construct( /* $args */ ) {
		parent::__construct();
		
		$this->id             = 'knit_pay'; // TODO
		$this->payment_method = 'knit_pay'; // TODO
		$this->title          = PaymentMethods::get_name( $this->payment_method, __( 'Pay Online', 'knit-pay-lang' ) );
		$this->description    = '';
		
		$this->method_title = sprintf(
			/* translators: 1: Gateway admin label prefix, 2: Gateway admin label */
			__( '%1$s - %2$s', 'knit-pay-lang' ),
			__( 'Knit Pay', 'knit-pay-lang' ),
			PaymentMethods::get_name( $this->payment_method )
		);
		$this->method_description = __( 'Configurations can be created in Knit Pay gateway configurations page at <a href="' . admin_url() . 'edit.php?post_type=pronamic_gateway">"Knit Pay >> Configurations"</a>.', 'knit-pay-lang' );

		$this->icon = empty( $this->get_value( 'icon' ) ) ? PaymentMethods::get_icon_url( $this->payment_method ) : empty( $this->get_value( 'icon' ) );

		$gateway = Plugin::get_gateway( $this->get_value( 'config_id' ) );
		if ( null !== $gateway && $gateway->supports( 'refunds' ) ) {
			$this->supports = [
				self::REFUNDS,
			];
		}
	}

	public function admin_settings() {

		$settings = parent::admin_settings();
		
		$settings['icon'] = [
			'label'       => __( 'Icon', 'knit-pay-lang' ),
			'type'        => 'text',
			'description' => 
				__( 'This controls the icon which the user sees during checkout.', 'knit-pay-lang' ),
			'default'     => '',
		];

		$settings['config_id'] = [
			'label'       => __( 'Configuration', 'knit-pay-lang' ),
			'description' => __( 'Configurations can be created in Knit Pay gateway configurations page at <a href="' . admin_url() . 'edit.php?post_type=pronamic_gateway">"Knit Pay >> Configurations"</a>.', 'knit-pay-lang' ),
			'default'     => get_option( 'pronamic_pay_config_id' ),
			'type'        => 'select',
			'options'     => Plugin::get_config_select_options( $this->payment_method ),
		];

		$settings['payment_description'] = [
			'label'       => __( 'Payment Description', 'knit-pay-lang' ),
			'default'     => __( 'ProfilePress Order {order_id}', 'knit-pay-lang' ),
			'type'        => 'text',
			'description' => sprintf( __( 'Available tags: %s', 'knit-pay-lang' ), sprintf( '<code>%s</code> <code>%s</code>', '{order_id}', '{plan_name}' ) ) . 'Default: Order {order_id}',
		];
		
		$settings['remove_billing_fields'] = [
			'label'          => esc_html__( 'Remove Billing Address', 'knit-pay-lang' ),
			'type'           => 'checkbox',
			'checkbox_label' => esc_html__( 'Check to remove billing address fields from the checkout page.', 'knit-pay-lang' ),
			'description'    => esc_html__( 'If you do not want the billing address fields displayed on the checkout page, use this setting to remove it.', 'knit-pay-lang' ),
		];

		return $settings;

	}

	public function process_payment( $order_id, $subscription_id, $customer_id ) {
		$config_id      = $this->get_value( 'config_id' );
		$payment_method = $this->payment_method;

		// Use default gateway if no configuration has been set.
		if ( empty( $config_id ) ) {
			$config_id = get_option( 'pronamic_pay_config_id' );
		}

		$gateway = Plugin::get_gateway( $config_id );

		if ( ! $gateway ) {
			return false;
		}

		$order    = OrderFactory::fromId( $order_id );
		$customer = $order->get_customer();
		
		/**
		 * Build payment.
		 */
		$payment = new Payment();

		$payment->source    = 'profile-press';
		$payment->source_id = $order_id;
		$payment->order_id  = $order_id;

		$payment->set_description( Helper::get_description( $this, $order ) );

		$payment->title = Helper::get_title( $order_id );

		// Customer.
		$payment->set_customer( Helper::get_customer( $customer ) );

		// Address.
		$payment->set_billing_address( Helper::get_address_from_customer( $customer ) );

		// Currency.
		$currency = Currency::get_instance( \ppress_get_currency() );

		// Amount.
		$payment->set_total_amount( new Money( $order->get_total(), $currency ) );

		// Method.
		$payment->set_payment_method( $payment_method );

		// Configuration.
		$payment->config_id = $config_id;

		try {
			$payment = Plugin::start_payment( $payment );
			
			$checkoutResponse = new CheckoutResponse();
				
				return $checkoutResponse
				->set_is_success( true )
				->set_redirect_url( $payment->get_pay_redirect_url() );
		} catch ( \Exception $e ) {
			ppress_log_error( __METHOD__ . '(): ' . $e->getMessage() );
			
			return ( new CheckoutResponse() )
			->set_is_success( false )
			->set_error_message( $e->getMessage() );
		}

	}
	
	public function validate_fields() {
		return true;
	}

	public function process_webhook() {
	}
	
	protected function billing_address_form() {
		return $this->is_billing_fields_removed() ? '' : parent::billing_address_form();
	}
	
	/**
	 * Disable billing validation.
	 *
	 * @param $val
	 *
	 * @return bool
	 */
	public function should_validate_billing_details( $val ) {
		if ( $this->is_billing_fields_removed() ) {
			$val = false;
		}
		
		return $val;
	}
	
	protected function is_billing_fields_removed() {
		return $this->get_value( 'remove_billing_fields' ) === 'true';
	}
	
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		// Check gateway.
		$gateway = Plugin::get_gateway( $this->get_value( 'config_id' ) );
		
		if ( null === $gateway ) {
			return new \WP_Error(
				'pronamic-pay-profile-press-refund-gateway',
				__( 'Unable to process refund as gateway configuration does not exist.', 'knit-pay-lang' )
			);
		}

		// Create refund.
		$order = OrderFactory::fromId( $order_id );

		$amount = new Money( $amount, $order->currency );
		
		// Check payment.
		$payment = \get_pronamic_payment_by_transaction_id( $order->get_transaction_id() );

		if ( null === $payment ) {
			return;
		}

		try {
			$refund = new Refund( $payment, $amount );
			$refund->set_description( $reason );

			Plugin::create_refund( $refund );

			if ( null !== $refund->psp_id ) {
				$note = \sprintf(
					/* translators: 1: formatted refund amount, 2: refund gateway reference */
					\__( 'Created refund of %1$s with gateway reference `%2$s`.', 'knit-pay-lang' ),
					\esc_html( $amount->format_i18n() ),
					\esc_html( $refund->psp_id )
				);
				
				$order->add_note( $note );
				return true;
			}
			
			$order->add_note( esc_html__( 'Refund request failed', 'knit-pay-lang' ) );
			return false;
		} catch ( \Exception $e ) {
			ppress_log_error( $e->getMessage() . '; OrderID:' . $order_id );
			
			return false;
		}
		
		return true;
	}
}
