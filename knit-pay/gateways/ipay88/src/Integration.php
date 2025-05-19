<?php

namespace KnitPay\Gateways\IPay88;

use Pronamic\WordPress\Pay\AbstractGatewayIntegration;
use Pronamic\WordPress\Pay\Core\IntegrationModeTrait;

/**
 * Title: iPay88 Integration
 * Copyright: 2020-2025 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.96.0.0
 * @since   8.96.0.0
 */
class Integration extends AbstractGatewayIntegration {
	use IntegrationModeTrait;
	
	/**
	 * Construct iPay88 integration.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'id'       => 'ipay88',
				'name'     => 'iPay88',
				'provider' => 'ipay88',
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

		return $config->merchant_code;
	}

	/**
	 * Get settings fields.
	 *
	 * @return array
	 */
	public function get_settings_fields() {
		$fields = [];

		// Country.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_ipay88_country',
			'title'    => __( 'Country', 'knit-pay-lang' ),
			'type'     => 'select',
			'options'  => [
				'my' => 'Malaysia',
			],
			'default'  => 'my',
		];

		// Merchant Code
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_ipay88_merchant_code',
			'title'    => __( 'Merchant Code', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( 'Your iPay88 Merchant Code', 'knit-pay-lang' ),
			'required' => true,
		];

		// Merchant Key
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_ipay88_merchant_key',
			'title'    => __( 'Merchant Key', 'knit-pay-lang' ),
			'type'     => 'password',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( 'Your iPay88 Merchant Key', 'knit-pay-lang' ),
			'required' => true,
		];

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

		$config->country       = $this->get_meta( $post_id, 'ipay88_country' );
		$config->merchant_code = $this->get_meta( $post_id, 'ipay88_merchant_code' );
		$config->merchant_key  = $this->get_meta( $post_id, 'ipay88_merchant_key' );

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
		
		$this->set_mode( $mode );
		$gateway->set_mode( $mode );
		$gateway->init( $config );
		
		return $gateway;
	}
}
