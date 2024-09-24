<?php

namespace KnitPay\Gateways\Fiserv;

use Pronamic\WordPress\Pay\AbstractGatewayIntegration;
use Pronamic\WordPress\Pay\Core\IntegrationModeTrait;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Plugin;

/**
 * Title: Fiserv Integration
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 6.64.0.0
 * @since   6.64.0.0
 */
class Integration extends AbstractGatewayIntegration {
	use IntegrationModeTrait;
	/**
	 * Construct Fiserv integration.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'id'          => 'fiserv',
				'name'        => 'Fiserv',
				'url'         => 'http://go.thearrangers.xyz/fiserv?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=',
				'product_url' => 'http://go.thearrangers.xyz/fiserv?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=product-url',
				'provider'    => 'fiserv',
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

		return $config->storename;
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
		
		// Store Name.
		$fields[] = [
			'section'     => 'general',
			'filter'      => FILTER_SANITIZE_NUMBER_INT,
			'meta_key'    => '_pronamic_gateway_fiserv_storename',
			'title'       => __( 'Store Name', 'knit-pay-lang' ),
			'type'        => 'text',
			'classes'     => [
				'regular-text',
				'code',
			],
			'description' => 'This is the ID of the store provided by Fiserv.',
		];
		
		// Shared Secret.
		$fields[] = [
			'section'     => 'general',
			'filter'      => FILTER_UNSAFE_RAW,
			'meta_key'    => '_pronamic_gateway_fiserv_sharedsecret',
			'title'       => __( 'Shared Secret', 'knit-pay-lang' ),
			'type'        => 'text',
			'classes'     => [
				'regular-text',
				'code',
			],
			'description' => 'This is a unique identifier provided by the payment gateway, which will be provided by the payment gateway support team.',
		];
		
		// Return fields.
		return $fields;
	}

	public function get_config( $post_id ) {
		$config = new Config();

		$config->storename    = $this->get_meta( $post_id, 'fiserv_storename' );
		$config->sharedsecret = $this->get_meta( $post_id, 'fiserv_sharedsecret' );
		$config->mode         = $this->get_meta( $post_id, 'mode' );

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
