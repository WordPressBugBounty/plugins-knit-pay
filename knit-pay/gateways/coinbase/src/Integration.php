<?php

namespace KnitPay\Gateways\Coinbase;

use Pronamic\WordPress\Pay\AbstractGatewayIntegration;

/**
 * Title: Coinbase Commerce Integration
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.77.0.0
 * @since   8.77.0.0
 */
class Integration extends AbstractGatewayIntegration {
	/**
	 * Construct Coinbase Commerce integration.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'id'            => 'coinbase',
				'name'          => 'Coinbase Commerce',
				'url'           => 'http://go.thearrangers.xyz/coinbase?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=',
				'product_url'   => 'http://go.thearrangers.xyz/coinbase?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=product-url',
				'dashboard_url' => 'http://go.thearrangers.xyz/coinbase?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=dashboard-url',
				'provider'      => 'coinbase',
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
	 * Get settings fields.
	 *
	 * @return array
	 */
	public function get_settings_fields() {
		$fields = [];
		
		// API Key.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_coinbase_api_key',
			'title'    => __( 'API Key', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( 'API Key', 'knit-pay-lang' ),
			'required' => true,
		];
		
		// Webhook URL.
		$fields[] = [
			'section'  => 'feedback',
			'title'    => \__( 'Webhook URL', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'large-text', 'code' ],
			'value'    => add_query_arg( 'kp_coinbase_webhook', '', home_url( '/' ) ),
			'readonly' => true,
			'tooltip'  => sprintf(
				/* translators: %s: Coinbase Commerce */
				__(
					'Copy the Webhook URL to the %s dashboard to receive automatic transaction status updates.',
					'knit-pay'
				),
				__( 'Coinbase Commerce', 'knit-pay-lang' )
			),
		];
		
		$fields[] = [
			'section'  => 'feedback',
			'meta_key' => '_pronamic_gateway_coinbase_webhook_shared_secret',
			'title'    => \__( 'Webhook Shared Secret', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
		];

		// Return fields.
		return $fields;
	}

	public function get_config( $post_id ) {
		$config = new Config();

		$config->api_key               = $this->get_meta( $post_id, 'coinbase_api_key' );
		$config->webhook_shared_secret = $this->get_meta( $post_id, 'coinbase_webhook_shared_secret' );

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
		
		$this->set_mode( $mode );
		$gateway->set_mode( $mode );
		$gateway->init( $config );
		
		return $gateway;
	}
}
