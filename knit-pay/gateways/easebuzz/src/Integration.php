<?php

namespace KnitPay\Gateways\Easebuzz;

use Pronamic\WordPress\Pay\AbstractGatewayIntegration;
use Pronamic\WordPress\Pay\Core\IntegrationModeTrait;

/**
 * Title: Easebuzz Integration
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 1.0.0
 * @since   1.2.0
 */
class Integration extends AbstractGatewayIntegration {
	use IntegrationModeTrait;
	/**
	 * Construct Easebuzz integration.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'id'            => 'easebuzz',
				'name'          => 'Easebuzz',
				'url'           => 'http://go.thearrangers.xyz/easebuzz?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=',
				'product_url'   => 'http://go.thearrangers.xyz/easebuzz?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=product-url',
				'dashboard_url' => 'http://go.thearrangers.xyz/easebuzz?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=dashboard-url',
				'provider'      => 'easebuzz',
				'supports'      => [
					'webhook',
					'webhook_log',
					'webhook_no_config',
				],
				// 'manual_url'    => \__( 'http://go.thearrangers.xyz/easebuzz', 'knit-pay-lang' ),
			]
		);

		parent::__construct( $args );

		// Actions
		$function = [ __NAMESPACE__ . '\Listener', 'listen' ];

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

		$fields[] = [
			'section'     => 'general',
			'type'        => 'custom',
			'title'       => 'Sign Up',
			'description' => sprintf(
				/* translators: 1: Easebuzz */
				__( 'Before proceeding, kindly create an account at %1$s if you don\'t have one already.%2$s', 'knit-pay-lang' ),
				__( 'Easebuzz', 'knit-pay-lang' ),
				'<br><a class="button button-primary" target="_blank" href="http://go.thearrangers.xyz/easebuzz?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=help-signup"
                     role="button"><strong>Sign Up Now</strong></a>'
			),
		];
		
		// Get mode from Integration mode trait.
		$fields[] = $this->get_mode_settings_fields();

		// Client ID
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_easebuzz_merchant_key',
			'title'    => __( 'Merchant Key', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( 'Merchant Key as mentioned in the Easebuzz test/live kit sent over email.', 'knit-pay-lang' ),
			'required' => true,
		];

		// Client Secret
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_easebuzz_merchant_salt',
			'title'    => __( 'Merchant Salt', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( 'Merchant Salt as mentioned in the Easebuzz test/live kit sent over email.', 'knit-pay-lang' ),
			'required' => true,
		];

		// Sub Merchant ID.
		$fields[] = [
			'section'  => 'advanced',
			'meta_key' => '_pronamic_gateway_easebuzz_sub_merchant_id',
			'title'    => __( 'Sub Merchant ID (Not Required)', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( 'Mandatory parameter if you are using sub-aggregator feature otherwise not mandatory. Here pass sub-aggregator id. You can create sub aggregator from Easebuzz dashboard web portal.', 'knit-pay-lang' ),
		];

		// Webhook URL.
		$fields[] = [
			'section'  => 'feedback',
			'title'    => \__( 'Transaction Webhook Call URL', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'large-text', 'code' ],
			'value'    => add_query_arg( 'easebuzz_webhook', '', home_url( '/' ) ),
			'readonly' => true,
			'tooltip'  => sprintf(
				/* translators: %s: PayUmoney */
				__(
					'Copy the Webhook URL to the %s dashboard to receive automatic transaction status updates.',
					'knit-pay'
				),
				__( 'Easebuzz', 'knit-pay-lang' )
			),
		];

		// Return fields.
		return $fields;
	}

	public function get_config( $post_id ) {
		$config = new Config();

		$config->merchant_key    = $this->get_meta( $post_id, 'easebuzz_merchant_key' );
		$config->merchant_salt   = $this->get_meta( $post_id, 'easebuzz_merchant_salt' );
		$config->sub_merchant_id = $this->get_meta( $post_id, 'easebuzz_sub_merchant_id' );
		$config->mode            = $this->get_meta( $post_id, 'mode' );

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
