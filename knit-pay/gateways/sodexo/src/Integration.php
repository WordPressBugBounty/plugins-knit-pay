<?php

namespace KnitPay\Gateways\Sodexo;

use Pronamic\WordPress\Pay\AbstractGatewayIntegration;
use Pronamic\WordPress\Pay\Core\IntegrationModeTrait;
use Pronamic\WordPress\Pay\Payments\Payment;

/**
 * Title: Sodexo Integration
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 1.0.0
 * @since   3.3.0
 */
class Integration extends AbstractGatewayIntegration {
	use IntegrationModeTrait;
	
	/**
	 * Construct Sodexo integration.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'id'          => 'sodexo',
				'name'        => 'Sodexo',
				'product_url' => 'http://go.thearrangers.xyz/sodexo?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=product-url',
				'provider'    => 'sodexo',
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

		// API Keys.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_sodexo_api_keys',
			'title'    => __( 'API Keys', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( 'apiKey will be shared by Zeta with requester during the on-boarding process.', 'knit-pay-lang' ),
		];

		// Acquirer ID.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_sodexo_aid',
			'title'    => __( 'Acquirer ID', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( 'Information of the merchant (payee) for which payment is requested. (aid: acquirer ID given by Sodexo)', 'knit-pay-lang' ),
		];

		// Merchant ID.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_sodexo_mid',
			'title'    => __( 'Merchant ID', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( 'Information of the merchant (payee) for which payment is requested. (mid:  merchant ID given by Sodexo)', 'knit-pay-lang' ),
		];

		// Terminal ID.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_sodexo_tid',
			'title'    => __( 'Terminal ID', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( 'Information of the merchant (payee) for which payment is requested. (tid: terminal ID given by Sodexo)', 'knit-pay-lang' ),
		];

		// Return fields.
		return $fields;
	}

	public function get_config( $post_id ) {
		$config = new Config();

		$config->api_keys = $this->get_meta( $post_id, 'sodexo_api_keys' );
		$config->aid      = $this->get_meta( $post_id, 'sodexo_aid' );
		$config->mid      = $this->get_meta( $post_id, 'sodexo_mid' );
		$config->tid      = $this->get_meta( $post_id, 'sodexo_tid' );
		$config->mode     = $this->get_meta( $post_id, 'mode' );

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
