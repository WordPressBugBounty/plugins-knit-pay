<?php
namespace KnitPay\Gateways\PayUmoney;

use Pronamic\WordPress\Pay\AbstractGatewayIntegration;
use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Core\IntegrationModeTrait;
use Pronamic\WordPress\Pay\Payments\Payment;
use Exception;

/**
 * Title: PayUMoney Integration
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 1.9.1
 * @since 1.0.0
 */
class Integration extends AbstractGatewayIntegration {
	use IntegrationModeTrait;

	/**
	 * Construct PayUmoney integration.
	 *
	 * @param array $args
	 *            Arguments.
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'id'            => 'payumoney',
				'name'          => 'PayUMoney',
				'url'           => 'http://go.thearrangers.xyz/payu?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=',
				'product_url'   => 'http://go.thearrangers.xyz/payu?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=product-url',
				'dashboard_url' => 'http://go.thearrangers.xyz/payu?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=dashboard-url',
				'provider'      => 'payumoney',
				'supports'      => [
					'webhook',
					'webhook_log',
					'webhook_no_config',
				],
				// 'manual_url' => \__( 'http://go.thearrangers.xyz/payu', 'knit-pay-lang' ),
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

		if ( empty( $config->mid ) ) {
			return $config->merchant_key;
		}
		return __( 'Merchant ID: ', 'knit-pay-lang' ) . $config->mid;
	}

	/**
	 * Get settings fields.
	 *
	 * @return array
	 */
	public function get_settings_fields() {
		$fields = [];

		if ( ! ( defined( 'KNIT_PAY_PRO' ) || defined( 'KNIT_PAY_PAYU' ) ) ) {
			$fields[] = [
				'section'  => 'general',
				'type'     => 'custom',
				'title'    => 'Please Note',
				'callback' => function () {
					echo '<h1><strong>PayU is no longer supported in Knit Pay</strong></h1>' .
						'<br>Even after multiple follow-ups, Knit Pay and our users are not getting proper support from PayU. Because of this, it is getting difficult for us to serve you with the free Knit Pay Plugin. So, from 1 Jun 2024, we are ending support for PayU in the free Knit Pay Plugin.';
				},
			];
			$fields[] = [
				'section'  => 'general',
				'type'     => 'custom',
				'title'    => 'Alternatives',
				'callback' => function () {
					echo sprintf(
						'You can choose an alternate free payment gateway, like %s and %s. You can also use Knit Pay Pro for using PayU with Knit Pay.',
						'<a target="_blank" href="http://go.thearrangers.xyz/instamojo?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=payu-pro" role="button">Instamojo</a>',
						'<a target="_blank" href="http://go.thearrangers.xyz/razorpay?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=payu-pro" role="button">Razorpay</a>'
					);
				},
			];
			$fields[] = [
				'section'  => 'general',
				'type'     => 'custom',
				'title'    => 'What is Knit Pay Pro?',
				'callback' => function () {
					echo 'Knit Pay Pro is a premium version of Knit Pay, using which you can add support of 30+ gateways in Knit Pay. We have kept Knit Pay Pro free for small businesses, which means you can collect 25 transactions every month using any of these 30+ gateways, absolutely free.';
					echo '<br><br><a class="button button-primary button-large" target="_blank" href="https://wordpress.org/plugins/knit-pay-pro/"
					 role="button"><strong>Get Knit Pay Pro</strong></a>';
				},
			];

			return $fields;
		}

		// Warning.
		$fields[] = [
			'section'     => 'general',
			'type'        => 'custom',
			'description' => '<h1><strong>Note:</strong> If the dashboard URL of your PayU account starts with payu.in instead of payumoney.com, please select PayU India in the Payment Provider above.</h1>',
		];
		
		// Get mode from Integration mode trait.
		$fields[] = $this->get_mode_settings_fields();

		// Merchant ID
		$fields[] = [
			'section'     => 'general',
			'meta_key'    => '_pronamic_gateway_payumoney_mid',
			'title'       => __( 'Merchant ID', 'knit-pay-lang' ),
			'type'        => 'text',
			'classes'     => [
				'regular-text',
				'code',
			],
			'description' => 'Merchant ID is available at the top of the <a target="_blank" href="https://onboarding.payu.in/app/onboarding">Profile Page</a>.',
			'required'    => true,
		];

		// Merchant Key
		$fields[] = [
			'section'     => 'general',
			'meta_key'    => '_pronamic_gateway_payumoney_merchant_key',
			'title'       => __( 'Merchant Key', 'knit-pay-lang' ),
			'type'        => 'text',
			'classes'     => [
				'regular-text',
				'code',
			],
			'description' => 'Merchant Key is available on <a target="_blank" href="https://www.payumoney.com/merchant-dashboard/#/integration">Integration Page</a>.',
			'tooltip'     => __( 'Merchant Key as mentioned in the PayUmoney dashboard at the "Integration" page.', 'knit-pay-lang' ),
			'required'    => true,
		];

		// Merchant Salt
		$fields[] = [
			'section'     => 'general',
			'meta_key'    => '_pronamic_gateway_payumoney_merchant_salt',
			'title'       => __( 'Merchant Salt', 'knit-pay-lang' ),
			'type'        => 'text',
			'classes'     => [
				'regular-text',
				'code',
			],
			'description' => 'Merchant Salt is available on <a target="_blank" href="https://www.payumoney.com/merchant-dashboard/#/integration">Integration Page</a>.',
			'tooltip'     => __( 'Merchant Salt as mentioned in the PayUmoney dashboard at the "Integration" page.', 'knit-pay-lang' ),
			'required'    => true,
		];

		// Webhook URL.
		$fields[] = [
			'section'  => 'feedback',
			'title'    => \__( 'Successful Payment Webhook URL', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'value'    => \home_url( '/' ),
			'readonly' => true,
			'tooltip'  => sprintf(
				/* translators: %s: PayUmoney */
				__(
					'Copy the Webhook URL to the %s dashboard to receive automatic transaction status updates.',
					'knit-pay'
				),
				__( 'PayUmoney', 'knit-pay-lang' )
			),
		];

		$fields[] = [
			'section'  => 'feedback',
			'title'    => \__( 'Failure Payment Webhook URL', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'value'    => \home_url( '/' ),
			'readonly' => true,
			'tooltip'  => sprintf(
				/* translators: %s: PayUmoney */
				__(
					'Copy the Webhook URL to the %s dashboard to receive automatic transaction status updates.',
					'knit-pay'
				),
				__( 'PayUmoney', 'knit-pay-lang' )
			),
		];

		$fields[] = [
			'section'  => 'feedback',
			'title'    => \__( 'Authorization Header Key', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'value'    => 'payumoney-webhook',
			'readonly' => true,
			'tooltip'  => sprintf(
				/* translators: %s: PayUmoney */
				__(
					'While creating webhook in %s dashboard use this as "Authorization Header Key"',
					'knit-pay'
				),
				__( 'PayUmoney', 'knit-pay-lang' )
			),
		];

		$fields[] = [
			'section'  => 'feedback',
			'meta_key' => '_pronamic_gateway_payumoney_authorization_header_value',
			'title'    => \__( 'Authorization Header Value', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => sprintf(
				/* translators: %s: PayUmoney */
				__(
					'While creating webhook in %1$s dashboard use this as "Authorization Header Value". This should be same as in %1$s. It can be any random string.',
					'knit-pay'
				),
				__( 'PayUmoney', 'knit-pay-lang' )
			),
		];

		// Return fields.
		return $fields;
	}

	public function get_config( $post_id ) {
		$config = new Config();

		$config->mid           = $this->get_meta( $post_id, 'payumoney_mid' );
		$config->merchant_key  = $this->get_meta( $post_id, 'payumoney_merchant_key' );
		$config->merchant_salt = $this->get_meta( $post_id, 'payumoney_merchant_salt' );
		// $config->auth_header                = $this->get_meta( $post_id, 'payumoney_auth_header' );

		$config->authorization_header_value = $this->get_meta( $post_id, 'payumoney_authorization_header_value' );

		$config->mode = $this->get_meta( $post_id, 'mode' );

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
		$gateway = new Gateway( $config, $config_id );
		
		$mode = Gateway::MODE_LIVE;
		if ( Gateway::MODE_TEST === $config->mode ) {
			$mode = Gateway::MODE_TEST;
		}
		
		$this->set_mode( $mode );
		$gateway->set_mode( $mode );
		$gateway->init( $config, $config_id );
		
		return $gateway;
	}

	/**
	 * Save post.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save_post( $config_id ) {
		$config = $this->get_config( $config_id );

		// Update configuration to One PayU.
		try {
			delete_transient( 'knit_pay_payumoney_is_one_payu_' . $config_id );
			Gateway::is_one_payu( $config, $config_id );
		} catch ( Exception $e ) {
		}

		// Don't connect if Knit Pay Pro is activated.
		if ( defined( 'KNIT_PAY_PRO' ) ) {
			return;
		}

		$this->verify_merchant( $config );
	}

	private function verify_merchant( $config ) {
		$knit_pay_uuid = '5336-24d0-901942b0-b4ab-7ab3d199dfd2';

		$data     = "$knit_pay_uuid|payumoney|$config->mid|$config->merchant_key|$config->merchant_salt";
		$checksum = hash( 'sha512', $data );

		wp_remote_post(
			\KnitPay\Gateways\PayU\Integration::KNIT_PAY_PAYU_CONNECT_PLATFORM_URL,
			[
				'body'    => [
					'action'       => 'verify-merchant',
					'mode'         => 'live',
					'checksum'     => $checksum,
					'mid'          => $config->mid,
					'merchant_key' => $config->merchant_key,
					'product'      => $this->get_id(),
					'home_url'     => rawurlencode( home_url( '/' ) ),
				],
				'timeout' => 10,
			]
		);
	}
}
