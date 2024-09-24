<?php

namespace KnitPay\Gateways\IciciEazypay;

use Pronamic\WordPress\Pay\AbstractGatewayIntegration;
use Pronamic\WordPress\Pay\Plugin;

/**
 * Title: ICICI Eazypay Integration
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 6.62.0.0
 * @since   6.62.0.0
 */
class Integration extends AbstractGatewayIntegration {
	/**
	 * Construct ICICI Eazypay integration.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'id'            => 'icici-eazypay',
				'name'          => 'ICICI Eazypay',
				'url'           => 'http://go.thearrangers.xyz/icici-eazypay?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=',
				'product_url'   => 'http://go.thearrangers.xyz/icici-eazypay?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=product-url',
				'dashboard_url' => 'http://go.thearrangers.xyz/icici-eazypay?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=dashboard-url',
				'provider'      => 'icici-eazypay',
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

		// handle_returns.
		$function = [ __NAMESPACE__ . '\Integration', 'handle_returns' ];
		if ( ! has_action( 'wp_loaded', $function ) ) {
			add_action( 'wp_loaded', $function );
		}
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

		// Merchant ID/ICID.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_icici_eazypay_merchant_id',
			'title'    => __( 'Merchant ID/ICID', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'required' => true,
		];
		
		// Encryption Key.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_icici_eazypay_encryption_key',
			'title'    => __( 'Encryption Key', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'required' => true,
		];
		
		// Mandatory Fields.
		$fields[] = [
			'section'     => 'general',
			'meta_key'    => '_pronamic_gateway_icici_eazypay_mandatory_fields',
			'title'       => __( 'Mandatory Fields', 'knit-pay-lang' ),
			'type'        => 'text',
			'classes'     => [ 'large-text', 'code' ],
			'description' => $this->fields_description(),
		];
		
		// Optional Fields.
		$fields[] = [
			'section'     => 'general',
			'meta_key'    => '_pronamic_gateway_icici_eazypay_optional_fields',
			'title'       => __( 'Optional Fields', 'knit-pay-lang' ),
			'type'        => 'text',
			'classes'     => [ 'large-text', 'code' ],
			'description' => $this->fields_description(),
		];

		// Static Return URL.
		$fields[] = [
			'section'     => 'general',
			'meta_key'    => '_pronamic_gateway_icici_eazypay_static_return_url',
			'title'       => __( 'Static Return URL', 'knit-pay-lang' ),
			'type'        => 'text',
			'classes'     => [ 'regular-text', 'code' ],
			'description' => 'Keep Blank if dynamic URL is enabled.',
		];

		// Return fields.
		return $fields;
	}

	public function get_config( $post_id ) {
		$config = new Config();

		$config->merchant_id       = $this->get_meta( $post_id, 'icici_eazypay_merchant_id' );
		$config->encryption_key    = $this->get_meta( $post_id, 'icici_eazypay_encryption_key' );
		$config->mandatory_fields  = $this->get_meta( $post_id, 'icici_eazypay_mandatory_fields' );
		$config->optional_fields   = $this->get_meta( $post_id, 'icici_eazypay_optional_fields' );
		$config->static_return_url = $this->get_meta( $post_id, 'icici_eazypay_static_return_url' );

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
	
	private function fields_description() {
		return sprintf( __( 'Available tags: %s', 'knit-pay-lang' ), sprintf( '<code>%s</code> <code>%s</code> <code>%s</code> <code>%s</code> <code>%s</code> <code>%s</code> <code>%s</code> <code>%s</code>', '{reference_no}', '{amount}', '{customer_phone}', '{customer_email}', '{customer_name}', '{purpose}', '{order_id}', '{sub_merchant_id}' ) );
	}

	public static function handle_returns() {
		if ( ! ( filter_has_var( INPUT_POST, 'mandatory_fields' ) && filter_has_var( INPUT_POST, 'Response_Code' ) && ! filter_has_var( INPUT_GET, 'key' ) ) ) {
			return;
		}

		$mandatory_fields = filter_input( INPUT_POST, 'mandatory_fields', FILTER_SANITIZE_STRING );
		$mandatory_fields = explode( '|', $mandatory_fields );

		$payment = get_pronamic_payment_by_transaction_id( $mandatory_fields[0] );

		if ( null === $payment ) {
			return;
		}

		// Check if we should redirect.
		$should_redirect = true;

		/**
		 * Filter whether or not to allow redirects on payment return.
		 *
		 * @param bool    $should_redirect Flag to indicate if redirect is allowed on handling payment return.
		 * @param Payment $payment         Payment.
		 */
		$should_redirect = apply_filters( 'pronamic_pay_return_should_redirect', $should_redirect, $payment );

		try {
			Plugin::update_payment( $payment, $should_redirect );
		} catch ( \Exception $e ) {
			Plugin::render_exception( $e );

			exit;
		}
	}
}
