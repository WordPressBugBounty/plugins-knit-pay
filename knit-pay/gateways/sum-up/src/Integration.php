<?php

namespace KnitPay\Gateways\SumUp;

use Pronamic\WordPress\Pay\AbstractGatewayIntegration;

/**
 * Title: SumUp Integration
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.91.0.0
 * @since   8.91.0.0
 */
class Integration extends AbstractGatewayIntegration {
	/**
	 * Construct SumUp integration.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'id'          => 'sumup',
				'name'        => 'SumUp',
				'provider'    => 'sumup',
				'url'         => 'https://sumup.com/',
				'product_url' => 'https://sumup.com/',
			]
		);

		parent::__construct( $args );
	}

	/**
	 * Setup.
	 */
	public function setup() {
		// Display ID on payment screen.
		\add_filter(
			'pronamic_payment_source_text_' . $this->get_id(),
			[ $this, 'source_text' ],
			10,
			2
		);
	}

	/**
	 * Get settings fields.
	 *
	 * @return array
	 */
	public function get_settings_fields() {
		$fields = [];

		// Login Email
		$fields[] = [
			'section'  => 'general',
			'filter'   => FILTER_SANITIZE_EMAIL,
			'meta_key' => '_pronamic_gateway_sumup_login_email',
			'title'    => __( 'Login Email', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( 'The email address used to login to your SumUp account.', 'knit-pay-lang' ),
		];

		// Client ID.
		$fields[] = [
			'section'  => 'general',
			'filter'   => FILTER_SANITIZE_STRING,
			'meta_key' => '_pronamic_gateway_sumup_client_id',
			'title'    => __( 'Client ID', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
		];

		// Client Secret.
		$fields[] = [
			'section'  => 'general',
			'filter'   => FILTER_SANITIZE_STRING,
			'meta_key' => '_pronamic_gateway_sumup_client_secret',
			'title'    => __( 'Client Secret', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
		];

		return $fields;
	}

	public function get_config( $post_id ) {
		$config = new Config();

		$config->login_email   = $this->get_meta( $post_id, 'sumup_login_email' );
		$config->client_id     = $this->get_meta( $post_id, 'sumup_client_id' );
		$config->client_secret = $this->get_meta( $post_id, 'sumup_client_secret' );

		return $config;
	}

	/**
	 * Get gateway.
	 *
	 * @param int $post_id Post ID.
	 * @return Gateway
	 */
	public function get_gateway( $post_id ) {
		return new Gateway( $this->get_config( $post_id ) );
	}
} 
