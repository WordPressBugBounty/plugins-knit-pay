<?php

namespace KnitPay\Gateways\SBIePay;

use Pronamic\WordPress\Pay\AbstractGatewayIntegration;
use Pronamic\WordPress\Pay\Core\IntegrationModeTrait;

/**
 * Title: SBIePay Integration
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 5.7.0
 * @since   5.7.0
 */
class Integration extends AbstractGatewayIntegration {
	/**
	 * REST route namespace.
	 *
	 * @var string
	 */
	const REST_ROUTE_NAMESPACE = 'knit-pay/sbiepay/v1';

	use IntegrationModeTrait;
	/**
	 * Construct SBIePay integration.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'id'          => 'sbi-e-pay',
				'name'        => 'SBIePay',
				'product_url' => 'http://go.thearrangers.xyz/sbiepay?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=product-url',
				'provider'    => 'sbi-e-pay',
				'supports'    => [
					'webhook',
					'webhook_log',
				],
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

		// Notifications controller.
		$notifications_controller = new WebhookController( $this );

		$notifications_controller->setup();
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
		
		// Get mode from Integration mode trait.
		$fields[] = $this->get_mode_settings_fields();

		// Merchant ID
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_sbiepay_merchant_id',
			'title'    => __( 'Merchant ID', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
		];

		// Encryption Key
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_sbiepay_encryption_key',
			'title'    => __( 'Encryption Key', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
		];

		// Webhook URL or Push Response URL.
		$fields[] = [
			'section'  => 'feedback',
			'title'    => \__( 'Push Response URL', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'large-text', 'code' ],
			'value'    => \rest_url( self::REST_ROUTE_NAMESPACE . '/push-response-listener/' . \wp_hash( home_url( '/' ) ) ),
			'readonly' => true,
		];

		// Return fields.
		return $fields;
	}

	public function get_config( $post_id ) {
		$config = new Config();

		$config->merchant_id    = $this->get_meta( $post_id, 'sbiepay_merchant_id' );
		$config->encryption_key = $this->get_meta( $post_id, 'sbiepay_encryption_key' );
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
