<?php

namespace KnitPay\Gateways\Pesapal;

use Pronamic\WordPress\Pay\Core\IntegrationModeTrait;
use Pronamic\WordPress\Pay\Payments\Payment;
use KnitPay\Gateways\Integration as KnitPayGatewayIntegration;

/**
 * Title: Pesapal Integration
 * Copyright: 2020-2025 Knit Pay
 *
 * @author  Knit Pay
 * @version 9.2.0.0
 * @since   9.2.0.0
 */
class Integration extends KnitPayGatewayIntegration {
	use IntegrationModeTrait;
	
	/**
	 * Construct the integration
	 *
	 * @param array $args Arguments for integration
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'id'          => 'pesapal',
				'name'        => 'Pesapal',
				'provider'    => 'pesapal',
				'product_url' => 'https://www.pesapal.com',
				'manual_url'  => 'https://developer.pesapal.com/',
				'supports'    => [
					'webhook',
				],
			]
		);

		parent::__construct( $args );
		
		// Webhook listener setup
		add_action( 'wp_loaded', [ $this, 'webhook_listener' ] );
	}

	/**
	 * Setup the integration
	 */
	public function setup() {
		// Display gateway identifier on configuration page
		\add_filter(
			'pronamic_gateway_configuration_display_value_' . $this->get_id(),
			[ $this, 'gateway_configuration_display_value' ],
			10,
			2
		);
	}

	/**
	 * Webhook listener
	 */
	public function webhook_listener() {
		if ( ! filter_has_var( INPUT_GET, 'knit_pay_webhook' ) ) {
			return;
		}

		$gateway_id = \sanitize_text_field( \wp_unslash( $_REQUEST['knit_pay_webhook'] ) );
		
		if ( $this->get_id() !== $gateway_id ) {
			return;
		}
		
		$listener = new Listener( $this );
		$listener->listen();
		
		exit;
	}

	/**
	 * Get the value to display on the gateway configuration page
	 *
	 * @param string $display_value Current display value
	 * @param int    $post_id       Gateway configuration post ID
	 * @return string Display value for the configuration
	 */
	public function gateway_configuration_display_value( $display_value, $post_id ) {
		$config = $this->get_config( $post_id );
		
		// Return consumer key (partially masked for security)
		if ( ! empty( $config->consumer_key ) ) {
			return '...' . substr( $config->consumer_key, -8 );
		}
		
		return $display_value;
	}

	/**
	 * Get settings fields for the gateway configuration
	 *
	 * @return array Array of settings field definitions
	 */
	public function get_settings_fields() {
		$fields = [];

		// Get mode from Integration mode trait.
		$fields[] = $this->get_mode_settings_fields();
		
		// Consumer Key
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_pesapal_consumer_key',
			'title'    => __( 'Consumer Key', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'required' => true,
			'tooltip'  => __( 'Your Pesapal Consumer Key from the API settings', 'knit-pay-lang' ),
		];
		
		// Consumer Secret
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_pesapal_consumer_secret',
			'title'    => __( 'Consumer Secret', 'knit-pay-lang' ),
			'type'     => 'password',
			'classes'  => [ 'regular-text', 'code' ],
			'required' => true,
			'tooltip'  => __( 'Your Pesapal Consumer Secret from the API settings', 'knit-pay-lang' ),
		];

		// Webhook URL (read-only, for user to copy)
		$fields[] = [
			'section'  => 'feedback',
			'title'    => __( 'Webhook URL / IPN URL', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'large-text', 'readonly' ],
			'value'    => add_query_arg( 'knit_pay_webhook', $this->get_id(), home_url( '/' ) ),
			'readonly' => true,
			'callback' => function () {
				echo '<div class="callout warning">
					<p>You can use Pesapal\'s online forms below to register your IPN URLs.</p>
					<ul>
					<li><a href="https://cybqa.pesapal.com/PesapalIframe/PesapalIframe3/IpnRegistration" target="_blank"> - Sandbox/Demo IPN Registration Form</a></li>
					<li><a href="https://pay.pesapal.com/iframe/PesapalIframe3/IpnRegistration" target="_blank"> - Production/Live IPN Registration Form</a></li>
					</ul>
				</div>';
			},
		];
		
		// IPN ID
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_pesapal_ipn_id',
			'title'    => __( 'IPN ID', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'required' => true,
			'tooltip'  => __( 'IPN ID obtained after registering your IPN URL in Pesapal dashboard', 'knit-pay-lang' ),
		];

		return $fields;
	}

	/**
	 * Get gateway configuration from post meta
	 * 
	 * @param int $post_id Configuration post ID
	 * @return Config Configuration object
	 */
	public function get_config( $post_id ) {
		$config = new Config();

		// Get the mode (test/live)
		$config->mode = $this->get_meta( $post_id, 'mode' );

		// Load configuration fields
		$config->consumer_key    = $this->get_meta( $post_id, 'pesapal_consumer_key' );
		$config->consumer_secret = $this->get_meta( $post_id, 'pesapal_consumer_secret' );
		$config->ipn_id          = $this->get_meta( $post_id, 'pesapal_ipn_id' );

		return $config;
	}

	/**
	 * Get the gateway instance
	 *
	 * @param int $config_id Configuration post ID
	 * @return Gateway Gateway instance
	 */
	public function get_gateway( $config_id ) {
		// Load configuration
		$config = $this->get_config( $config_id );
		
		// Create gateway instance
		$gateway = new Gateway();
		
		// Determine the mode
		$mode = Gateway::MODE_LIVE;
		if ( Gateway::MODE_TEST === $config->mode ) {
			$mode = Gateway::MODE_TEST;
		}
		
		// Set mode on both integration and gateway
		$this->set_mode( $mode );
		$gateway->set_mode( $mode );
		
		// Initialize gateway with configuration
		$gateway->init( $config );
		
		return $gateway;
	}
}
