<?php

namespace KnitPay\Gateways\Razorpay;

use Pronamic\WordPress\DateTime\DateTime;
use KnitPay\Gateways\IntegrationOAuthClient;
use Pronamic\WordPress\Pay\Core\IntegrationModeTrait;
use Pronamic\WordPress\Pay\Core\PaymentMethods;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;
use WP_Query;
/**
 * Title: Razorpay Integration
 * Copyright: 2020-2025 Knit Pay
 *
 * @author  Knit Pay
 * @version 1.0.0
 * @since   1.7.0
 */
class Integration extends IntegrationOAuthClient {
	use IntegrationModeTrait;

	/**
	 * Construct Razorpay integration.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'id'            => 'razorpay',
				'name'          => 'Razorpay - Easy Connect',
				'gateway_name'  => 'Razorpay', // Set this if display name and actual name are different.
				'url'           => 'http://go.thearrangers.xyz/razorpay?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=',
				'product_url'   => 'http://go.thearrangers.xyz/razorpay?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=product-url',
				'dashboard_url' => 'http://go.thearrangers.xyz/razorpay?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=dashboard-url',
				'provider'      => 'razorpay',
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

	public function allowed_redirect_hosts( $hosts ) {
		$hosts[] = 'auth.razorpay.com';
		return $hosts;
	}

	/**
	 * Setup.
	 */
	public function setup() {
		parent::setup();

		// Subscription status change listener.
		add_action( 'pronamic_subscription_status_update', [ $this, 'subscription_status_update_listener' ], 10, 4 );
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

		return empty( $config->merchant_id ) ? $config->key_id : $config->merchant_id;
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

		if ( null === $transaction_id ) {
			return $url;
		}

		return \sprintf( 'https://dashboard.razorpay.com/app/orders/%s', $payment->get_meta( 'razorpay_order_id' ) );
	}

	protected function get_basic_auth_fields( $fields ) {
		// Key ID.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_razorpay_key_id',
			'title'    => __( 'API Key ID', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( 'API Key ID is mentioned on the Razorpay dashboard at the "API Keys" tab of the settings page.', 'knit-pay-lang' ),
			'required' => true,
		];
		
		// Key Secret.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_razorpay_key_secret',
			'title'    => __( 'API Key Secret', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( 'API Key Secret is mentioned on the Razorpay dashboard at the "API Keys" tab of the settings page.', 'knit-pay-lang' ),
			'required' => true,
		];

		return $fields;
	}

	protected function show_common_setting_fields( $fields ) {
		$checkout_modes_options = [
			Config::CHECKOUT_STANDARD_MODE => 'Standard Checkout - Payment Box',
		];
		// Currently Hosted mode is not working with Razorpay Connect.
		if ( $this->is_auth_basic_enabled() && ! defined( 'KNIT_PAY_RAZORPAY_SUBSCRIPTION' ) ) {
			$checkout_modes_options[ Config::CHECKOUT_HOSTED_MODE ] = 'Hosted Checkout - Payment Page';
		}
		// TODO: Add support for payment link.

		// Merchant/Company Name.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_razorpay_company_name',
			'title'    => __( 'Merchant/Brand/Company Name', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( 'The merchant/company name shown in the Checkout form.', 'knit-pay-lang' ),
		];

		// Checkout Image.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_razorpay_checkout_image',
			'title'    => __( 'Checkout Image', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'large-text', 'code' ],
			'tooltip'  => __( 'Link to an image (usually your business logo) shown in the Checkout form. Can also be a base64 string, if loading the image from a network is not desirable. Keep it blank to use default image.', 'knit-pay-lang' ),
		];

		// Checkout Mode.
		$fields[] = [
			'section'  => 'general',
			'filter'   => FILTER_SANITIZE_NUMBER_INT,
			'meta_key' => '_pronamic_gateway_razorpay_checkout_mode',
			'title'    => __( 'Checkout Mode', 'knit-pay-lang' ),
			'type'     => 'select',
			'options'  => $checkout_modes_options,
			'default'  => Config::CHECKOUT_STANDARD_MODE,
		];
		
		// Transaction Fees Percentage.
		$fields[] = [
			'section'     => 'advanced',
			'meta_key'    => '_pronamic_gateway_razorpay_transaction_fees_percentage',
			'title'       => __( 'Transaction Fees Percentage', 'knit-pay-lang' ),
			'type'        => 'text',
			'classes'     => [ 'regular-text', 'code' ],
			'description' => __( 'Percentage of transaction fees you want to collect from the customer. For example: 2.36 for 2% + GST; 3.54 for 3% + GST. Keep it blank for not collecting transaction fees from the customer.', 'knit-pay-lang' ),
		];
		
