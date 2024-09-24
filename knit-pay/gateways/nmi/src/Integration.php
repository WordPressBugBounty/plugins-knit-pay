<?php

namespace KnitPay\Gateways\NMI;

use Pronamic\WordPress\Pay\AbstractGatewayIntegration;
use Pronamic\WordPress\Pay\Core\IntegrationModeTrait;
use Pronamic\WordPress\Pay\Payments\Payment;

/**
 * Title: NMI Integration
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.83.0.0
 * @since   8.83.0.0
 */
class Integration extends AbstractGatewayIntegration {
	use IntegrationModeTrait;

	/**
	 * Construct NMI integration.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'id'            => 'nmi',
				'name'          => 'NMI (Beta)',
				'url'           => 'http://go.thearrangers.xyz/nmi?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=',
				'product_url'   => 'http://go.thearrangers.xyz/nmi?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=product-url',
				'dashboard_url' => 'http://go.thearrangers.xyz/nmi?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=dashboard-url',
				'provider'      => 'nmi',
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

		return $config->public_key;
	}

	/**
	 * Get settings fields.
	 *
	 * @return array
	 */
	public function get_settings_fields() {
		$fields = [];

		// Private Key.
		$fields[] = [
			'section'     => 'general',
			'meta_key'    => '_pronamic_gateway_nmi_private_key',
			'title'       => __( 'Private Key', 'knit-pay-lang' ),
			'type'        => 'text',
			'classes'     => [ 'regular-text', 'code' ],
			'description' => __( 'Used for authenticating transactions. Make sure the private key you enter here has "API" permission enabled.', 'knit-pay-lang' ),
			'required'    => true,
		];

		// Public Tokenization Key.
		$fields[] = [
			'section'     => 'general',
			'meta_key'    => '_pronamic_gateway_nmi_public_key',
			'title'       => __( 'Public Tokenization Key', 'knit-pay-lang' ),
			'type'        => 'text',
			'classes'     => [ 'regular-text', 'code' ],
			'description' => __( 'Used for Collect.js tokenization for PCI compliance. Leave it empty ONLY if you are facing Javascript issues at checkout and the plugin will default to Direct Post method.', 'knit-pay-lang' ),
			'required'    => true,
		];

		// Return fields.
		return $fields;
	}

	public function get_config( $post_id ) {
		$config = new Config();

		$config->private_key = $this->get_meta( $post_id, 'nmi_private_key' );
		$config->public_key  = $this->get_meta( $post_id, 'nmi_public_key' );

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
