<?php

namespace KnitPay\Gateways\CMI;

use Pronamic\WordPress\Pay\AbstractGatewayIntegration;
use Pronamic\WordPress\Pay\Core\IntegrationModeTrait;

/**
 * Title: CMI Integration
 * Copyright: 2020-2025 Knit Pay
 *
 * @author  Knit Pay
 * @version 7.71.0.0
 * @since   7.71.0.0
 */
class Integration extends AbstractGatewayIntegration {
	use IntegrationModeTrait;
	
	/**
	 * Construct CMI integration.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'id'          => 'cmi',
				'name'        => 'CMI aka Maroc Telecommerce',
				'url'         => 'https://www.cmi.co.ma/',
				'product_url' => 'https://www.cmi.co.ma/',
				'provider'    => 'cmi',
				'supports'    => [
					'webhook',
					'webhook_log',
					'webhook_no_config',
				],
			]
		);

		parent::__construct( $args );

		// Webhook Listener.
		$function = [ __NAMESPACE__ . '\Listener', 'listen' ];

		if ( ! has_action( 'wp_loaded', $function ) ) {
			add_action( 'wp_loaded', $function );
		}
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
			'section'     => 'general',
			'meta_key'    => '_pronamic_gateway_cmi_client_id',
			'title'       => __( 'Client ID', 'knit-pay-lang' ),
			'type'        => 'text',
			'classes'     => [ 'regular-text', 'code' ],
			'description' => __( 'Merchant ID', 'knit-pay-lang' ),
		];
		
		// Store Key.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_cmi_store_key',
			'title'    => __( 'Store Key', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
		];

		// Username.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_cmi_username',
			'title'    => __( 'Username', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
		];

		// Password.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_cmi_password',
			'title'    => __( 'Password', 'knit-pay-lang' ),
			'type'     => 'password',
			'classes'  => [ 'regular-text', 'code' ],
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

		$config->client_id = $this->get_meta( $post_id, 'cmi_client_id' );
		$config->store_key = $this->get_meta( $post_id, 'cmi_store_key' );
		$config->username  = $this->get_meta( $post_id, 'cmi_username' );
		$config->password  = $this->get_meta( $post_id, 'cmi_password' );
		$config->mode      = $this->get_meta( $post_id, 'mode' );

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
