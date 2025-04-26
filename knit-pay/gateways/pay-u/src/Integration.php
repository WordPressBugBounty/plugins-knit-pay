<?php
namespace KnitPay\Gateways\PayU;

use Pronamic\WordPress\Html\Element;
use Pronamic\WordPress\Pay\AbstractGatewayIntegration;
use Pronamic\WordPress\Pay\Core\IntegrationModeTrait;

/**
 * Title: PayU Integration
 * Copyright: 2020-2025 Knit Pay
 *
 * @author Knit Pay
 * @version 5.4.0
 * @since 5.4.0
 */
class Integration extends AbstractGatewayIntegration {
	use IntegrationModeTrait;

	/**
	 * Construct PayU integration.
	 *
	 * @param array $args
	 *            Arguments.
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'id'            => 'pay-u',
				'name'          => 'PayU India/PayUBiz',
				'url'           => 'ttp://go.thearrangers.xyz/payu?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=',
				'product_url'   => 'http://go.thearrangers.xyz/payu?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=product-url',
				'dashboard_url' => 'http://go.thearrangers.xyz/payu?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=dashboard-url',
				'provider'      => 'pay-u',
				'supports'      => [
					'webhook',
					'webhook_log',
					'webhook_no_config',
				],
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

		return $config->merchant_key;
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
			'title'       => 'Sign Up Now',
			'description' => sprintf(
				/* translators: 1: PayU */
				__( 'Before proceeding, kindly create an account at %1$s if you don\'t have one already.%2$s', 'knit-pay-lang' ),
				__( 'PayU', 'knit-pay-lang' ),
				'<br><a class="button button-primary" target="_blank" href="http://go.thearrangers.xyz/payu?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=help-signup"
                     role="button"><strong>Sign Up on PayU Live</strong></a>'
			) . sprintf(
					/* translators: 1: PayU */
				__( '<br><br>For Testing, kindly create an account at %1$s if you don\'t have one already.%2$s', 'knit-pay-lang' ),
				__( '<strong>PayU UAT Dashboard</strong>', 'knit-pay-lang' ),
				'<br><a class="button button-primary" target="_blank" href="https://test.payumoney.com/url/QIJLMsgaurL3"
                     role="button"><strong>Sign Up on PayU Test/UAT</strong></a>'
			),
		];
		
		// Get mode from Integration mode trait.
		$fields[] = $this->get_mode_settings_fields();

		// Merchant Key
		$fields[] = [
			'section'     => 'general',
			'meta_key'    => '_pronamic_gateway_payu_merchant_key',
			'title'       => __( 'Merchant Key', 'knit-pay-lang' ),
			'type'        => 'text',
			'classes'     => [
				'regular-text',
				'code',
			],
			'description' => 'Merchant Key is available on <a target="_blank" href="https://www.payu.in/business/payment-gateway/integration">Integration Page</a>.',
			'required'    => true,
		];

		// Merchant Salt
		$fields[] = [
			'section'     => 'general',
			'meta_key'    => '_pronamic_gateway_payu_merchant_salt',
			'title'       => __( 'Merchant Salt', 'knit-pay-lang' ),
			'type'        => 'text',
			'classes'     => [
				'regular-text',
				'code',
			],
			'description' => 'Merchant Salt is available on <a target="_blank" href="https://www.payu.in/business/payment-gateway/integration">Integration Page</a>. You can use anyone from Merchant Salt, Merchant Salt v1, or Merchant Salt v2. Few Salts do not work with some accounts. Try changing Salt if you face Hash issues.',
			'required'    => true,
		];

		// Transaction Fees Percentage.
		$fields[] = [
			'section'     => 'advanced',
			'meta_key'    => '_pronamic_gateway_payu_transaction_fees_percentage',
			'title'       => __( 'Transaction Fees Percentage', 'knit-pay-lang' ),
			'type'        => 'text',
			'classes'     => [ 'regular-text', 'code' ],
			'description' => __( 'Percentage of transaction fees you want to collect from the customer. For example: 2.36 for 2% + GST; 3.54 for 3% + GST. Keep it blank for not collecting transaction fees from the customer.', 'knit-pay-lang' ),
		];

		// Transaction Fees Fix Amount.
		$fields[] = [
			'section'     => 'advanced',
			'meta_key'    => '_pronamic_gateway_payu_transaction_fees_fix',
			'title'       => __( 'Transaction Fees Fix Amount', 'knit-pay-lang' ),
			'type'        => 'text',
			'classes'     => [ 'regular-text', 'code' ],
			'description' => __( 'Fix amount of transaction fees you want to collect from the customer. For example, 5 for adding 5 in the final amount. Keep it blank for not collecting fixed transaction fees from the customer.', 'knit-pay-lang' ),
		];

		// Webhook URL.
		$fields[] = [
			'section'  => 'feedback',
			'title'    => \__( 'Successful Payment Webhook URL', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'large-text', 'code' ],
			'value'    => add_query_arg( 'kp_payu_webhook', '', home_url( '/' ) ),
			'readonly' => true,
			'tooltip'  => sprintf(
				/* translators: %s: PayU */
				__(
					'Copy the Webhook URL to the %s dashboard to receive automatic transaction status updates.',
					'knit-pay'
				),
				__( 'PayU', 'knit-pay-lang' )
			),
		];

		// Return fields.
		return $fields;
	}

	public function get_config( $post_id ) {
		$config = new Config();

		$config->merchant_key                = $this->get_meta( $post_id, 'payu_merchant_key' );
		$config->merchant_salt               = $this->get_meta( $post_id, 'payu_merchant_salt' );
		$config->transaction_fees_percentage = $this->get_meta( $post_id, 'payu_transaction_fees_percentage' );
		$config->transaction_fees_fix        = $this->get_meta( $post_id, 'payu_transaction_fees_fix' );

		$config->mode = $this->get_meta( $post_id, 'mode' );

		if ( empty( $config->transaction_fees_percentage ) ) {
			$config->transaction_fees_percentage = 0;
		}

		if ( empty( $config->transaction_fees_fix ) ) {
			$config->transaction_fees_fix = 0;
		}

		return $config;
	}

	/**
	 * Get gateway.
	 *
	 * @param int $post_id
	 *            Post ID.
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
}
