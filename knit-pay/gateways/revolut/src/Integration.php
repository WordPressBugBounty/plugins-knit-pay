<?php

namespace KnitPay\Gateways\Revolut;

use Pronamic\WordPress\Pay\AbstractGatewayIntegration;
use Pronamic\WordPress\Pay\Core\IntegrationModeTrait;
use Pronamic\WordPress\Pay\Payments\Payment;

/**
 * Title: Revolut Integration
 * Copyright: 2020-2026 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.98.0.0
 * @since   8.98.0.0
 */
class Integration extends AbstractGatewayIntegration {
	use IntegrationModeTrait;
	
	/**
	 * Construct the integration
	 *
	 * @param array $args Arguments for integration
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'id'          => 'revolut',
				'name'        => 'Revolut',
				'provider'    => 'revolut',
				'product_url' => 'https://www.revolut.com/business/online-payments',
			]
		);

		parent::__construct( $args );
	}

	/**
	 * Setup the integration
	 */
	public function setup() {
		// Display gateway identifier on configuration page
		\add_filter(
			'pronamic_gateway_configuration_display_value_' . $this->get_id(),
			[ $this, 'gateway_configuration_display_value' ],
			10,
			2
		);
	}

	/**
	 * Get the value to display on the gateway configuration page
	 *
	 * @param string $display_value Current display value
	 * @param int    $post_id       Gateway configuration post ID
	 * @return string Display value for the configuration
	 */
	public function gateway_configuration_display_value( $display_value, $post_id ) {
		$config = $this->get_config( $post_id );
		
		// Return last 4 characters of API Secret Key for identification
		if ( ! empty( $config->api_secret_key ) ) {
			return '...' . substr( $config->api_secret_key, -4 );
		}
		
		return '';
	}

	/**
	 * Get settings fields for the gateway configuration
	 *
	 * @return array Array of settings field definitions
	 */
	public function get_settings_fields() {
		$fields = [];
		
		// Mode selection (Test/Live)
		$fields[] = $this->get_mode_settings_fields();
		
		// API Secret Key
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_revolut_api_secret_key',
			'title'    => __( 'API Secret Key', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'required' => true,
			'tooltip'  => __( 'Your Revolut Merchant API Secret Key (Bearer token)', 'knit-pay-lang' ),
		];

		return $fields;
	}

	/**
	 * Get gateway configuration from post meta
	 * 
	 * @param int $post_id Configuration post ID
	 * @return Config Configuration object
	 */
	public function get_config( $post_id ) {
		$config = new Config();
		
		// Get the mode (test/live)
		$config->mode = $this->get_meta( $post_id, 'mode' );
		
		// Load configuration fields
		$config->api_secret_key = $this->get_meta( $post_id, 'revolut_api_secret_key' );

		return $config;
	}

	/**
	 * Get the gateway instance
	 *
	 * @param int $config_id Configuration post ID
	 * @return Gateway Gateway instance
	 */
	public function get_gateway( $config_id ) {
		// Load configuration
		$config = $this->get_config( $config_id );
		
		// Create gateway instance
		$gateway = new Gateway();
		
		// Determine the mode
		$mode = $this->get_mode();
		
		// Set mode on both integration and gateway
		$this->set_mode( $mode );
		$gateway->set_mode( $mode );
		
		// Initialize gateway with configuration
		$gateway->init( $config );
		
		return $gateway;
	}
}
