<?php

namespace KnitPay\Gateways\HdfcSmartGateway;

use Pronamic\WordPress\Pay\AbstractGatewayIntegration;
use Pronamic\WordPress\Pay\Core\IntegrationModeTrait;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Plugin;

/**
 * Title: HDFC Smart Gateway Integration
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.93.0.0
 * @since   8.93.0.0
 */
class Integration extends AbstractGatewayIntegration {
	use IntegrationModeTrait;

	/**
	 * Construct HDFC Smart Gateway integration.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'id'       => 'hdfc-smart-gateway',
				'name'     => 'HDFC Smart Gateway',
				'provider' => 'hdfc-smart-gateway',
			]
		);

		parent::__construct( $args );
	}

		/**
		 * Setup.
		 */
	public function setup() {
		// handle_returns.
		$function = [ __NAMESPACE__ . '\Integration', 'handle_returns' ];
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

		// API Key
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_hdfc_smart_gateway_api_key',
			'title'    => __( 'API Key', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
		];

		// Merchant ID
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_hdfc_smart_gateway_merchant_id',
			'title'    => __( 'Merchant ID', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
		];

		// Payment Page Client ID
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_hdfc_smart_gateway_client_id',
			'title'    => __( 'Payment Page Client ID', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
		];

		// Return fields.
		return $fields;
	}

	public function get_config( $post_id ) {
		$config = new Config();

		$config->api_key     = $this->get_meta( $post_id, 'hdfc_smart_gateway_api_key' );
		$config->merchant_id = $this->get_meta( $post_id, 'hdfc_smart_gateway_merchant_id' );
		$config->client_id   = $this->get_meta( $post_id, 'hdfc_smart_gateway_client_id' );

		$config->mode = $this->get_meta( $post_id, 'mode' );

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

	public static function handle_returns() {
		if ( ! ( filter_has_var( INPUT_POST, 'order_id' ) ) ) {
			return;
		}

		if ( 'kp-hdfc-smart-gateway-return' !== end( explode( '/', $_SERVER['REQUEST_URI'] ) ) ) {
			return;
		}

		$transaction_id = \sanitize_text_field( \wp_unslash( $_POST['order_id'] ) );

		$payment = get_pronamic_payment_by_transaction_id( $transaction_id );

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
