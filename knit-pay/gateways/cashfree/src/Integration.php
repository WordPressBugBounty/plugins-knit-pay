<?php

namespace KnitPay\Gateways\Cashfree;

use Pronamic\WordPress\Pay\AbstractGatewayIntegration;
use Pronamic\WordPress\Pay\Core\IntegrationModeTrait;
use Pronamic\WordPress\Pay\Payments\Payment;

/**
 * Title: Cashfree Integration
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 1.0.0
 * @since   2.4
 */
class Integration extends AbstractGatewayIntegration {
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
				'name'          => 'Cashfree',
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
		
		return $config->api_id;
	}

	/**
	 * Get settings fields.
	 *
	 * @return array
	 */
	public function get_settings_fields() {
		$fields = [];

		$fields[] = [
			'section'     => 'general',
			'type'        => 'custom',
			'title'       => 'Sign Up',
			'description' => sprintf(
				/* translators: 1: Cashfree */
				__( 'Before proceeding, kindly create an account at %1$s if you don\'t have one already.%2$s', 'knit-pay-lang' ),
				__( 'Cashfree', 'knit-pay-lang' ),
				'<br><a class="button button-primary" target="_blank" href="http://go.thearrangers.xyz/cashfree?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=help-signup"
                     role="button"><strong>Sign Up Now</strong></a>'
			),
		];
		
		// Get mode from Integration mode trait.
		$fields[] = $this->get_mode_settings_fields();

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

		// Default Customer Phone.
		$fields[] = [
			'section'  => 'advanced',
			'meta_key' => '_pronamic_gateway_cashfree_default_customer_phone',
			'title'    => __( 'Default Customer Phone', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( 'Knit Pay will pass this phone number to Cashfree if the customer\'s phone number is not available.', 'knit-pay-lang' ),
		];

		// TODO Implement Webhook URL, cashfree API v4, requirement webhook URL implementation.

		// Return fields.
		return $fields;
	}

	public function get_config( $post_id ) {
		$config = new Config();

		$config->api_id                 = $this->get_meta( $post_id, 'cashfree_api_id' );
		$config->secret_key             = $this->get_meta( $post_id, 'cashfree_secret_key' );
		$config->default_customer_phone = $this->get_meta( $post_id, 'cashfree_default_customer_phone' );
		$config->mode                   = $this->get_meta( $post_id, 'mode' );

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
