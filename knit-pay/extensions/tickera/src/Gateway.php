<?php

namespace KnitPay\Extensions\Tickera;

use Pronamic\WordPress\Money\Currency;
use Pronamic\WordPress\Money\Money;
use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Payments\Payment;
use Tickera\TC_Form_Fields_API;
use Tickera\TC_Gateway_API;

/**
 * Title: Tickera Gateway
 * Description:
 * Copyright: 2020-2025 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   8.84.0.0
 * @version 8.96.2.0
 */

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit();

class Gateway extends TC_Gateway_API {

	var $plugin_name = 'knit_pay';
	var $config_id;
	var $skip_payment_screen = true;
	var $payment_description;

	/**
	 * Support for older payment gateway API
	 */
	function on_creation() {
		$this->init();
	}

	function init() {
		global $tc;

		$this->admin_name  = __( 'Knit Pay', 'knit-pay-lang' );
		$this->public_name = $this->get_option( 'title', __( 'Online Payment', 'knit-pay-lang' ), $this->plugin_name );

		$this->method_img_url      = apply_filters( 'tc_gateway_method_img_url', $tc->plugin_url . 'images/gateways/custom-offline-payments.png', $this->plugin_name );
		$this->admin_img_url       = apply_filters( 'tc_gateway_admin_img_url', plugins_url( 'images/small-knit-pay.png', __FILE__ ), $this->plugin_name );
		$this->config_id           = $this->get_option( 'config_id', '', $this->plugin_name );
		$this->payment_description = $this->get_option( 'payment_description', '', $this->plugin_name );
	}

	function process_payment( $cart ) {
		global $tc;

		tickera_final_cart_check( $cart );
		$this->save_cart_info();
		$order_id  = $tc->generate_order_id();
		$cart_info = $this->cart_info();

		$config_id      = $this->config_id;
		$payment_method = $this->plugin_name;

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

		$payment->source    = 'tickera';
		$payment->source_id = $order_id;
		$payment->order_id  = $order_id;

		$payment->set_description( Helper::get_description( $this, $order_id ) );

		$payment->title = Helper::get_title( $order_id );

		// Customer.
		$payment->set_customer( Helper::get_customer_from_order( $cart_info ) );

		// Address.
		$payment->set_billing_address( Helper::get_address_from_order( $cart_info ) );

		// Currency.
		$currency = Currency::get_instance( $tc->get_store_currency() );

		// Amount.
		$payment->set_total_amount( new Money( $this->total(), $currency ) );

		// Method.
		$payment->set_payment_method( $payment_method );

		// Configuration.
		$payment->config_id = $config_id;

		try {
			$payment = Plugin::start_payment( $payment );

			$payment_info = $this->save_payment_info();
			$tc->create_order( $order_id, $this->cart_contents(), $this->cart_info(), $payment_info, false );

			tickera_redirect( $payment->get_pay_redirect_url(), true, true );
		} catch ( \Exception $e ) {
			$tc->checkout_error = true;
			$tc->session->set( 'tc_cart_errors', $e->getMessage() );
			tickera_redirect( $tc->get_payment_slug( true ), true );
		}
	}

	function save_cart_info() {
		global $tc;

		$class_name = get_class( $this );

		$session                                    = $tc->session->get();
		$session['cart_info']['gateway']            = $this->plugin_name;
		$session['cart_info']['gateway_admin_name'] = $this->admin_name;
		$session['cart_info']['gateway_class']      = $class_name;
		$tc->session->set( 'cart_info', $session['cart_info'] );
	}

	function gateway_admin_settings( $settings, $visible ) {
		$fields = [
			'title'               => [
				'title'       => __( 'Title', 'knit-pay-lang' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'knit-pay-lang' ),
				'default'     => __( 'Online Payment', 'knit-pay-lang' ),
			],
			'config_id'           => [
				'title'       => __( 'Configuration', 'knit-pay-lang' ),
				'type'        => 'select',
				'default'     => get_option( 'pronamic_pay_config_id' ),
				'options'     => Plugin::get_config_select_options(),
				'description' => 'Configurations can be created in Knit Pay gateway configurations page at <a href="' . admin_url( 'edit.php?post_type=pronamic_gateway' ) . '">"Knit Pay >> Configurations"</a>.',
			],
			'payment_description' => [
				'title'       => __( 'Payment Description', 'knit-pay-lang' ),
				'type'        => 'text',
				'description' => sprintf(
					'%s<br />%s<br />%s',
					__( 'This controls the payment description.', 'knit-pay-lang' ),
					/* translators: %s: default code */
					sprintf( __( 'Default: <code>%s</code>', 'knit-pay-lang' ), __( 'Order {order_id}', 'knit-pay-lang' ) ),
					/* translators: %s: tags */
					sprintf( __( 'Tags: %s', 'knit-pay-lang' ), sprintf( '<code>%s</code>', '{order_id}' ) )
				),
				'default'     => __( 'Order {order_id}', 'knit-pay-lang' ),
			],
		];
		$form   = new TC_Form_Fields_API( $fields, 'tc', 'gateways', $this->plugin_name );
		?>

		<div id="<?php echo esc_attr( $this->plugin_name ); ?>"
			 class="postbox" <?php echo( ! $visible ? 'style="display:none;"' : '' ); ?>>
			<h3><span><?php printf( __( '%s Settings', 'knit-pay-lang' ), $this->admin_name ); ?></span>
			</h3>
			<div class="inside">
				<table class="form-table">
					<?php $form->admin_options(); ?>
				</table>
			</div>
		</div>
		<?php
	}
}

\Tickera\tickera_register_gateway_plugin( 'KnitPay\Extensions\Tickera\Gateway', 'knit_pay', __( 'Knit Pay', 'knit-pay-lang' ) );
