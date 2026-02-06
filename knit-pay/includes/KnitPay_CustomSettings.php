<?php

use Pronamic\WordPress\Html\Element;
use Pronamic\WordPress\Money\Currencies;
use Pronamic\WordPress\Money\Currency;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class KnitPay_CustomSettings {
	public function __construct() {
		// Actions.
		add_action( 'admin_init', [ $this, 'admin_init' ] );
	}

	/**
	 * Admin initialize.
	 *
	 * @return void
	 */
	public function admin_init() {
		\register_setting(
			'pronamic_pay',
			'knit_pay_currency_exchange_mode',
			[
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'auto_free',
			]
		);

		\add_settings_field(
			'knit_pay_currency_exchange_mode',
			\__( 'Currency Exchange Mode', 'knit-pay-lang' ),
			[ $this, 'input_select' ],
			'pronamic_pay',
			'pronamic_pay_general',
			[
				'description' => \__( 'In some payment gateways, store base currency is diffent than the currency of the payment. In that case, currency exchange mode will be used to convert the amount.', 'knit-pay-lang' ),
				'label_for'   => 'knit_pay_currency_exchange_mode',
				'options'     => [
					'disable'   => 'Disable',
					// 'manual' => 'Manual (fetched from the gateway configuration page)',
					'auto_free' => 'Auto (fetched automatically using the free APIs)',
				],
				'default'     => 'auto_free',
			]
		);
	}

	/**
	 * Input Field.
	 *
	 * @param array $args Arguments.
	 * @return void
	 */
	public function input_field( $args ) {
		$args['id']   = $args['label_for'];
		$args['name'] = $args['label_for'];

		$args['value'] = get_option( $args['name'], '' );

		$element = new Element( 'input', $args );
		$element->output();

		self::print_description( $args );
	}

	/**
	 * Input page.
	 *
	 * @param array $args Arguments.
	 * @return void
	 */
	public function input_select( $args ) {
		$args['id']   = $args['label_for'];
		$args['name'] = $args['label_for'];

		$options = $args['options'];
		unset( $args['options'] );

		$selected_value = \get_option( $args['name'] );
		if ( ! $selected_value ) {
			$selected_value = $args['default'];
		}

		$element = new Element( 'select', $args );

		foreach ( $options as $key => $label ) {
			$option = new Element( 'option', [ 'value' => $key ] );

			$option->children[] = $label;

			if ( $selected_value === (string) $key ) {
				$option->attributes['selected'] = 'selected';
			}

			$element->children[] = $option;
		}

		$element->output();

		self::print_description( $args );
	}

	public static function print_description( $args ) {
		if ( isset( $args['description'] ) ) {
			printf(
				'<p class="pronamic-pay-description description">%s</p>',
				\wp_kses(
					$args['description'],
					[
						'a'    => [
							'href'   => true,
							'target' => true,
						],
						'br'   => [],
						'code' => [],
					]
				)
			);
		}
	}
}

new KnitPay_CustomSettings();
