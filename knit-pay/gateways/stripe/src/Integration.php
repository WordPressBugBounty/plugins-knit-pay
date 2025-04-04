<?php

namespace KnitPay\Gateways\Stripe;

use Pronamic\WordPress\Pay\AbstractGatewayIntegration;
use Pronamic\WordPress\Pay\Util;
use Pronamic\WordPress\Pay\Core\IntegrationModeTrait;

/**
 * Title: Stripe Integration
 * Copyright: 2020-2025 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.96.11.0
 * @since   3.1.0
 */
class Integration extends AbstractGatewayIntegration {
	use IntegrationModeTrait;
	/**
	 * Construct Stripe integration.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'id'          => 'stripe',
				'name'        => 'Stripe',
				'product_url' => 'http://go.thearrangers.xyz/stripe?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=product-url',
				'provider'    => 'stripe',
			]
		);

		parent::__construct( $args );

		// Actions.
		$function = [ __NAMESPACE__ . '\Webhook', 'listen' ];

		if ( ! has_action( 'wp_loaded', $function ) ) {
			add_action( 'wp_loaded', $function );
		}
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

		// Publishable Key.
		$fields['stripe_publishable_key'] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_stripe_publishable_key',
			'title'    => __( 'Publishable Key', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( 'This is the identifier for your Stripe merchant Account.', 'knit-pay-lang' ),
		];

		// Secret Key.
		$fields['stripe_secret_key'] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_stripe_secret_key',
			'title'    => __( 'Secret Key', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( 'This is the access code for your application.', 'knit-pay-lang' ),
		];

		// Test Publishable Key.
		$fields['stripe_test_publishable_key'] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_stripe_test_publishable_key',
			'title'    => __( 'Publishable Key (Test)', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( 'This is the identifier for your Stripe merchant Account.', 'knit-pay-lang' ),
		];

		// Test Secret Key.
		$fields['stripe_test_secret_key'] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_stripe_test_secret_key',
			'title'    => __( 'Secret Key (Test)', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( 'This is the access code for your application.', 'knit-pay-lang' ),
		];

		// Enabled Payment Methods.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => 'stripe_enabled_payment_methods',
			'title'    => __( 'Enabled Payment Methods', 'knit-pay-lang' ),
			'type'     => 'description',
			'callback' => [ $this, 'field_enabled_payment_methods' ],
		];

		// Payment Currency.
		$fields[] = [
			'section'  => 'advanced',
			'meta_key' => '_pronamic_gateway_stripe_payment_currency',
			'title'    => __( 'Payment Currency', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( '3 Character Currency Code. (eg. USD) ', 'knit-pay-lang' ),
		];

		// Exchange Rate.
		$fields[] = [
			'section'  => 'advanced',
			'filter'   => FILTER_VALIDATE_FLOAT,
			'meta_key' => '_pronamic_gateway_stripe_exchange_rate',
			'title'    => __( 'Exchange Rate', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( 'Exchange Rate of Payment Currency.', 'knit-pay-lang' ),
			'default'  => 1,
		];


		// Auto Webhook Setup Supported.
		$fields[] = [
			'section'     => 'feedback',
			'title'       => __( 'Auto Webhook Setup Supported', 'knit-pay-lang' ),
			'type'        => 'description',
			'description' => 'Knit Pay automatically creates webhook configuration in Stripe Dashboard as soon as Stripe configuration is published or saved. Kindly raise the Knit Pay support ticket or configure the webhook manually if the automatic webhook setup fails.',
		];

		// Webhook URL.
		$fields[] = [
			'section'  => 'feedback',
			'title'    => \__( 'Webhook URL', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'large-text', 'code' ],
			'value'    => add_query_arg( 'kp_stripe_webhook', '', home_url( '/' ) ),
			'readonly' => true,
			'tooltip'  => sprintf(
				/* translators: %s: Stripe */
				__(
					'Copy the Webhook URL to the %s dashboard to receive automatic transaction status updates.',
					'knit-pay'
				),
				__( 'Stripe', 'knit-pay-lang' )
			),
		];

		$fields[] = [
			'section'  => 'feedback',
			'meta_key' => '_pronamic_gateway_stripe_webhook_secret',
			'title'    => \__( 'Webhook Secret', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  =>
			__(
				'Create a new webhook secret. This can be a random string, and you don\'t have to remember it. Do not use your password or Key Secret here.',
				'knit-pay'
			),
		];

		// Return fields.
		return $fields;
	}

	/**
	 * Get configuration by post ID.
	 *
	 * @param int $post_id Post ID.
	 * @return Config
	 */
	public function get_config( $post_id ) {
		$config = new Config();

		$config->publishable_key      = $this->get_meta( $post_id, 'stripe_publishable_key' );
		$config->secret_key           = $this->get_meta( $post_id, 'stripe_secret_key' );
		$config->test_publishable_key = $this->get_meta( $post_id, 'stripe_test_publishable_key' );
		$config->test_secret_key      = $this->get_meta( $post_id, 'stripe_test_secret_key' );
		$config->payment_currency     = $this->get_meta( $post_id, 'stripe_payment_currency' );
		$config->exchange_rate        = $this->get_meta( $post_id, 'stripe_exchange_rate' );
		$config->mode                 = $this->get_meta( $post_id, 'mode' );

		$config->enabled_payment_methods = $this->get_meta( $post_id, 'stripe_enabled_payment_methods' );
		if ( empty( $config->enabled_payment_methods ) ) {
			$config->enabled_payment_methods = [ PaymentMethods::CREDIT_CARD ];
		}

		return $config;
	}

	/**
	 * Get gateway.
	 *
	 * @param int $config_id Post ID.
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

	/**
	 * Field Enabled Payment Methods.
	 *
	 * @param array<string, mixed> $field Field.
	 * @return void
	 */
	public function field_enabled_payment_methods( $field ) {
		$config_id = (int) \get_the_ID();
		$config    = self::get_config( $config_id );
		$gateway   = self::get_gateway( $config_id );

		// Get Supported Payment Methods.
		$supported_payment_methods_ids = $gateway->get_payment_methods()->getIterator();
		unset( $supported_payment_methods_ids['stripe'] );
		$supported_payment_methods = [];
		foreach ( $supported_payment_methods_ids as $payment_method ) {
			$supported_payment_methods[ $payment_method->get_id() ] = $payment_method->get_name();
		}

		$attributes['type'] = 'checkbox';
		$attributes['id']   = '_pronamic_gateway_stripe_enabled_payment_methods';
		$attributes['name'] = $attributes['id'] . '[]';

		foreach ( $supported_payment_methods as $value => $label ) {
			$attributes['value'] = $value;

			printf(
				'<input %s %s />',
    	        // @codingStandardsIgnoreStart
    	        Util::array_to_html_attributes( $attributes ),
    	        // @codingStandardsIgnoreEnd
				in_array( $value, $config->enabled_payment_methods ) ? 'checked ' : ''
			);

			printf( ' ' );

			if ( PaymentMethods::get_name( PaymentMethods::CREDIT_CARD ) === $label ) {
				$label = 'Cards';
			}
			printf(
				'<label for="%s">%s</label><br>',
				esc_attr( $attributes['id'] ),
				esc_html( $label )
			);
		}
		printf( '<br>Please ensure the selected payment method is activated in your <a target="_blank" href="https://dashboard.stripe.com/account/payments/settings">dashboard</a>.' );
	}

	/**
	 * Save post.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save_post( $post_id ) {
		$post = filter_input_array( INPUT_POST );
		if ( empty( $post['_pronamic_gateway_stripe_enabled_payment_methods'] ) ) {
			$post['_pronamic_gateway_stripe_enabled_payment_methods'] = [ PaymentMethods::CREDIT_CARD ];
		}
		update_post_meta( $post_id, '_pronamic_gateway_stripe_enabled_payment_methods', $post['_pronamic_gateway_stripe_enabled_payment_methods'] );
	}
}
