<?php

namespace KnitPay\Gateways\Payrexx;

use Pronamic\WordPress\Pay\AbstractGatewayIntegration;
use Pronamic\WordPress\Pay\Core\IntegrationModeTrait;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Plugin;

/**
 * Title: Payrexx Integration
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.82.0.0
 * @since   8.82.0.0
 */
class Integration extends AbstractGatewayIntegration {
	use IntegrationModeTrait;
	
	/**
	 * Construct Payrexx integration.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'id'          => 'payrexx',
				'name'        => 'Payrexx',
				'url'         => 'http://go.thearrangers.xyz/payrexx?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=',
				'product_url' => 'http://go.thearrangers.xyz/payrexx?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=product-url',
				'provider'    => 'payrexx',
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

		return $config->instance;
	}

	/**
	 * Get settings fields.
	 *
	 * @return array
	 */
	public function get_settings_fields() {
		$fields = [];

		// 'Instance Name'.
		$fields[] = [
			'section'     => 'general',
			'meta_key'    => '_pronamic_gateway_payrexx_instance',
			'title'       => __( 'Instance Name', 'knit-pay-lang' ),
			'type'        => 'text',
			'classes'     => [ 'regular-text', 'code' ],
			'description' => __( 'The instance name is your Payrexx account name. You find it in the URL when logged in INSTANCENAME.payrexx.com.', 'wc-payrexx-gateway' ),
			'required'    => true,
		];

		// API Key.
		$fields[] = [
			'section'     => 'general',
			'meta_key'    => '_pronamic_gateway_payrexx_api_key',
			'title'       => __( 'API Key', 'knit-pay-lang' ),
			'type'        => 'text',
			'classes'     => [ 'regular-text', 'code' ],
			'description' => __( 'Paste the API key from the integrations page of your Payrexx merchant backend here', 'wc-payrexx-gateway' ),
			'required'    => true,
		];

		// Return fields.
		return $fields;
	}

	public function get_config( $post_id ) {
		$config = new Config();

		$config->instance = $this->get_meta( $post_id, 'payrexx_instance' );
		$config->api_key  = $this->get_meta( $post_id, 'payrexx_api_key' );

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
		
		$gateway->init( $config );
		
		return $gateway;
	}
}
