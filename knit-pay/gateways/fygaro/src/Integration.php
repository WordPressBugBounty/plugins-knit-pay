<?php

namespace KnitPay\Gateways\Fygaro;

use Pronamic\WordPress\Pay\AbstractGatewayIntegration;

/**
 * Title: Fygaro Integration
 * Copyright: 2020-2021 Knit Pay
 *
 * @author  Knit Pay
 * @version 5.0.0
 * @since   5.0.0
 */
class Integration extends AbstractGatewayIntegration {
	/**
	 * Construct Fygaro integration.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'id'          => 'fygaro',
				'name'        => 'Fygaro',
				'product_url' => 'https://fygaro.com/en/app/dashboard/',
				'provider'    => 'fygaro',
			]
		);

		parent::__construct( $args );
		
		// Actions.
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

		// Payment Return Listener.
		$function = [ __NAMESPACE__ . '\Integration', 'payment_return_listener' ];
		if ( ! has_action( 'wp_loaded', $function ) ) {
			add_action( 'wp_loaded', $function );
		}
	}

	public static function payment_return_listener() {
		if ( ! ( filter_has_var( INPUT_GET, 'kp_fygaro_return' ) ) ) {
			return;
		}

		if ( empty( $_COOKIE['kp_fygaro_transaction_id'] ) ) {
			return;
		}

		// Wait for 5 seconds so that webhook can update the status.
		sleep( 3 );

		$payment = get_pronamic_payment_by_transaction_id( $_COOKIE['kp_fygaro_transaction_id'] );

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

		return $config->api_key;
	}

	/**
	 * Get settings fields.
	 *
	 * @return array
	 */
	public function get_settings_fields() {
		$fields = [];

		// API Key.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_fygaro_api_key',
			'title'    => __( 'API Key', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
		];
		
		// API Secret.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_fygaro_api_secret',
			'title'    => __( 'API Secret', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
		];
		
		// Payment Button URL.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_fygaro_payment_button_url',
			'title'    => __( 'Payment Button URL', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
		];
		
		// Return URL.
		$fields[] = [
			'section'  => 'general',
			'title'    => \__( 'Return URL', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'large-text', 'code' ],
			'value'    => add_query_arg( 'kp_fygaro_return', '', home_url( '/' ) ),
			'readonly' => true,
			'tooltip'  => sprintf(
				/* translators: %s: PayUmoney */
				__(
					'Copy the Return URL to the %s dashboard while creating payment button.',
					'knit-pay'
				),
				__( 'Fygaro', 'knit-pay-lang' )
			),
		];

		// Webhook URL.
		$fields[] = [
			'section'  => 'feedback',
			'title'    => \__( 'Webhook URL', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'large-text', 'code' ],
			'value'    => add_query_arg( 'kp_fygaro_webhook', '', home_url( '/' ) ),
			'readonly' => true,
			'tooltip'  => sprintf(
				/* translators: %s: Fygaro */
				__(
					'Copy the Webhook URL to the %s dashboard to receive automatic transaction status updates.',
					'knit-pay'
				),
				__( 'Fygaro', 'knit-pay-lang' )
			),
		];

		// Return fields.
		return $fields;
	}

	public function get_config( $post_id ) {
		$config = new Config();

		$config->api_key            = $this->get_meta( $post_id, 'fygaro_api_key' );
		$config->api_secret         = $this->get_meta( $post_id, 'fygaro_api_secret' );
		$config->payment_button_url = $this->get_meta( $post_id, 'fygaro_payment_button_url' );

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
}
