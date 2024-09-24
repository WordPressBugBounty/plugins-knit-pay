<?php

namespace KnitPay\Gateways\Paytr;

use KnitPay\Utils as KnitPayUtils;
use Pronamic\WordPress\Pay\AbstractGatewayIntegration;
use Pronamic\WordPress\Pay\Core\IntegrationModeTrait;
use Pronamic\WordPress\Pay\Payments\Payment;

/**
 * Title: PayTR Integration
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.86.0.0
 * @since   8.86.0.0
 */
class Integration extends AbstractGatewayIntegration {
	use IntegrationModeTrait;

	/**
	 * Construct PayTR integration.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = [] ) {  
		$args = wp_parse_args(
			$args,
			[
				'id'       => 'paytr',
				'name'     => 'PayTR',
				'provider' => 'paytr',
				'supports' => [
					'webhook',
					'webhook_log',
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

		\add_filter( 'pronamic_payment_provider_url_paytr', [ $this, 'payment_provider_url' ], 10, 2 );
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
	 * Payment provider URL.
	 *
	 * @param string|null $url     Payment provider URL.
	 * @param Payment     $payment Payment.
	 * @return string|null
	 */
	public function payment_provider_url( $url, Payment $payment ) {
		$transaction_id = $payment->get_transaction_id();
		
		return \sprintf( 'https://www.paytr.com/magaza/islemler?merchant_oid=%s', $transaction_id );
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

		// Merchant ID.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_paytr_merchant_id',
			'title'    => __( 'Merchant ID', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'required' => true,
			'filter'   => FILTER_VALIDATE_INT,
		];

		// Merchant Key.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_paytr_merchant_key',
			'title'    => __( 'Merchant Key', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'required' => true,
		];

		// Merchant Salt.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_paytr_merchant_salt',
			'title'    => __( 'Merchant Salt', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
		];
		
		// Callback URL.
		$config_id = KnitPayUtils::get_gateway_config_id();
		
		$fields[] = [
			'section'     => 'feedback',
			'title'       => \__( 'Callback URL', 'knit-pay-lang' ),
			'type'        => 'text',
			'classes'     => [ 'large-text', 'code' ],
			'value'       => add_query_arg(
				[
					'kp_paytr_webhook' => '', 
					'kp_config_id'     => $config_id,
				],
				home_url( '/' ) 
			),
			'readonly'    => true,
			'description' => sprintf( __( 'You must add the following notification url to your <a href="https://www.paytr.com/magaza/ayarlar" target="_blank">Notification URL Settings.</a>' ) ),
		];

		// Return fields.
		return $fields;
	}

	public function get_config( $post_id ) {
		$config = new Config();

		$config->merchant_id   = $this->get_meta( $post_id, 'paytr_merchant_id' );
		$config->merchant_key  = $this->get_meta( $post_id, 'paytr_merchant_key' );
		$config->merchant_salt = $this->get_meta( $post_id, 'paytr_merchant_salt' );
		$config->mode          = $this->get_meta( $post_id, 'mode' );

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
