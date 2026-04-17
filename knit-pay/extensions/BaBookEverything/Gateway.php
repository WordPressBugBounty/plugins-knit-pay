<?php

namespace KnitPay\Extensions\BaBookEverything;

use BABE_Order;
use BABE_Settings;
use Pronamic\WordPress\Money\Currency;
use Pronamic\WordPress\Money\Money;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Plugin;
use WP_Session;

/**
 * Title: BA Book Everything Gateway
 * Description:
 * Copyright: 2020-2023 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   9.1.0.0
 */

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit();

class Gateway {
	private $id             = '';
	private $payment_method = '';
	
	public function __construct( $id, $paymet_method ) {
		$this->id             = $id;
		$this->payment_method = $paymet_method;

		add_action( 'babe_settings_payment_method_' . $this->id, [ $this, 'payment_settings_fields' ], 10, 3 );

		if ( class_exists( 'BABE_Settings' ) ) {
			add_filter( 'babe_sanitize_' . BABE_Settings::$option_name, [ $this, 'sanitize_settings' ], 10, 2 );
		}

		add_filter( 'babe_checkout_payment_title_' . $this->id, [ $this, 'payment_method_title' ] );
		add_filter( 'babe_checkout_payment_description_' . $this->id, [ $this, 'payment_method_description_html' ] );
		add_action( 'babe_order_start_paying_with_' . $this->id, [ $this, 'order_to_pay' ], 10, 4 );
	}
	
	public function payment_settings_fields( $section_id, $option_menu_slug, $option_name ) {
		add_settings_field(
			$this->id . '_tab_title',
			__( 'Payment tab title', 'knit-pay-lang' ),
			[ 'BABE_Settings_admin', 'text_field_callback' ],
			$option_menu_slug,
			$section_id,
			[
				'option'        => $this->id . '_tab_title',
				'settings_name' => $option_name,
			]
		);

		add_settings_field(
			$this->id . '_description',
			__( 'Front-end description', 'knit-pay-lang' ),
			[ 'BABE_Settings_admin', 'textarea_callback' ],
			$option_menu_slug,
			$section_id,
			[
				'option'        => $this->id . '_description',
				'settings_name' => $option_name,
			]
		);

		// Payment Description.
		add_settings_field(
			$this->id . '_payment_description',
			__( 'Payment Description', 'knit-pay-lang' ),
			[ 'KnitPay\\CustomSettingFields', 'input_field' ],
			$option_menu_slug,
			$section_id,
			[
				'description' => sprintf(
					'%s<br />%s',
					/* translators: %s: default code */
					sprintf( __( 'Default: <code>%s</code>', 'knit-pay-lang' ), __( 'Order {order_id}', 'knit-pay-lang' ) ),
					/* translators: %s: tags */
					sprintf( __( 'Tags: %s', 'knit-pay-lang' ), sprintf( '<code>%s</code> <code>%s</code>', '{order_id}', '{order_number}' ) )
				),
				'label_for'   => $option_name . '[' . $this->id . '_config_id' . ']',
				'type'        => 'text',
				'class'       => 'regular-text',
				'value'       => $this->get_setting( 'payment_description' ),
			]
		);

		add_settings_field(
			$this->id . '_config_id',
			__( 'Configuration', 'knit-pay-lang' ),
			[ 'KnitPay\\CustomSettingFields', 'select_configuration' ],
			$option_menu_slug,
			$section_id,
			[
				'description' => __( 'Configurations can be created in Knit Pay gateway configurations page at <a href="' . admin_url() . 'edit.php?post_type=pronamic_gateway">"Knit Pay >> Configurations"</a>.', 'knit-pay-lang' ) . '<br>' . __( 'Visit the "Knit Pay >> Settings" page to set Default Gateway Configuration.', 'knit-pay-lang' ),
				'label_for'   => $option_name . '[' . $this->id . '_config_id' . ']',
				'class'       => 'regular-text',
				'value'       => $this->get_setting( 'config_id' ),
			]
		);
	}

