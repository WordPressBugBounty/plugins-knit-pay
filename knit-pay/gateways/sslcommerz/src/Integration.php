<?php

namespace KnitPay\Gateways\SSLCommerz;

use Pronamic\WordPress\Pay\AbstractGatewayIntegration;
use Pronamic\WordPress\Pay\Core\IntegrationModeTrait;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Plugin;

/**
 * Title: SSLCommerz Integration
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.80.0.0
 * @since   8.80.0.0
 */
class Integration extends AbstractGatewayIntegration {
	use IntegrationModeTrait;

	/**
	 * Construct SSLCommerz integration.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'id'          => 'sslcommerz',
				'name'        => 'SSLCommerz (Bangladesh)',
				'url'         => 'http://go.thearrangers.xyz/sslcommerz?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=',
				'product_url' => 'http://go.thearrangers.xyz/sslcommerz?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=product-url',
				'provider'    => 'sslcommerz',
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

		return $config->store_id;
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

		// Store ID.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_sslcommerz_store_id',
			'title'    => __( 'Store ID', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'required' => true,
		];

		// Store Password.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_sslcommerz_store_password',
			'title'    => __( 'Store Password', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'required' => true,
		];

		// Return fields.
		return $fields;
	}

	public function get_config( $post_id ) {
		$config = new Config();

		$config->store_id       = $this->get_meta( $post_id, 'sslcommerz_store_id' );
		$config->store_password = $this->get_meta( $post_id, 'sslcommerz_store_password' );
		$config->mode           = $this->get_meta( $post_id, 'mode' );

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
