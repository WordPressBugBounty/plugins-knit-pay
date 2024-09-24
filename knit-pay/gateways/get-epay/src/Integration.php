<?php

namespace KnitPay\Gateways\GetEpay;

use Pronamic\WordPress\Pay\AbstractGatewayIntegration;
use Pronamic\WordPress\Pay\Core\IntegrationModeTrait;

/**
 * Title: Get ePay Integration
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.87.0.0
 * @since   8.87.0.0
 */
class Integration extends AbstractGatewayIntegration {
	use IntegrationModeTrait;

	/**
	 * Construct Get ePay integration.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'id'       => 'get-epay',
				'name'     => 'Get ePay',
				'provider' => 'get-epay',
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

		// MID.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_get_epay_mid',
			'title'    => __( 'MID', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'required' => true,
		];

		// Terminal ID.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_get_epay_terminal_id',
			'title'    => __( 'Terminal ID', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'required' => true,
		];

		// Key.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_get_epay_key',
			'title'    => __( 'Key', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'required' => true,
		];

		// IV.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_get_epay_iv',
			'title'    => __( 'IV', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'required' => true,
		];

		// Return fields.
		return $fields;
	}

	public function get_config( $post_id ) {
		$config = new Config();

		$config->mid         = $this->get_meta( $post_id, 'get_epay_mid' );
		$config->terminal_id = $this->get_meta( $post_id, 'get_epay_terminal_id' );
		$config->key         = $this->get_meta( $post_id, 'get_epay_key' );
		$config->iv          = $this->get_meta( $post_id, 'get_epay_iv' );
		$config->mode        = $this->get_meta( $post_id, 'mode' );

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
