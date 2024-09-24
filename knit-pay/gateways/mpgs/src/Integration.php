<?php

namespace KnitPay\Gateways\MPGS;

use Pronamic\WordPress\Pay\AbstractGatewayIntegration;
use Pronamic\WordPress\Pay\Core\IntegrationModeTrait;
use Pronamic\WordPress\Pay\Payments\Payment;

/**
 * Title: MPGS Integration
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.81.0.0
 * @since   8.81.0.0
 */
class Integration extends AbstractGatewayIntegration {
	use IntegrationModeTrait;
	
	/**
	 * Construct MPGS integration.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'id'            => 'mpgs',
				'name'          => 'Mastercard Payment Gateway Services',
				'url'           => 'http://go.thearrangers.xyz/mpgs?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=',
				'product_url'   => 'http://go.thearrangers.xyz/mpgs?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=product-url',
				'dashboard_url' => 'http://go.thearrangers.xyz/mpgs?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=dashboard-url',
				'provider'      => 'mpgs',
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
		
		return $config->merchant_id;
	}

	/**
	 * Get settings fields.
	 *
	 * @return array
	 */
	public function get_settings_fields() {
		$fields = [];

		// MPGS URL.
		$fields[] = [
			'section'     => 'general',
			'filter'      => FILTER_SANITIZE_URL,
			'meta_key'    => '_pronamic_gateway_mpgs_url',
			'title'       => __( 'MPGS URL', 'knit-pay-lang' ),
			'type'        => 'text',
			'classes'     => [ 'regular-text', 'code' ],
			'description' => __( 'MPGS URL, given by the Bank. This is an example: https://ap-gateway.mastercard.com/', 'knit-pay-lang' ),
			'required'    => true,
		];

		// Merchant ID.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_mpgs_merchant_id',
			'title'    => __( 'Merchant ID', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( 'Merchant ID, given by the Bank', 'knit-pay-lang' ),
			'required' => true,
		];

		// Authentication Password.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_mpgs_auth_pass',
			'title'    => __( 'Authentication Password', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( 'Authentication Password, given by the Bank', 'knit-pay-lang' ),
			'required' => true,
		];

		// Return fields.
		return $fields;
	}

	public function get_config( $post_id ) {
		$config = new Config();

		$config->mpgs_url    = $this->get_meta( $post_id, 'mpgs_url' );
		$config->merchant_id = $this->get_meta( $post_id, 'mpgs_merchant_id' );
		$config->auth_pass   = $this->get_meta( $post_id, 'mpgs_auth_pass' );

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
