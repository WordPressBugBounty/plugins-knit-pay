<?php

namespace KnitPay\Gateways\Razorpay;

use Pronamic\WordPress\DateTime\DateTime;
use Pronamic\WordPress\Pay\AbstractGatewayIntegration;
use Pronamic\WordPress\Pay\Core\IntegrationModeTrait;
use Pronamic\WordPress\Pay\Core\PaymentMethods;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;
use WP_Query;
/**
 * Title: Razorpay Integration
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 1.0.0
 * @since   1.7.0
 */
class Integration extends AbstractGatewayIntegration {
	use IntegrationModeTrait;

	private $config;
	private $can_create_connection;

	const KNIT_PAY_RAZORPAY_PLATFORM_CONNECT_URL = 'https://razorpay-connect.knitpay.org/';
	const RENEWAL_TIME_BEFORE_TOKEN_EXPIRE       = 15 * MINUTE_IN_SECONDS; // 15 minutes.

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
				'name'          => 'Razorpay',
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

		// create connection if Merchant ID not available.
		$this->can_create_connection = true;
	}

	public function allowed_redirect_hosts( $hosts ) {
		$hosts[] = 'auth.razorpay.com';
		return $hosts;
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

		\add_filter( 'pronamic_payment_provider_url_razorpay', [ $this, 'payment_provider_url' ], 10, 2 );

		// Connect/Disconnect Listener.
		$function = [ __NAMESPACE__ . '\Integration', 'update_connection_status' ];
		if ( ! has_action( 'wp_loaded', $function ) ) {
			add_action( 'wp_loaded', $function );
		}

		// Get new access token if it's about to get expired.
		add_action( 'knit_pay_razorpay_refresh_access_token', [ $this, 'refresh_access_token' ], 10, 1 );

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

	/**
	 * Get settings fields.
	 *
	 * @return array
	 */
	public function get_settings_fields() {
		$fields = [];
		
		// Get Config ID from Post.
		$config_id = get_the_ID();

		// try to get Config ID from Referer URL if config id not available in Post.
		if ( empty( $config_id ) ) {
			$referer_parameter = [];
			$referer_url       = wp_parse_url( wp_get_referer() );
			parse_str( $referer_url['query'], $referer_parameter );
			$config_id = isset( $referer_parameter['post'] ) ? $referer_parameter['post'] : 0;
		}
		if ( ! empty( $config_id ) ) {
			$this->config = $this->get_config( $config_id );
		}

		// Get mode from Integration mode trait.
		$fields[] = $this->get_mode_settings_fields();

		$checkout_modes_options = [
			Config::CHECKOUT_STANDARD_MODE => 'Standard Checkout - Payment Box',
		];
		// Currently Hosted mode is not working with Razorpay Connect.
		if ( $this->is_auth_basic_enabled() && ! defined( 'KNIT_PAY_RAZORPAY_SUBSCRIPTION' ) ) {
			$checkout_modes_options[ Config::CHECKOUT_HOSTED_MODE ] = 'Hosted Checkout - Payment Page';
		}
		// TODO: Add support for payment link.

		$mode = isset( $_GET['gateway_mode'] ) ? sanitize_text_field( $_GET['gateway_mode'] ) : null;

		if ( $this->is_auth_basic_enabled() ) {
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
		} elseif ( ! isset( $this->config )
			|| empty( $this->config->access_token )
			|| ( isset( $mode ) && ! strpos( $this->config->key_id, $mode ) ) ) {

			// Signup.
			/*
			 $fields[] = array(
				'section' => 'general',
				'type'    => 'custom',
				'title'   => 'Limited Period Offer',
				'callback'    => function () {
				echo '<p>' . __( 'Encash your customer payments in an instant, at 0% additional charge. Offer valid on the new account for limited time.' ) . '</p>' .
				'<br /> <a class="button button-primary button-large" target="_blank" href="' . $this->get_url() . 'special-offer"
				role="button"><strong>Sign Up Now</strong></a>';
				}
			); */

			// Razorpay Connect Description.
			$fields[] = [
				'section'  => 'general',
				'type'     => 'custom',
				'title'    => 'Razorpay Connect',
				'callback' => function () {
					echo '<p><h1>' . __( 'How it works?' ) . '</h1></p>' .
					'<p>' . __( 'To provide a seamless integration experience, Knit Pay has introduced Razorpay Platform Connect. Now you can integrate Razorpay in Knit Pay with just a few clicks.' ) . '</p>' .
					'<p>' . __( 'Click on "<strong>Connect with Razorpay</strong>" below to initiate the connection.' ) . '</p>';
				},
			];

			// Connect.
			$fields[] = [
				'section'  => 'general',
				'type'     => 'custom',
				'callback' => function () {
					echo '<a id="razorpay-platform-connect" class="button button-primary button-large"
		                  role="button" style="font-size: 21px;background: #3395ff;">Connect with <strong>Razorpay</strong></a>
                        <script>
                            document.getElementById("razorpay-platform-connect").addEventListener("click", function(event){
                                event.preventDefault();
                                document.getElementById("publish").click();
                            });
                        </script>';
				},
			];
		} else {
			// Remove Knit Pay as an Authorized Application.
			$fields[] = [
				'section'     => 'general',
				'title'       => __( 'Remove Knit Pay as an Connected Application for my Razorpay account.', 'knit-pay-lang' ),
				'type'        => 'custom',
				'callback'    => function () {
					echo '<script>
                    document.getElementById("_pronamic_gateway_mode").addEventListener("change", function(event){
                                event.preventDefault();
                                document.getElementById("publish").click();
                            });
                 </script>';
				},
				'description' => '<p>Removing Knit Pay as an Connected Application for your Razorpay account will remove the connection between all the sites that you have connected to Knit Pay using the same Razorpay account and connect method. Proceed with caution while disconnecting if you have multiple sites connected.</p>' .
				'<br><a class="button button-primary button-large" target="_blank" href="https://dashboard.razorpay.com/app/website-app-settings/applications" role="button"><strong>View connected applications in Razorpay</strong></a>',
			];

			// Connected with Razorpay.
			$fields[] = [
				'section'     => 'general',
				'filter'      => FILTER_VALIDATE_BOOLEAN,
				'meta_key'    => '_pronamic_gateway_razorpay_is_connected',
				'title'       => __( 'Connected with Razorpay', 'knit-pay-lang' ),
				'type'        => 'checkbox',
				'description' => 'This gateway configuration is connected with Razorpay Platform Connect. Uncheck this and save the configuration to disconnect it.',
				'label'       => __( 'Uncheck and save to disconnect the Razorpay Account.', 'knit-pay-lang' ),
			];

			// Connection Status.
			$fields[] = [
				'section'  => 'general',
				'title'    => __( 'Connection Status', 'knit-pay-lang' ),
				'type'     => 'custom',
				'callback' => [ $this, 'connection_status_box' ],
			];
		}

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

		// Return fields.
		return $fields;
	}

	public function get_config( $post_id ) {
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

		if ( empty( $config->merchant_id ) && $this->can_create_connection ) {
			$this->create_connection( $post_id );
		}

		if ( empty( $config->transaction_fees_percentage ) ) {
			$config->transaction_fees_percentage = 0;
		}

		if ( empty( $config->transaction_fees_fix ) ) {
			$config->transaction_fees_fix = 0;
		}

		if ( empty( $config->connection_fail_count ) ) {
			$config->connection_fail_count = 0;
		}

		$config->config_id = $post_id;

		// Schedule next refresh token if not done before.
		self::schedule_next_refresh_access_token( $post_id, $config->expires_at );

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

	/**
	 * When the post is saved, saves our custom data.
	 *
	 * @param int $config_id The ID of the post being saved.
	 * @return void
	 */
	public function save_post( $config_id ) {
		parent::save_post( $config_id );

		if ( $this->is_auth_basic_enabled() ) {
			$this->create_connection( $config_id );

			self::configure_webhook( $config_id );
			return;
		}

		// Execute below code only for Razorpay Connect.
		$config = $this->get_config( $config_id );

		if ( empty( $config->access_token ) || ! strpos( $config->key_id, $config->mode ) ) {
			$this->connect( $config, $config_id );
			return;
		}

		// Clear Keys if not connected.
		if ( ! $config->is_connected && ! empty( $config->access_token ) ) {
			self::clear_config( $config_id );
			return;
		}

		self::configure_webhook( $config_id );
	}

	private function connect( $config, $config_id ) {
		// Clear Old config before creating new connection.
		self::clear_config( $config_id );

		$response = wp_remote_post(
			self::KNIT_PAY_RAZORPAY_PLATFORM_CONNECT_URL,
			[
				'body'    => [
					'admin_url'  => rawurlencode( admin_url() ),
					'action'     => 'connect',
					'gateway_id' => $config_id,
					'mode'       => $config->mode,
				],
				'timeout' => 60,
			]
		);
		$result   = wp_remote_retrieve_body( $response );
		$result   = json_decode( $result );
		if ( isset( $result->error ) ) {
			echo $result->error;
			exit;
		}
		if ( isset( $result->return_url ) ) {
			add_filter( 'allowed_redirect_hosts', [ $this, 'allowed_redirect_hosts' ] );
			wp_safe_redirect( add_query_arg( 'redirect_uri', self::KNIT_PAY_RAZORPAY_PLATFORM_CONNECT_URL, $result->return_url ) );
			exit;
		}
	}

	private static function clear_config( $config_id ) {
		delete_post_meta( $config_id, '_pronamic_gateway_razorpay_key_id' );
		delete_post_meta( $config_id, '_pronamic_gateway_razorpay_key_secret' );
		delete_post_meta( $config_id, '_pronamic_gateway_razorpay_webhook_id' );
		delete_post_meta( $config_id, '_pronamic_gateway_razorpay_is_connected' );
		delete_post_meta( $config_id, '_pronamic_gateway_razorpay_expires_at' );
		delete_post_meta( $config_id, '_pronamic_gateway_razorpay_access_token' );
		delete_post_meta( $config_id, '_pronamic_gateway_razorpay_refresh_token' );
		delete_post_meta( $config_id, '_pronamic_gateway_razorpay_merchant_id' );
		delete_post_meta( $config_id, '_pronamic_gateway_razorpay_connection_fail_count' );

		// Stop Refresh Token Scheduler.
		$timestamp_next_schedule = wp_next_scheduled( 'knit_pay_razorpay_refresh_access_token', [ 'config_id' => $config_id ] );
		wp_unschedule_event( $timestamp_next_schedule, 'knit_pay_razorpay_refresh_access_token', [ 'config_id' => $config_id ] );
	}

	public static function update_connection_status() {
		if ( ! ( filter_has_var( INPUT_GET, 'razorpay_connect_status' ) && current_user_can( 'manage_options' ) ) ) {
			return;
		}

		$code                    = isset( $_GET['code'] ) ? sanitize_text_field( $_GET['code'] ) : null;
		$state                   = isset( $_GET['state'] ) ? sanitize_text_field( $_GET['state'] ) : null;
		$gateway_id              = isset( $_GET['gateway_id'] ) ? sanitize_text_field( $_GET['gateway_id'] ) : null;
		$razorpay_connect_status = isset( $_GET['razorpay_connect_status'] ) ? sanitize_text_field( $_GET['razorpay_connect_status'] ) : null;

		// Don't interfere if rzp-wppcommerce attempting to connect.
		if ( 'rzp-woocommerce' === $gateway_id ) {
			return;
		}

		if ( empty( $code ) || empty( $state ) || 'failed' === $razorpay_connect_status ) {
			self::clear_config( $gateway_id );
			self::redirect_to_config( $gateway_id );
		}

		// GET keys.
		$response = wp_remote_post(
			self::KNIT_PAY_RAZORPAY_PLATFORM_CONNECT_URL,
			[
				'body'    => [
					'code'       => $code,
					'state'      => $state,
					'gateway_id' => $gateway_id,
					'action'     => 'get-keys',
				],
				'timeout' => 90,
			]
		);
		$result   = wp_remote_retrieve_body( $response );
		$result   = json_decode( $result );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			self::redirect_to_config( $gateway_id );
			return;
		}

		self::save_token( $gateway_id, $result, true );

		// Update active payment methods.
		PaymentMethods::update_active_payment_methods();

		self::configure_webhook( $gateway_id );

		self::redirect_to_config( $gateway_id );
	}

	public function refresh_access_token( $config_id ) {
		if ( 'publish' !== get_post_status( $config_id ) ) {
			return;
		}
		
		// Don't refresh again if already refreshing.
		if ( get_transient( 'knit_pay_razorpay_refreshing_access_token_' . $config_id ) ) {
			return;
		}
		set_transient( 'knit_pay_razorpay_refreshing_access_token_' . $config_id, true, MINUTE_IN_SECONDS );
		
		$config = $this->get_config( $config_id );

		// Don't proceed further if it's API key connection.
		if ( ! empty( $config->key_secret ) && empty( $config->refresh_token ) ) {
			return;
		}

		if ( empty( $config->refresh_token ) ) {
			// Clear All configurations if Refresh Token is missing.
			self::clear_config( $config_id ); // This code was deleting configuration for mechants migrated from OAuth to API.
			return;
		}

		/*
		 $time_left_before_expire = $config->expires_at - time();
		if ( $time_left_before_expire > 0 && $time_left_before_expire > self::RENEWAL_TIME_BEFORE_TOKEN_EXPIRE + 432000 ) {
			self::schedule_next_refresh_access_token( $config_id, $config->expires_at );
			return;
		} */

		// GET keys.
		$response = wp_remote_post(
			self::KNIT_PAY_RAZORPAY_PLATFORM_CONNECT_URL,
			[
				'body'    => [
					'refresh_token' => $config->refresh_token,
					'merchant_id'   => $config->merchant_id,
					'mode'          => $config->mode,
					'action'        => 'refresh-access-token',
				],
				'timeout' => 90,
			]
		);
		$result   = wp_remote_retrieve_body( $response );
		$result   = json_decode( $result );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			$this->inc_refresh_token_fail_counter( $config, $config_id );
			self::schedule_next_refresh_access_token( $config_id, $config->expires_at );
			return;
		}

		if ( isset( $result->razorpay_connect_status ) && 'failed' === $result->razorpay_connect_status ) {
			$this->inc_refresh_token_fail_counter( $config, $config_id );

			// Client config if access is revoked.
			if ( isset( $result->error ) && isset( $result->error->description )
				&& ( 'Token has been revoked' === $result->error->description || 'Token has expired' === $result->error->description ) ) {
					self::clear_config( $config_id );
					return;
			}
		}

		self::save_token( $config_id, $result );
	}

	private static function save_token( $gateway_id, $token_data, $new_connection = false ) {
		if ( ! ( isset( $token_data->razorpay_connect_status ) && 'connected' === $token_data->razorpay_connect_status ) || empty( $token_data->expires_in ) ) {
			return;
		}

		$expires_at = time() + $token_data->expires_in - 45;

		update_post_meta( $gateway_id, '_pronamic_gateway_razorpay_key_id', $token_data->public_token );
		update_post_meta( $gateway_id, '_pronamic_gateway_razorpay_access_token', $token_data->access_token );
		update_post_meta( $gateway_id, '_pronamic_gateway_razorpay_refresh_token', $token_data->refresh_token );
		update_post_meta( $gateway_id, '_pronamic_gateway_razorpay_expires_at', $expires_at );
		update_post_meta( $gateway_id, '_pronamic_gateway_razorpay_is_connected', true );

		// Reset Connection Fail Counter.
		delete_post_meta( $gateway_id, '_pronamic_gateway_razorpay_connection_fail_count' );

		if ( $new_connection ) {
			update_post_meta( $gateway_id, '_pronamic_gateway_razorpay_connected_at', time() );
		}

		if ( isset( $token_data->merchant_id ) ) {
			update_post_meta( $gateway_id, '_pronamic_gateway_razorpay_merchant_id', $token_data->merchant_id );
		}

		self::schedule_next_refresh_access_token( $gateway_id, $expires_at );
	}

	private static function redirect_to_config( $gateway_id ) {
		wp_safe_redirect( get_edit_post_link( $gateway_id, false ) );
		exit;
	}

	private static function schedule_next_refresh_access_token( $config_id, $expires_at ) {
		if ( empty( $expires_at ) ) {
			return;
		}
		
		// Don't set next refresh cron if already refreshing.
		if ( get_transient( 'knit_pay_razorpay_refreshing_access_token_' . $config_id ) ) {
			return;
		}

		$next_schedule_time = wp_next_scheduled( 'knit_pay_razorpay_refresh_access_token', [ 'config_id' => $config_id ] );
		if ( $next_schedule_time && $next_schedule_time < $expires_at ) {
			return;
		}

		$next_schedule_time = $expires_at - self::RENEWAL_TIME_BEFORE_TOKEN_EXPIRE + wp_rand( 0, MINUTE_IN_SECONDS );
		$current_time       = time();
		if ( $next_schedule_time <= $current_time ) {
			$next_schedule_time = $current_time + wp_rand( 0, MINUTE_IN_SECONDS );
		}

		wp_schedule_single_event(
			$next_schedule_time,
			'knit_pay_razorpay_refresh_access_token',
			[ 'config_id' => $config_id ]
		);
	}

	private static function configure_webhook( $config_id ) {
		$integration = new self();
		$webhook     = new Webhook( $config_id, $integration->get_config( $config_id ) );
		$webhook->configure_webhook();
	}

	private function create_connection( $config_id ) {
		$this->can_create_connection = false;
		if ( $this->is_auth_basic_enabled() ) {
			// Save Account ID.
			$gateway          = $this->get_gateway( $config_id );
			$merchant_details = $gateway->get_balance();
			if ( isset( $merchant_details['merchant_id'] ) ) {
				update_post_meta( $config_id, '_pronamic_gateway_razorpay_merchant_id', $merchant_details['merchant_id'] );

				// Check Connection.
				$config = $this->get_config( $config_id );
				wp_remote_post(
					self::KNIT_PAY_RAZORPAY_PLATFORM_CONNECT_URL,
					[
						'body'    => [
							'admin_url'   => rawurlencode( home_url( '/' ) ),
							'action'      => 'check-connection',
							'merchant_id' => $merchant_details['merchant_id'],
							'mode'        => $config->mode,
							'auth_type'   => 'Basic',
						],
						'timeout' => 10,
					]
				);
			}

			delete_post_meta( $config_id, '_pronamic_gateway_razorpay_is_connected' );
			delete_post_meta( $config_id, '_pronamic_gateway_razorpay_expires_at' );
			delete_post_meta( $config_id, '_pronamic_gateway_razorpay_access_token' );
			delete_post_meta( $config_id, '_pronamic_gateway_razorpay_refresh_token' );
		}
	}
	
	/*
	 * Increse the refresh token fail counter.
	 */
	private function inc_refresh_token_fail_counter( $config, $config_id ) {
		$connection_fail_count = ++$config->connection_fail_count;
		
		// Kill connection after 30 fail attempts
		if ( 30 < $connection_fail_count ) {
			self::clear_config( $config_id );
			return;
		}
		
		// Count how many times refresh token attempt is failed.
		update_post_meta( $config_id, '_pronamic_gateway_razorpay_connection_fail_count', $connection_fail_count );
	}

	public function subscription_status_update_listener( $subscription, $can_redirect, $previous_status, $updated_status ) {
		$config_id = $subscription->get_config_id();

		if ( empty( $config_id ) ) {
			return;
		}

		$gateway = $this->get_gateway( $config_id );

		$gateway->subscription_status_update( $subscription, $can_redirect, $previous_status, $updated_status );
	}
	
	/**
	 * Field Enabled Payment Methods.
	 *
	 * @param array<string, mixed> $field Field.
	 * @return void
	 */
	public function connection_status_box( $field ) {
		$config = reset( $field['callback'] )->config;
		
		if ( ! empty( $config->connected_at ) ) {
			$connected_at = new DateTime();
			$connected_at->setTimestamp( $config->connected_at );
		}
		$expire_date = new DateTime();
		$expire_date->setTimestamp( $config->expires_at );
		$renew_schedule_time = new DateTime();
		$renew_schedule_time->setTimestamp( wp_next_scheduled( 'knit_pay_razorpay_refresh_access_token', [ 'config_id' => $config->config_id ] ) );
		$access_token_info  = '<dl>';
		$access_token_info .= isset( $connected_at ) ? sprintf( '<dt><strong>Connected at:</strong></dt><dd>%s</dd>', $connected_at->format_i18n() ) : '';
		$access_token_info .= sprintf( '<dt><strong>Access Token Expiry Date:</strong></dt><dd>%s</dd>', $expire_date->format_i18n() );
		$access_token_info .= sprintf( '<dt><strong>Next Automatic Renewal Scheduled at:</strong></dt><dd>%s</dd>', $renew_schedule_time->format_i18n() );
		$access_token_info .= '</dl>';
		echo $access_token_info;
	}

	private function is_auth_basic_enabled() {
		return defined( 'KNIT_PAY_RAZORPAY_API' ) || 'razorpay-pro' === $this->get_id();
	}
}
