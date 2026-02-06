<?php

namespace KnitPay\Gateways\ZohoPay;

use Pronamic\WordPress\Pay\Core\IntegrationModeTrait;
use Pronamic\WordPress\Pay\Payments\Payment;
use KnitPay\Gateways\IntegrationOAuthClient;

/**
 * Title: Zoho Pay Integration
 * Copyright: 2020-2026 Knit Pay
 *
 * @author  Knit Pay
 * @version 9.0.0.0
 * @since   9.0.0.0
 */
class Integration extends IntegrationOAuthClient {
	use IntegrationModeTrait;
	
	/**
	 * Construct Zoho Pay integration.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'id'           => 'zoho-pay',
				'name'         => 'Zoho Pay',
				'gateway_name' => 'Zoho Pay', // Set this if display name and actual name are different.
				'provider'     => 'zoho-pay',
			]
		);

		parent::__construct( $args );

		$this->supports_test_mode          = false;
		$this->schedule_next_refresh_token = false;
	}
	
	public function allowed_redirect_hosts( $hosts ) {
		$hosts[] = 'accounts.zoho.in';
		return $hosts;
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

		return empty( $config->account_id ) ? $display_value : $config->account_id;
	}

	
	/**
	 * Get settings fields.
	 *
	 * @return array
	 */
	protected function get_oauth_connection_status_fields( $fields ) {
		$fields = parent::get_oauth_connection_status_fields( $fields );

		// Account ID.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_zoho_pay_account_id',
			'title'    => __( 'Account ID', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'required' => true,
		];

		// Return fields.
		return $fields;
	}

	public function get_child_config( $post_id ) {
		$config = new Config();

		// OAuth.
		$config->access_token          = $this->get_meta( $post_id, $this->snake_case_id . '_access_token' );
		$config->refresh_token         = $this->get_meta( $post_id, $this->snake_case_id . '_refresh_token' );
		$config->connection_fail_count = $this->get_meta( $post_id, $this->snake_case_id . '_connection_fail_count' );
		$config->expires_at            = $this->get_meta( $post_id, $this->snake_case_id . '_expires_at' );

		// Account ID.
		$config->account_id = $this->get_meta( $post_id, $this->snake_case_id . '_account_id' );

		if ( empty( $config->connection_fail_count ) ) {
			$config->connection_fail_count = 0;
		}

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

		$this->set_mode( $mode );
		$gateway->set_mode( $mode );
		$gateway->init( $config );

		return $gateway;
	}

	public function clear_child_config( $config_id ) {}
}
