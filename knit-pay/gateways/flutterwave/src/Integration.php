<?php

namespace KnitPay\Gateways\Flutterwave;

use Pronamic\WordPress\Pay\AbstractGatewayIntegration;
use Pronamic\WordPress\Pay\Payments\Payment;

/**
 * Title: Flutterwave Integration
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 1.0.0
 * @since   4.8.0
 */
class Integration extends AbstractGatewayIntegration {
	/**
	 * Construct Flutterwave integration.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'id'          => 'flutterwave',
				'name'        => 'Flutterwave',
				'product_url' => 'https://www.flutterwave.com',
				'provider'    => 'flutterwave',
			]
		);

		parent::__construct( $args );
	}

	/**
	 * Get settings fields.
	 *
	 * @return array
	 */
	public function get_settings_fields() {
		$fields = [];

		// Secret Key.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_flutterwave_secret_key',
			'title'    => __( 'Secret Key', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
		];

		// Return fields.
		return $fields;
	}

	public function get_config( $post_id ) {
		$config = new Config();

		$config->secret_key = $this->get_meta( $post_id, 'flutterwave_secret_key' );

		return $config;
	}

	/**
	 * Get gateway.
	 *
	 * @param int $post_id Post ID.
	 * @return Gateway
	 */
	public function get_gateway( $config_id ) {
		return new Gateway( $this->get_config( $config_id ) );
	}
}
