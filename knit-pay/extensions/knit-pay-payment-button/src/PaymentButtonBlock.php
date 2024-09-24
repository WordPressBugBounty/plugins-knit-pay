<?php

namespace KnitPay\Extensions\KnitPayPaymentButton;

use Pronamic\WordPress\Money\Currencies;
use Pronamic\WordPress\Pay\Plugin;

/**
 * Title: Knit Pay - Payment Button Block
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

class PaymentButtonBlock {
	/**
	 * Setup.
	 *
	 * @return void
	 */
	public function setup() {
		// Initialize.
		add_action( 'init', [ $this, 'register_block_types' ] );
		add_action( 'init', [ $this, 'init_dropdown_options' ] );
	}

	public function knit_pay_payment_button_render_callback( $attributes, $content, $block ) {
		ob_start();
		require plugin_dir_path( __FILE__ ) . 'build/template.php';
		return ob_get_clean();
	}

	/**
	 * Add block dropdown options in js.
	 *
	 * @return void
	 */
	public function init_dropdown_options() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$payment_configurations = Plugin::get_config_select_options();
		foreach ( $payment_configurations as $key => $payment_config ) {
			$payment_config_options[] = [
				'value' => $key,
				'label' => $payment_config,
			];
		}

		foreach ( Currencies::get_currencies() as $currency ) {
			$label = $currency->get_alphabetic_code();

			$symbol = $currency->get_symbol();

			if ( null !== $symbol ) {
				$label = sprintf( '%s (%s)', $label, $symbol );
			}

			$currencies_options[] = [
				'value' => $currency->get_alphabetic_code(),
				'label' => $label,
			];
		}

		wp_add_inline_script(
			'wp-block-editor',
			sprintf(
				'window.knit_pay = {configs:%s, currencies:%s};',
				wp_json_encode( $payment_config_options ),
				wp_json_encode( $currencies_options )
			),
			'before'
		);
	}

	/**
	 * Register block types.
	 *
	 * @return void
	 */
	public function register_block_types() {
		register_block_type(
			__DIR__ . '/build',
			[
				'render_callback' => [ $this, 'knit_pay_payment_button_render_callback' ],
			]
		);
	}
}
