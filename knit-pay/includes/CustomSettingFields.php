<?php

namespace KnitPay;

use Pronamic\WordPress\Html\Element;
use Pronamic\WordPress\Money\Currencies;
use Pronamic\WordPress\Money\Currency;
use Pronamic\WordPress\Pay\Plugin;
use KnitPay\Utils;

class CustomSettingFields {

	/**
	 * Select Configuration Field Callback.
	 *
	 * @param array $args Arguments.
	 * @return void
	 */
	public static function select_configuration( $args ) {
		$args['id']   = $args['label_for'];
		$args['name'] = $args['label_for'];

		$configurations    = Plugin::get_config_select_options();
		$configurations[0] = __( '— Default Gateway —', 'knit-pay-lang' );

		$element = new Element( 'select', $args );

		if ( ! empty( $args['value'] ) ) {
			$selected_config_id = $args['value'];
		} else {
			$selected_config_id = Utils::get_gateway_config_id();
		}

		if ( empty( $selected_config_id ) ) {
			$selected_config_id = get_option( 'pronamic_pay_config_id' );
		}

		foreach ( $configurations as $key => $label ) {
			$option = new Element( 'option', [ 'value' => $key ] );

			$option->children[] = $label;

			if ( intval( $selected_config_id ) === $key ) {
				$option->attributes['selected'] = 'selected';
			}

			$element->children[] = $option;
		}

		$element->output();

		self::print_description( $args );
	}

	/**
	 * Input Field Callback.
	 *
	 * @param array $args Arguments.
	 * @return void
	 */
	public static function input_field( $args ) {
		$args['id']   = $args['label_for'];
		$args['name'] = $args['label_for'];

		$element = new Element( 'input', $args );
		$element->output();

		CustomSettingFields::print_description( $args );
	}

	/**
	 * Select Currency Field Callback.
	 *
	 * @param array $args Arguments.
	 * @return void
	 */
	public static function select_currency( $args ) {
		$currency_default = Currency::get_instance( 'INR' );

		$args['id']   = $args['label_for'];
		$args['name'] = $args['label_for'];

		$element = new Element( 'select', $args );

		foreach ( Currencies::get_currencies() as $currency ) {
			$option = new Element( 'option', [ 'value' => $currency->get_alphabetic_code() ] );

			$label = $currency->get_alphabetic_code();

			$symbol = $currency->get_symbol();

			if ( null !== $symbol ) {
				$label = sprintf( '%s (%s)', $label, $symbol );
			}

			$option->children[] = $label;

			if ( $currency_default->get_alphabetic_code() === $currency->get_alphabetic_code() ) {
				$option->attributes['selected'] = 'selected';
			}

			$element->children[] = $option;
		}

		$element->output();

		CustomSettingFields::print_description( $args );
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
