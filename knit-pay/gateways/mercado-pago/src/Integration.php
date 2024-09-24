<?php

namespace KnitPay\Gateways\MercadoPago;

use Pronamic\WordPress\Pay\AbstractGatewayIntegration;
use Pronamic\WordPress\Pay\Core\IntegrationModeTrait;

/**
 * Title: Mercado Pago Integration
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.88.0.0
 * @since   8.88.0.0
 */
class Integration extends AbstractGatewayIntegration {
	use IntegrationModeTrait;
	
	/**
	 * Construct Mercado Pago integration.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'id'       => 'mercado-pago',
				'name'     => 'Mercado Pago',
				'provider' => 'mercado-pago',
			]
		);

		parent::__construct( $args );
		
		// Webhook Listener.
		// TODO @see https://www.mercadopago.com.br/developers/en/docs/checkout-pro/additional-content/your-integrations/notifications/webhooks
	}

	/**
	 * Setup.
	 */
	public function setup() {
		// Display ID on Configurations page.
		\add_filter(
			'pronamic_gateway_configuration_display_value_' . $this->get_id(),
			[ $this, 'gateway_configuration_display_value' ],
			10,
			2
		);
	}

	/**
	 * Gateway configuration display value.
	 *
	 * @param string $display_value Display value.
	 * @param int    $post_id       Gateway configuration post ID.
	 * @return string
	 */
	public function gateway_configuration_display_value( $display_value, $post_id ) {
		$config = $this->get_config( $post_id );

		return $config->public_key;
	}

	/**
	 * Get settings fields.
	 *
	 * @return array
	 */
	public function get_settings_fields() {
		$fields = [];

		// Public Key.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_mercado_pago_public_key',
			'title'    => __( 'Public Key', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'required' => true,
		];

		// Access Token.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_mercado_pago_access_token',
			'title'    => __( 'Access Token', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'required' => true,
		];
		
		// TODO
		// statement_descriptor
		// https://www.mercadopago.com.br/developers/en/docs/checkout-pro/checkout-customization/preferences/invoice-description

		// Return fields.
		return $fields;
	}

	public function get_config( $post_id ) {
		$config = new Config();

		$config->public_key   = $this->get_meta( $post_id, 'mercado_pago_public_key' );
		$config->access_token = $this->get_meta( $post_id, 'mercado_pago_access_token' );

		return $config;
	}

	/**
	 * Get gateway.
	 *
	 * @param int $post_id Post ID.
	 * @return Gateway
	 */
	public function get_gateway( $config_id ) {
		$config  = $this->get_config( $config_id );
		$gateway = new Gateway( $config );
		
		$mode = Gateway::MODE_LIVE;
		
		$this->set_mode( $mode );
		$gateway->set_mode( $mode );
		$gateway->init( $config );
		
		return $gateway;
	}
}