		// Transaction Fees Fix Amount.
		$fields[] = [
			'section'     => 'advanced',
			'meta_key'    => '_pronamic_gateway_razorpay_transaction_fees_fix',
			'title'       => __( 'Transaction Fees Fix Amount', 'knit-pay-lang' ),
			'type'        => 'text',
			'classes'     => [ 'regular-text', 'code' ],
			'description' => __( 'Fix amount of transaction fees you want to collect from the customer. For example, 5 for adding 5 in the final amount. Keep it blank for not collecting fixed transaction fees from the customer.', 'knit-pay-lang' ),
		];

		// Expire Old Pending Payments.
		$fields[] = [
			'section'     => 'advanced',
			'filter'      => FILTER_VALIDATE_BOOLEAN,
			'meta_key'    => '_pronamic_gateway_razorpay_expire_old_payments',
			'title'       => __( 'Expire Old Pending Payments', 'knit-pay-lang' ),
			'type'        => 'checkbox',
			'description' => 'If this option is enabled, 24 hours old pending payments will be marked as expired in Knit Pay.',
			'label'       => __( 'Mark old pending Payments as expired in Knit Pay.', 'knit-pay-lang' ),
			'default'     => true,
		];

		// TODO: Add affordibility widget support.

		// Auto Webhook Setup Supported.
		$fields[] = [
			'section'     => 'feedback',
			'title'       => __( 'Auto Webhook Setup Supported', 'knit-pay-lang' ),
			'type'        => 'description',
			'description' => 'Knit Pay automatically creates webhook configuration in Razorpay Dashboard as soon as Razorpay configuration is published or saved. Kindly raise the Knit Pay support ticket or configure the webhook manually if the automatic webhook setup fails.',
		];

		// Webhook URL.
		$fields[] = [
			'section'  => 'feedback',
			'title'    => \__( 'Webhook URL', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'large-text', 'code' ],
			'value'    => add_query_arg( 'kp_razorpay_webhook', '', home_url( '/' ) ),
			'readonly' => true,
			'tooltip'  => sprintf(
				/* translators: %s: Razorpay */
				__(
					'Copy the Webhook URL to the %s dashboard to receive automatic transaction status updates.',
					'knit-pay'
				),
				__( 'Razorpay', 'knit-pay-lang' )
			),
		];

		$fields[] = [
			'section'  => 'feedback',
			'meta_key' => '_pronamic_gateway_razorpay_webhook_secret',
			'title'    => \__( 'Webhook Secret', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  =>
			__(
				'Create a new webhook secret. This can be a random string, and you don\'t have to remember it. Do not use your password or Key Secret here.',
				'knit-pay'
			),
		];

		$fields[] = [
			'section'     => 'feedback',
			'title'       => \__( 'Active Events', 'knit-pay-lang' ),
			'type'        => 'description',
			'description' => sprintf(
				/* translators: 1: Razorpay */
				__( 'In Active Events section check payment authorized and failed events.', 'knit-pay-lang' ),
				__( 'Razorpay', 'knit-pay-lang' )
			),
		];

		return $fields;
	}

