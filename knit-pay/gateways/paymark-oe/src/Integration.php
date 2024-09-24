<?php

namespace KnitPay\Gateways\PaymarkOE;

use Pronamic\WordPress\Pay\AbstractGatewayIntegration;
use Pronamic\WordPress\Pay\Core\IntegrationModeTrait;
use Pronamic\WordPress\Pay\Payments\Payment;

/**
 * Title: Paymark OE Integration
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 5.2.0
 * @since   5.2.0
 */
class Integration extends AbstractGatewayIntegration {
	use IntegrationModeTrait;
	
	/**
	 * Construct Paymark OE integration.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'id'       => 'paymark-oe',
				'name'     => 'Paymark - Online EFTPOS',
				'provider' => 'paymark-oe',
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
		
		// Get mode from Integration mode trait.
		$fields[] = $this->get_mode_settings_fields();

		// Consumer Key.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_paymark_oe_consumer_key',
			'title'    => __( 'Consumer Key', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
		];

		// Consumer Secret.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_paymark_oe_consumer_secret',
			'title'    => __( 'Consumer Secret', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
		];

		// Merchant Id Code.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_paymark_oe_merchant_id_code',
			'title'    => __( 'Merchant Id Code', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
		];

		// Return fields.
		return $fields;
	}

	public function get_config( $post_id ) {
		$config = new Config();

		$config->consumer_key     = $this->get_meta( $post_id, 'paymark_oe_consumer_key' );
		$config->consumer_secret  = $this->get_meta( $post_id, 'paymark_oe_consumer_secret' );
		$config->merchant_id_code = $this->get_meta( $post_id, 'paymark_oe_merchant_id_code' );
		$config->mode             = $this->get_meta( $post_id, 'mode' );

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
