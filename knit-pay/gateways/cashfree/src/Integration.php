<?php

namespace KnitPay\Gateways\Cashfree;

use Pronamic\WordPress\Pay\Core\IntegrationModeTrait;
use Pronamic\WordPress\Pay\Payments\Payment;
use KnitPay\Gateways\IntegrationOAuthClient;

/**
 * Title: Cashfree Integration
 * Copyright: 2020-2025 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.91.0.0
 * @since   2.4
 */
class Integration extends IntegrationOAuthClient {
	use IntegrationModeTrait;
	
	/**
	 * Construct Cashfree integration.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'id'            => 'cashfree',
				'name'          => 'Cashfree - Easy Connect',
				'gateway_name'  => 'Cashfree', // Set this if display name and actual name are different.
				'url'           => 'http://go.thearrangers.xyz/cashfree?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=',
				'product_url'   => 'http://go.thearrangers.xyz/cashfree?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=product-url',
				'dashboard_url' => 'http://go.thearrangers.xyz/cashfree?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=dashboard-url',
				'provider'      => 'cashfree',
				'supports'      => [
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
	
	public function allowed_redirect_hosts( $hosts ) {
		$hosts[] = 'auth.cashfree.com';
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

		return empty( $config->merchant_id ) ? $config->api_id : $config->merchant_id;
	}

	protected function get_basic_auth_fields( $fields ) {
		// Client ID.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_cashfree_api_id',
			'title'    => __( 'API ID', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( 'API ID as mentioned in the Cashfree dashboard at the "Credentials" page.', 'knit-pay-lang' ),
			'required' => true,
		];

		// Client Secret.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_cashfree_secret_key',
			'title'    => __( 'Secret Key', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( 'Secret Key as mentioned in the Cashfree dashboard at the "Credentials" page.', 'knit-pay-lang' ),
			'required' => true,
		];

		// Return fields.
		return $fields;
	}

	protected function show_common_setting_fields( $fields ) {
		// Default Customer Phone.
		$fields[] = [
			'section'  => 'advanced',
			'meta_key' => '_pronamic_gateway_cashfree_default_customer_phone',
			'title'    => __( 'Default Customer Phone', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( 'Knit Pay will pass this phone number to Cashfree if the customer\'s phone number is unavailable. If not entered, Knit Pay - Cashfree will use 9999999999 as a default phone number.', 'knit-pay-lang' ),
		];

		// Return fields.
		return $fields;
	}

	public function get_child_config( $post_id ) {
		$config = new Config();

		// API.
		$config->api_id     = $this->get_meta( $post_id, 'cashfree_api_id' );
		$config->secret_key = $this->get_meta( $post_id, 'cashfree_secret_key' );

		// OAuth.
		$config->merchant_id           = $this->get_meta( $post_id, 'cashfree_merchant_id' );
		$config->is_connected          = $this->get_meta( $post_id, 'cashfree_is_connected' );
		$config->connected_at          = $this->get_meta( $post_id, 'cashfree_connected_at' );
		$config->expires_at            = $this->get_meta( $post_id, 'cashfree_expires_at' );
		$config->access_token          = $this->get_meta( $post_id, 'cashfree_access_token' );
		$config->refresh_token         = $this->get_meta( $post_id, 'cashfree_refresh_token' );
		$config->connection_fail_count = $this->get_meta( $post_id, 'cashfree_connection_fail_count' );

		$config->default_customer_phone = $this->get_meta( $post_id, 'cashfree_default_customer_phone' );
		$config->mode                   = $this->get_meta( $post_id, 'mode' );

		// Currently Cashfree Oauth does not support test mode.
		if ( empty( $config->mode ) ) {
			$config->mode = Gateway::MODE_LIVE;
		}

		if ( empty( $config->connection_fail_count ) ) {
			$config->connection_fail_count = 0;
		}

		if ( ! $this->is_auth_basic_enabled( $config ) && $this->is_auth_basic_connected( $config ) && defined( 'KNIT_PAY_PRO' ) ) {
			update_post_meta( $post_id, '_pronamic_gateway_id', 'cashfree-pro' );
			$this->set_id( 'cashfree-pro' );
			$config = $this->get_child_config( $post_id );
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
		if ( Gateway::MODE_TEST === $config->mode ) {
			$mode = Gateway::MODE_TEST;
		}

		$this->set_mode( $mode );
		$gateway->set_mode( $mode );
		$gateway->init( $config );

		return $gateway;
	}

	public function clear_child_config( $config_id ) {
		delete_post_meta( $config_id, '_pronamic_gateway_' . $this->get_id() . '_is_connected' );
		delete_post_meta( $config_id, '_pronamic_gateway_' . $this->get_id() . '_expires_at' );
		delete_post_meta( $config_id, '_pronamic_gateway_' . $this->get_id() . '_connected_at' );
		delete_post_meta( $config_id, '_pronamic_gateway_' . $this->get_id() . '_access_token' );
		delete_post_meta( $config_id, '_pronamic_gateway_' . $this->get_id() . '_refresh_token' );
		delete_post_meta( $config_id, '_pronamic_gateway_' . $this->get_id() . '_merchant_id' );
		delete_post_meta( $config_id, '_pronamic_gateway_' . $this->get_id() . '_connection_fail_count' );

		delete_post_meta( $config_id, '_pronamic_gateway_' . $this->get_id() . '_api_id' );
		delete_post_meta( $config_id, '_pronamic_gateway_' . $this->get_id() . '_secret_key' );
	}

	protected function is_auth_basic_enabled( $config ) {
		return defined( 'KNIT_PAY_CASHFREE' ) || 'cashfree-pro' === $this->get_id();
	}

	private function is_auth_basic_connected( $config ) {
		return ! empty( $config->secret_key );
	}

	protected function refresh_failed_action( $result, $config, $config_id ) {
		if ( parent::refresh_failed_action( $result, $config, $config_id ) ) {
			// Clear config if access is revoked.
			if ( isset( $result->data->code ) && ( 'invalid_grant' === $result->data->code ) ) {
					self::clear_config( $config_id );
			}
			return true;
		}

		return false;
	}
}
