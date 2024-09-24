<?php

namespace KnitPay\Gateways\MultiGateway;

use Pronamic\WordPress\Pay\AbstractGatewayIntegration;
use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Util;


/**
 * Title: Multi Gateway Integration
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 1.0.0
 * @since   4.0.0
 */
class Integration extends AbstractGatewayIntegration {
	/**
	 * Construct Multi Gateway integration.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'id'          => 'multi-gateway',
				'name'        => 'Multi Gateway',
				'product_url' => 'https://www.knitpay.org/indian-payment-gateways-supported-in-knit-pay/',
				'provider'    => 'multi-gateway',
			]
		);

		parent::__construct( $args );
	}

	/**
	 * Get settings fields.
	 *
	 * @return array
	 */
	public function get_settings_fields() {
		$fields = [];
		
		// Gateway Selection Mode.
		 $fields[] = [
			 'section'  => 'general',
			 'filter'   => FILTER_SANITIZE_NUMBER_INT,
			 'meta_key' => '_pronamic_gateway_multi_gateway_gateway_selection_mode',
			 'title'    => __( 'Gateway Selection Mode', 'knit-pay-lang' ),
			 'type'     => 'select',
			 'options'  => [
				 Config::SELECTION_MANUAL_MODE => 'Gateway Selected by Customer',
				 Config::SELECTION_RANDOM_MODE => 'Gateway Randomly Selected',
			 ],
			 'default'  => Config::SELECTION_MANUAL_MODE,
		 ];

		 // Enabled Payment Methods.
		 $fields[] = [
			 'section'  => 'general',
			 'meta_key' => 'multi_gateway_enabled_payment_gateways',
			 'title'    => __( 'Enabled Payment Gateways', 'knit-pay-lang' ),
			 'type'     => 'description',
			 'callback' => [ $this, 'field_enabled_payment_gateways' ],
		 ];

		 // Return fields.
		 return $fields;
	}

	/**
	 * Field Enabled Payment Methods.
	 *
	 * @param array<string, mixed> $field Field.
	 * @return void
	 */
	public function field_enabled_payment_gateways( $field ) {
		$gateways = Plugin::get_config_select_options();
		unset( $gateways[0] );
		ksort( $gateways );

		$config_id = (int) \get_the_ID();
		$config    = self::get_config( $config_id );

		$attributes['type'] = 'checkbox';
		$attributes['id']   = '_pronamic_gateway_multi_gateway_enabled_payment_gateways';
		$attributes['name'] = $attributes['id'] . '[]';

		foreach ( $gateways as $value => $label ) {
			$attributes['value'] = $value;

			printf(
				'<input %s %s />',
	            // @codingStandardsIgnoreStart
	            Util::array_to_html_attributes( $attributes ),
	            // @codingStandardsIgnoreEnd
				in_array( $value, $config->enabled_payment_gateways ) ? 'checked ' : ''
			);

			printf( ' ' );

			printf(
				'<label for="%s">%s</label><br>',
				esc_attr( $attributes['id'] ),
				esc_html( $label )
			);
		}
		printf( '<br>Configurations can be created in Knit Pay gateway configurations page at <a href="' . admin_url() . 'edit.php?post_type=pronamic_gateway">"Knit Pay >> Configurations"</a>.' );
	}

	public function get_config( $post_id ) {
		$config = new Config();

		$config->gateway_selection_mode   = $this->get_meta( $post_id, 'multi_gateway_gateway_selection_mode' );
		$config->enabled_payment_gateways = $this->get_meta( $post_id, 'multi_gateway_enabled_payment_gateways' );

		if ( '' === $config->gateway_selection_mode ) {
			$config->gateway_selection_mode = 0;
		}
		if ( empty( $config->enabled_payment_gateways ) ) {
			$config->enabled_payment_gateways = [];
		}

		return $config;
	}

	/**
	 * Get gateway.
	 *
	 * @param int $post_id Post ID.
	 * @return Gateway
	 */
	public function get_gateway( $config_id ) {
		return new Gateway( $this->get_config( $config_id ) );
	}

	/**
	 * Save post.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save_post( $post_id ) {
		$post = filter_input_array( INPUT_POST );
		if ( empty( $post['_pronamic_gateway_multi_gateway_enabled_payment_gateways'] ) ) {
			$post['_pronamic_gateway_multi_gateway_enabled_payment_gateways'] = [];
		}
		update_post_meta( $post_id, '_pronamic_gateway_multi_gateway_enabled_payment_gateways', $post['_pronamic_gateway_multi_gateway_enabled_payment_gateways'] );
	}
}
