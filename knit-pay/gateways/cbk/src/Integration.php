<?php

namespace KnitPay\Gateways\CBK;

use Pronamic\WordPress\Pay\AbstractGatewayIntegration;
use Pronamic\WordPress\Pay\Core\IntegrationModeTrait;

/**
 * Title: CBK (Commercial Bank of Kuwait - Al-Tijari) Integration
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 6.68.0.0
 * @since   6.68.0.0
 */
class Integration extends AbstractGatewayIntegration {
	use IntegrationModeTrait;
	
	/**
	 * Construct CBK (Commercial Bank of Kuwait - Al-Tijari) integration.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'id'          => 'cbk',
				'name'        => 'Commercial Bank of Kuwait - Al-Tijari',
				'url'         => 'http://go.thearrangers.xyz/cbk?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=',
				'product_url' => 'http://go.thearrangers.xyz/cbk?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=product-url',
				'provider'    => 'cbk',
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

		// Merchant API ID.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_cbk_client_id',
			'title'    => __( 'Merchant API ID [ClientId]', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
		];
		
		// Merchant API Password.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_cbk_client_secret',
			'title'    => __( 'Merchant API Password [ClientSecret]', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
		];
		
		// Merchant Encrypted Account Key.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_cbk_encrypt_key',
			'title'    => __( 'Merchant Encrypted account key [ENCRP_KEY]', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
		];

		// Return fields.
		return $fields;
	}

	public function get_config( $post_id ) {
		$config = new Config();

		$config->client_id     = $this->get_meta( $post_id, 'cbk_client_id' );
		$config->client_secret = $this->get_meta( $post_id, 'cbk_client_secret' );
		$config->encrypt_key   = $this->get_meta( $post_id, 'cbk_encrypt_key' );
		$config->mode          = $this->get_meta( $post_id, 'mode' );

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
		if ( Gateway::MODE_TEST === $config->mode ) {
			$mode = Gateway::MODE_TEST;
		}
		
		$this->set_mode( $mode );
		$gateway->set_mode( $mode );
		$gateway->init( $config );
		
		return $gateway;
	}
}
