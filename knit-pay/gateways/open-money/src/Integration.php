<?php

namespace KnitPay\Gateways\OpenMoney;

use Pronamic\WordPress\Pay\AbstractGatewayIntegration;
use Pronamic\WordPress\Pay\Core\IntegrationModeTrait;
use Pronamic\WordPress\Pay\Payments\Payment;

/**
 * Title: Open Money Integration
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 5.3.0
 * @since   5.3.0
 */
class Integration extends AbstractGatewayIntegration {
	use IntegrationModeTrait;
	/**
	 * Construct Open Money integration.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'id'            => 'open-money',
				'name'          => 'Open Money',
				'url'           => 'https://www.knitpay.org/integrate-open-money-payment-gateway-with-various-wordpress-plugins/?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=',
				'product_url'   => 'http://go.thearrangers.xyz/open-money?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=product-url',
				'dashboard_url' => 'http://go.thearrangers.xyz/open-money?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=dashboard-url',
				'provider'      => 'open-money',
			]
		);

		parent::__construct( $args );
	}

	/**
	 * Setup.
	 */
	public function setup() {
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

		return $config->api_key;
	}

	/**
	 * Get settings fields.
	 *
	 * @return array
	 */
	public function get_settings_fields() {
		$fields = [];

		$fields[] = [
			'section'     => 'general',
			'type'        => 'custom',
			'title'       => 'Sign Up',
			'description' => sprintf(
				/* translators: 1: Open Money */
				__( 'Before proceeding, kindly create an account at %1$s if you don\'t have one already.%2$s', 'knit-pay-lang' ),
				__( 'Open Money', 'knit-pay-lang' ),
				'<br><a class="button button-primary" target="_blank" href="http://go.thearrangers.xyz/open-money?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=help-signup"
                     role="button"><strong>Sign Up Now</strong></a>'
			),
		];
		
		// Get mode from Integration mode trait.
		$fields[] = $this->get_mode_settings_fields();

		// API Key.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_open_money_api_key',
			'title'    => __( 'API Key', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
		];

		// API Secret.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_open_money_api_secret',
			'title'    => __( 'API Secret', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
		];

		// Return fields.
		return $fields;
	}

	public function get_config( $post_id ) {
		$config = new Config();

		$config->api_key    = $this->get_meta( $post_id, 'open_money_api_key' );
		$config->api_secret = $this->get_meta( $post_id, 'open_money_api_secret' );
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
