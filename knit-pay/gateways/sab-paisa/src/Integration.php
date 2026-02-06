<?php

namespace KnitPay\Gateways\SabPaisa;

use Pronamic\WordPress\Pay\AbstractGatewayIntegration;
use Pronamic\WordPress\Pay\Core\IntegrationModeTrait;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Plugin;
use KnitPay\Utils as KnitPayUtils;

/**
 * Title: Sab Paisa Integration
 * Copyright: 2020-2026 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.95.0.0
 * @since   8.95.0.0
 */
class Integration extends AbstractGatewayIntegration {
	use IntegrationModeTrait;
	
	/**
	 * Construct Sab Paisa integration.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'id'       => 'sab-paisa',
				'name'     => 'Sab Paisa',
				'provider' => 'sab-paisa',
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

		return $config->client_code;
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

		// Client Code
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_sabpaisa_client_code',
			'title'    => __( 'Client Code', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( 'Your Sab Paisa Client Code', 'knit-pay-lang' ),
			'required' => true,
		];

		// Username
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_sabpaisa_username',
			'title'    => __( 'Username', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( 'Your Sab Paisa Username', 'knit-pay-lang' ),
			'required' => true,
		];

		// Password
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_sabpaisa_password',
			'title'    => __( 'Password', 'knit-pay-lang' ),
			'type'     => 'password',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( 'Your Sab Paisa Password', 'knit-pay-lang' ),
			'required' => true,
		];

		// Authentication Key
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_sabpaisa_auth_key',
			'title'    => __( 'Authentication Key', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( 'Your Sab Paisa Authentication Key', 'knit-pay-lang' ),
			'required' => true,
		];

		// Authentication IV
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_sabpaisa_auth_iv',
			'title'    => __( 'Authentication IV', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( 'Your Sab Paisa Authentication IV', 'knit-pay-lang' ),
			'required' => true,
		];

		// Return fields.
		return $fields;
	}

	/**
	 * Get config.
	 *
	 * @param int $post_id Post ID.
	 * @return Config
	 */
	public function get_config( $post_id ) {
		$config = new Config();

		$config->client_code = $this->get_meta( $post_id, 'sabpaisa_client_code' );
		$config->username    = $this->get_meta( $post_id, 'sabpaisa_username' );
		$config->password    = $this->get_meta( $post_id, 'sabpaisa_password' );
		$config->auth_key    = $this->get_meta( $post_id, 'sabpaisa_auth_key' );
		$config->auth_iv     = $this->get_meta( $post_id, 'sabpaisa_auth_iv' );
		$config->mode        = $this->get_meta( $post_id, 'mode' );

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

	public static function handle_returns() {
		if ( ! filter_has_var( INPUT_GET, 'kp_sab_paisa_payment_id' ) ) {
			return;
		}

		$payment_id = filter_input( INPUT_GET, 'kp_sab_paisa_payment_id', FILTER_SANITIZE_NUMBER_INT );

		$payment = get_pronamic_payment( $payment_id );

		if ( null === $payment ) {
			return;
		}

		wp_safe_redirect( $payment->get_return_url() );
		exit;
	}
}
