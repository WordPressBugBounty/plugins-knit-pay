<?php

namespace KnitPay\Gateways\Slydepay;

use Pronamic\WordPress\Pay\AbstractGatewayIntegration;
use Pronamic\WordPress\Pay\Core\IntegrationModeTrait;

/**
 * Title: Slydepay Integration
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 6.67.0.0
 * @since   6.67.0.0
 */
class Integration extends AbstractGatewayIntegration {
	use IntegrationModeTrait;
	
	/**
	 * Construct Slydepay integration.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'id'            => 'slydepay',
				'name'          => 'Slydepay',
				'url'           => 'http://go.thearrangers.xyz/slydepay?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=',
				'product_url'   => 'http://go.thearrangers.xyz/slydepay?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=product-url',
				'dashboard_url' => 'http://go.thearrangers.xyz/slydepay?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=dashboard-url',
				'provider'      => 'slydepay',
			]
		);

		parent::__construct( $args );
	}
	
	/**
	 * Setup gateway integration.
	 *
	 * @return void
	 */
	public function setup() {
		// Display ID on Configurations page.
		\add_filter(
			'pronamic_gateway_configuration_display_value_' . $this->get_id(),
			[ $this, 'gateway_configuration_display_value' ],
			10,
			2
		);
		
		// Payment Redirect Listener.
		$function = [ __NAMESPACE__ . '\Integration', 'payment_redirect_listener' ];
		if ( ! has_action( 'wp_loaded', $function ) ) {
			add_action( 'wp_loaded', $function );
		}
	}
	
	public static function payment_redirect_listener() {
		if ( ! ( filter_has_var( INPUT_GET, 'kp_slydepay_redirect' ) || filter_has_var( INPUT_GET, 'cust_ref' ) ) ) {
			return;
		}
		
		$transaction_id = filter_input( INPUT_GET, 'cust_ref', FILTER_SANITIZE_STRING );
		
		$payment = get_pronamic_payment_by_transaction_id( $transaction_id );
		
		if ( ! isset( $payment ) ) {
			return;
		}
		
		// Redirect to Return URL.
		wp_safe_redirect( $payment->get_return_url() );
		exit;
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
		
		return $config->merchant_email;
	}

	/**
	 * Get settings fields.
	 *
	 * @return array
	 */
	public function get_settings_fields() {
		$fields = [];

		// Merchant Email.
		$fields[] = [
			'section'  => 'general',
			'filter'   => FILTER_SANITIZE_EMAIL,
			'meta_key' => '_pronamic_gateway_slydepay_merchant_email',
			'title'    => __( 'Merchant Email', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( 'The email address for your Slydepay business account.', 'knit-pay-lang' ),
		];

		// API Key.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_slydepay_api_key',
			'title'    => __( 'API Key', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( 'API Key is generated from the settings page in your Slydepay business account.', 'knit-pay-lang' ),
		];
		
		// Callback URL.
		$fields[] = [
			'section'  => 'general',
			'title'    => \__( 'Callback URL', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'large-text', 'code' ],
			'value'    => add_query_arg( 'kp_slydepay_redirect', '', home_url( '/' ) ),
			'readonly' => true,
			'tooltip'  => sprintf(
				/* translators: %s: PayUmoney */
				__(
					'Copy the Redirect URL to the %s dashboard.',
					'knit-pay'
				),
				__( 'Slydepay', 'knit-pay-lang' )
			),
		];

		// Return fields.
		return $fields;
	}

	public function get_config( $post_id ) {
		$config = new Config();

		$config->merchant_email = $this->get_meta( $post_id, 'slydepay_merchant_email' );
		$config->api_key        = $this->get_meta( $post_id, 'slydepay_api_key' );

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

		$gateway->init( $config );
		
		return $gateway;
	}
}