	public function get_child_config( $post_id ) {
		$config = new Config();

		$config->key_id                      = $this->get_meta( $post_id, 'razorpay_key_id' );
		$config->key_secret                  = $this->get_meta( $post_id, 'razorpay_key_secret' );
		$config->webhook_id                  = $this->get_meta( $post_id, 'razorpay_webhook_id' );
		$config->webhook_secret              = $this->get_meta( $post_id, 'razorpay_webhook_secret' );
		$config->is_connected                = $this->get_meta( $post_id, 'razorpay_is_connected' );
		$config->connected_at                = $this->get_meta( $post_id, 'razorpay_connected_at' );
		$config->expires_at                  = $this->get_meta( $post_id, 'razorpay_expires_at' );
		$config->access_token                = $this->get_meta( $post_id, 'razorpay_access_token' );
		$config->refresh_token               = $this->get_meta( $post_id, 'razorpay_refresh_token' );
		$config->company_name                = $this->get_meta( $post_id, 'razorpay_company_name' );
		$config->checkout_image              = $this->get_meta( $post_id, 'razorpay_checkout_image' );
		$config->checkout_mode               = $this->get_meta( $post_id, 'razorpay_checkout_mode' );
		$config->transaction_fees_percentage = $this->get_meta( $post_id, 'razorpay_transaction_fees_percentage' );
		$config->transaction_fees_fix        = $this->get_meta( $post_id, 'razorpay_transaction_fees_fix' );
		$config->merchant_id                 = $this->get_meta( $post_id, 'razorpay_merchant_id' );
		$config->connection_fail_count       = $this->get_meta( $post_id, 'razorpay_connection_fail_count' );
		$config->expire_old_payments         = $this->get_meta( $post_id, 'razorpay_expire_old_payments' );
		$config->mode                        = $this->get_meta( $post_id, 'mode' );

		if ( empty( $config->checkout_mode ) ) {
			$config->checkout_mode = Config::CHECKOUT_STANDARD_MODE;
		}
		$config->checkout_mode = (int) $config->checkout_mode;

		if ( empty( $config->transaction_fees_percentage ) ) {
			$config->transaction_fees_percentage = 0;
		}

		if ( empty( $config->transaction_fees_fix ) ) {
			$config->transaction_fees_fix = 0;
		}

		if ( empty( $config->connection_fail_count ) ) {
			$config->connection_fail_count = 0;
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
		$config = $this->get_config( $config_id );

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

	protected function is_mode_changed( $config ) {
		return ! strpos( $config->key_id, $config->mode );
	}

	public function clear_child_config( $config_id ) {
		delete_post_meta( $config_id, '_pronamic_gateway_razorpay_key_id' );
		delete_post_meta( $config_id, '_pronamic_gateway_razorpay_key_secret' );
		delete_post_meta( $config_id, '_pronamic_gateway_razorpay_webhook_id' );
		delete_post_meta( $config_id, '_pronamic_gateway_razorpay_is_connected' );
		delete_post_meta( $config_id, '_pronamic_gateway_razorpay_expires_at' );
		delete_post_meta( $config_id, '_pronamic_gateway_razorpay_access_token' );
		delete_post_meta( $config_id, '_pronamic_gateway_razorpay_refresh_token' );
		delete_post_meta( $config_id, '_pronamic_gateway_razorpay_merchant_id' );
		delete_post_meta( $config_id, '_pronamic_gateway_razorpay_connection_fail_count' );
	}

	public function update_connection_status() {
		if ( ! ( filter_has_var( INPUT_GET, 'gateway_id' ) ) ) {
			return;
		}

		$gateway_id = sanitize_text_field( $_GET['gateway_id'] );

		// Don't interfere if rzp-wppcommerce attempting to connect.
		if ( 'rzp-woocommerce' === $gateway_id ) {
			return;
		}

		parent::update_connection_status();
	}

	protected static function configure_webhook( $config_id ) {
		$integration = new self();
		$webhook     = new Webhook( $config_id, $integration->get_config( $config_id ) );
		$webhook->configure_webhook();
	}

	protected function create_basic_connection( $config_id ) {
		if ( $this->is_auth_basic_enabled() ) {
			// Save Account ID.
			$gateway          = $this->get_gateway( $config_id );
			$merchant_details = $gateway->get_balance();
			if ( isset( $merchant_details['merchant_id'] ) ) {
				update_post_meta( $config_id, '_pronamic_gateway_razorpay_merchant_id', $merchant_details['merchant_id'] );
			}

			delete_post_meta( $config_id, '_pronamic_gateway_razorpay_is_connected' );
			delete_post_meta( $config_id, '_pronamic_gateway_razorpay_expires_at' );
			delete_post_meta( $config_id, '_pronamic_gateway_razorpay_access_token' );
			delete_post_meta( $config_id, '_pronamic_gateway_razorpay_refresh_token' );
		}
	}

	public function subscription_status_update_listener( $subscription, $can_redirect, $previous_status, $updated_status ) {
		$config_id = $subscription->get_config_id();

		if ( empty( $config_id ) ) {
			return;
		}

		$gateway = $this->get_gateway( $config_id );

		$gateway->subscription_status_update( $subscription, $can_redirect, $previous_status, $updated_status );
	}

	protected function is_auth_basic_enabled() {
		return defined( 'KNIT_PAY_RAZORPAY_API' ) || 'razorpay-pro' === $this->get_id();
	}

	protected function is_auth_basic_connected( $config ) {
		return ! empty( $config->key_secret );
	}
}
