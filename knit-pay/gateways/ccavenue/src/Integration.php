<?php

namespace KnitPay\Gateways\CCAvenue;

use Pronamic\WordPress\Pay\AbstractGatewayIntegration;
use Pronamic\WordPress\Pay\Core\IntegrationModeTrait;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Plugin;
use KnitPay\Utils as KnitPayUtils;

/**
 * Title: CCAvenue Integration
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 1.0.0
 * @since   2.3.0
 */
class Integration extends AbstractGatewayIntegration {
	use IntegrationModeTrait;
	
	/**
	 * Construct CCAvenue integration.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'id'          => 'ccavenue',
				'name'        => 'CCAvenue',
				'url'         => 'http://go.thearrangers.xyz/ccavenue?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=',
				'product_url' => 'http://go.thearrangers.xyz/ccavenue?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=product-url',
				'provider'    => 'ccavenue',
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

		if ( ! defined( 'KNIT_PAY_CCAVENUE' ) ) {
			$fields[] = [
				'section'     => 'general',
				'type'        => 'custom',
				'title'       => 'Please Note',
				'description' => sprintf(
					/* translators: 1: CCAvenue */
					__( 'Knit Pay supports %1$s with a Premium Addon. But you can get this premium addon for free. Contact us to know more.%2$s', 'knit-pay-lang' ),
					__( 'CCAvenue', 'knit-pay-lang' ),
					'<br><br><a class="button button-primary" target="_blank" href="' . $this->get_url() . 'know-more"
                     role="button"><strong>Click Here to Know More</strong></a>'
				),
			];
			return $fields;
		}
		
		// Get mode from Integration mode trait.
		$fields[] = $this->get_mode_settings_fields();

		// Country.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_ccavenue_country',
			'title'    => __( 'Country', 'knit-pay-lang' ),
			'type'     => 'select',
			'options'  => [
				'in' => 'India',
				'ae' => 'UAE',
			],
			'default'  => 'in',
		];

		// Merchant ID
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_ccavenue_merchant_id',
			'title'    => __( 'Merchant ID', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( 'This is the identifier for your CCAvenue merchant Account.', 'knit-pay-lang' ),
			'required' => true,
		];

		// Access Code
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_ccavenue_access_code',
			'title'    => __( 'Access Code', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( 'This is the access code for your application.', 'knit-pay-lang' ),
			'required' => true,
		];

		// Working Key
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_ccavenue_working_key',
			'title'    => __( 'Working Key', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( 'Ensure you are using the correct key while encrypting requests from different URLs registered with CCAvenue.', 'knit-pay-lang' ),
			'required' => true,
		];

		// CCAvenue Order ID Format.
		$fields[] = [
			'section'     => 'general',
			'meta_key'    => '_pronamic_gateway_ccavenue_order_id_format',
			'title'       => __( 'Order ID Format', 'knit-pay-lang' ),
			'type'        => 'text',
			'classes'     => [ 'large-text', 'code' ],
			'default'     => '{transaction_id}',
			'description' => $this->fields_description(),
		];

		// Server Public IP.
		$fields[] = [
			'section'     => 'general',
			'title'       => __( 'Server Public IP', 'knit-pay-lang' ),
			'type'        => 'description',
			'description' => __( 'We highly recommend you to Request CCAvenue to whitelist your server IP. Some feature will not work if server IP is not whitelisted.', 'knit-pay-lang' ) . ' ' . KnitPayUtils::get_server_public_ip(),
		];

		// Return fields.
		return $fields;
	}

	public function get_config( $post_id ) {
		$config = new Config();

		$config->country         = $this->get_meta( $post_id, 'ccavenue_country' );
		$config->merchant_id     = $this->get_meta( $post_id, 'ccavenue_merchant_id' );
		$config->access_code     = $this->get_meta( $post_id, 'ccavenue_access_code' );
		$config->working_key     = $this->get_meta( $post_id, 'ccavenue_working_key' );
		$config->order_id_format = $this->get_meta( $post_id, 'ccavenue_order_id_format' );
		$config->mode            = $this->get_meta( $post_id, 'mode' );

		if ( empty( $config->country ) ) {
			$config->country = 'in';
		}

		if ( empty( $config->order_id_format ) ) {
			$config->order_id_format = '{transaction_id}';
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
		if ( ! ( filter_has_var( INPUT_GET, 'kp_ccavenue_payment_id' ) && filter_has_var( INPUT_POST, 'encResp' ) ) ) {
			return;
		}

		$payment_id = filter_input( INPUT_GET, 'kp_ccavenue_payment_id', FILTER_SANITIZE_NUMBER_INT );

		$payment = get_pronamic_payment( $payment_id );

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

	private function fields_description() {
		return sprintf( __( 'Available tags: %s', 'knit-pay-lang' ), sprintf( '<code>%s</code> <code>%s</code> <code>%s</code>', '{transaction_id}', '{payment_description}', '{order_id}' ) );
	}
}
