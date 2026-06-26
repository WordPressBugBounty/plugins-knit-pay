<?php

namespace KnitPay\Gateways\Tosla;

use Pronamic\WordPress\Pay\AbstractGatewayIntegration;
use KnitPay\Gateways\IntegrationModeTrait;

/**
 * Title: Tosla Integration
 * Copyright: 2020-2026 Knit Pay
 *
 * @author  Knit Pay
 * @version 9.6.0.0
 * @since   9.6.0.0
 */
class Integration extends AbstractGatewayIntegration {
	use IntegrationModeTrait;

	/**
	 * Construct Tosla integration.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'id'       => 'tosla',
				'name'     => 'Tosla',
				'url'      => 'https://tosla.com/',
				'provider' => 'tosla',
			]
		);

		parent::__construct( $args );
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

		return $config->client_id;
	}

	/**
	 * Get settings fields.
	 *
	 * @return array
	 */
	public function get_settings_fields() {
		$fields = [];

		// Get mode from Integration mode trait.
		$fields[] = $this->get_mode_settings_fields();

		// Client ID.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_tosla_client_id',
			'title'    => __( 'Client ID', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'required' => true,
		];

		// API User.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_tosla_api_user',
			'title'    => __( 'API User', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'required' => true,
		];

		// API Pass.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_tosla_api_pass',
			'title'    => __( 'API Pass', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'required' => true,
		];

		// Return fields.
		return $fields;
	}

	/**
	 * Get config.
	 *
	 * @param int $post_id Post ID.
	 * @return Config
	 */
	public function get_config( $post_id ) {
		$config = new Config();

		$config->client_id = $this->get_meta( $post_id, 'tosla_client_id' );
		$config->api_user  = $this->get_meta( $post_id, 'tosla_api_user' );
		$config->api_pass   = $this->get_meta( $post_id, 'tosla_api_pass' );
		$config->mode       = $this->get_meta( $post_id, 'mode' );

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
		$gateway = new Gateway();

		$mode = Gateway::MODE_LIVE;
		if ( Gateway::MODE_TEST === $config->mode ) {
			$mode = Gateway::MODE_TEST;
		}

		$this->set_mode( $mode );
		$gateway->set_mode( $mode );
		$gateway->init( $config );

		return $gateway;
	}
}