	/**
	 * Sanitize settings
	 *
	 * @param array $new_input
	 * @param array $input
	 * @return array
	 */
	public function sanitize_settings( $new_input, $input ) {
		$new_input[ $this->id . '_tab_title' ] = empty( $input[ $this->id . '_tab_title' ] ) ? __( 'Online Payment', 'knit-pay-lang' ) : sanitize_text_field( $input[ $this->id . '_tab_title' ] );

		$new_input[ $this->id . '_description' ] = isset( $input[ $this->id . '_description' ] ) ? sanitize_textarea_field( $input[ $this->id . '_description' ] ) : '';

		$new_input[ $this->id . '_payment_description' ] = empty( $input[ $this->id . '_payment_description' ] ) ? __( 'Order {order_id}', 'knit-pay-lang' ) : sanitize_text_field( $input[ $this->id . '_payment_description' ] );

		$new_input[ $this->id . '_config_id' ] = isset( $input[ $this->id . '_config_id' ] ) ? intval( $input[ $this->id . '_config_id' ] ) : 0;

		return $new_input;
	}

	/**
	 * Get setting value from BABE settings.
	 *
	 * @param string $key
	 * @return string
	 */
	private function get_setting( $key ) {
		$key      = $this->id . '_' . $key;
		$settings = BABE_Settings::get_settings();
		return isset( $settings[ $key ] ) ? $settings[ $key ] : '';
	}

	/**
	 * Output payment method title for checkout form
	 *
	 * @return string
	 */
	public function payment_method_title() {
		$tab_title = $this->get_setting( 'tab_title' );
		return empty( $tab_title ) ? __( 'Online Payment', 'knit-pay-lang' ) : $tab_title;
	}

	/**
	 * Output payment method fields html for checkout form
	 *
	 * @return string
	 */
	public function payment_method_description_html() {
		return $this->get_setting( 'description' );
	}

	/**
	 * Init payment method
	 *
	 * @param int    $order_id
	 * @param array  $args
	 * @param string $current_url
	 * @param string $success_url
	 * @return void
	 */
	public function order_to_pay( $order_id, $args, $current_url, $success_url ) {
		$config_id      = $this->get_setting( 'config_id' );
		$payment_method = $this->payment_method;
		
		// Use default gateway if no configuration has been set.
		if ( empty( $config_id ) ) {
			$config_id = get_option( 'pronamic_pay_config_id' );
		}
		
		$gateway = Plugin::get_gateway( $config_id );
		
		if ( ! $gateway ) {
			return;
		}

		$amount   = ( isset( $args['payment']['amount_to_pay'] ) && 'deposit' === $args['payment']['amount_to_pay'] ) ? BABE_Order::get_order_prepaid_amount( $order_id ) : BABE_Order::get_order_total_amount( $order_id );
		$currency = BABE_Order::get_order_currency( $order_id );
		
		/**
		 * Build payment.
		 */
		$payment = new Payment();
		
		$payment->source    = 'ba-book-everything';
		$payment->source_id = $order_id;
		$payment->order_id  = $order_id;
		
		$payment->set_description( Helper::get_description( $this->get_setting( 'payment_description' ), $args ) );
		
		$payment->title = Helper::get_title( $order_id );
		
		// Customer.
		$payment->set_customer( Helper::get_customer( $args ) );
		
		// Address.
		$payment->set_billing_address( Helper::get_address( $args ) );
		
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
			wp_safe_redirect( $payment->get_pay_redirect_url() );
		} catch ( \Exception $e ) {
			// Could not find option to show error message in BA Book Everything Checkout page, this is workaround.
			$_SESSION['knit_pay_babe_error'] = $e->getMessage();

			// Redirect to confirmation page to show the error message.
			wp_safe_redirect( $success_url );

			return;
		}
	}
}
