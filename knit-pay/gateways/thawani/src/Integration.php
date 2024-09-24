<?php

namespace KnitPay\Gateways\Thawani;

use Pronamic\WordPress\Pay\AbstractGatewayIntegration;
use Pronamic\WordPress\Pay\Core\IntegrationModeTrait;
use Pronamic\WordPress\Pay\Payments\Payment;

/**
 * Title: Thawani Integration
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 6.70.0.0
 * @since   6.70.0.0
 */
class Integration extends AbstractGatewayIntegration {
	use IntegrationModeTrait;
	
	/**
	 * Construct Thawani integration.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'id'            => 'thawani',
				'name'          => 'Thawani',
				'url'           => 'http://go.thearrangers.xyz/thawani?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=',
				'product_url'   => 'http://go.thearrangers.xyz/thawani?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=product-url',
				'dashboard_url' => 'http://go.thearrangers.xyz/thawani?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=dashboard-url',
				'provider'      => 'thawani',
			]
		);

		parent::__construct( $args );
	}
	
	/**
	 * Setup gateway integration.
	 *
	 * @return void
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
		
		return $config->publishable_key;
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
		
		// Publishable Key.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_thawani_publishable_key',
			'title'    => __( 'Publishable Key', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( 'Publishable Key', 'knit-pay-lang' ),
			'required' => true,
		];
		
		// Secret Key.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_thawani_secret_key',
			'title'    => __( 'Secret Key', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( 'API Key', 'knit-pay-lang' ),
			'required' => true,
		];

		// Return fields.
		return $fields;
	}

	public function get_config( $post_id ) {
		$config = new Config();

		$config->publishable_key = $this->get_meta( $post_id, 'thawani_publishable_key' );
		$config->secret_key      = $this->get_meta( $post_id, 'thawani_secret_key' );
		$config->mode            = $this->get_meta( $post_id, 'mode' );

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
