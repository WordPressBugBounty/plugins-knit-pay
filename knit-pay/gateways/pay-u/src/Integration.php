<?php
namespace KnitPay\Gateways\PayU;

use Pronamic\WordPress\Html\Element;
use Pronamic\WordPress\Pay\AbstractGatewayIntegration;
use Pronamic\WordPress\Pay\Core\IntegrationModeTrait;
use WP_Query;

/**
 * Title: PayU Integration
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 5.4.0
 * @since 5.4.0
 */
class Integration extends AbstractGatewayIntegration {
	use IntegrationModeTrait;

	const KNIT_PAY_PAYU_CONNECT_PLATFORM_URL = 'https://payu-connect.knitpay.org/';

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
				'url'           => 'https://www.knitpay.org/integrate-payu-payment-gateway-with-various-wordpress-plugins/?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=',
				'product_url'   => 'http://go.thearrangers.xyz/payu?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=product-url',
				'dashboard_url' => 'http://go.thearrangers.xyz/payu?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=dashboard-url',
				'provider'      => 'pay-u',
				'supports'      => [
					'webhook',
					'webhook_log',
					'webhook_no_config',
				],
				// 'manual_url' => \__( 'http://go.thearrangers.xyz/payu', 'knit-pay-lang' ),
			]
		);

		add_action( 'wp_ajax_knitpay_payu_send_otp', [ $this, 'ajax_send_otp' ] );

		parent::__construct( $args );

		// Actions.
		$function = [ __NAMESPACE__ . '\Listener', 'listen' ];

		if ( ! has_action( 'wp_loaded', $function ) ) {
			add_action( 'wp_loaded', $function );
		}

		// Show notice if Knit Pay Pro is not installed.
		add_action( 'admin_notices', [ $this, 'payu_missing_knit_pay_pro' ] );
	}

	/**
	 * Admin notices.
	 *
	 * @return void
	 */
	public function payu_missing_knit_pay_pro() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Don't show message if Knit Pay Pro is already installed.
		if ( defined( 'KNIT_PAY_PRO' ) ) {
			return;
		}

		$config_ids = get_transient( 'knit_pay_payu_missing_knit_pay_pro_configs' );

		if ( empty( $config_ids ) ) {
			// Get gateways for which a API keys getting used.
			$query = new WP_Query(
				[
					'post_type'   => 'pronamic_gateway',
					'orderby'     => 'post_title',
					'order'       => 'ASC',
					'fields'      => 'ids',
					'nopaging'    => true,
					'post_status' => 'publish',
					'meta_query'  => [
						'relation' => 'OR',
						[
							'key'     => '_pronamic_gateway_id',
							'value'   => 'pay-u',
							'compare' => '=',
						],
						[
							'key'     => '_pronamic_gateway_id',
							'value'   => 'payumoney',
							'compare' => '=',
						],
					],
				]
			);

			$config_ids = $query->posts;
			if ( empty( $config_ids ) ) {
				$config_ids = true;
			}

			set_transient( 'knit_pay_payu_missing_knit_pay_pro_configs', $config_ids, DAY_IN_SECONDS );
		}

		if ( ! empty( $config_ids ) ) {
			require_once __DIR__ . '/views/notice-missing-knit-pay-pro.php';
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

		if ( ! defined( 'KNIT_PAY_PAYU_BIZ_API' ) ) {
			// Registered Phone.
			$fields[] = [
				'section'  => 'general',
				'title'    => __( 'Registered Phone Number', 'knit-pay-lang' ),
				'type'     => 'description',
				'callback' => [ $this, 'field_send_phone_otp' ],
				'tooltip'  => __( 'Phone number registered at PayU.', 'knit-pay-lang' ),
			];

			// Registered Email.
			$fields[] = [
				'section'  => 'general',
				'title'    => __( 'Registered Email', 'knit-pay-lang' ),
				'type'     => 'description',
				'callback' => [ $this, 'field_send_email_otp' ],
				'tooltip'  => __( 'Email address registered at PayU.', 'knit-pay-lang' ),
			];

			// Submit OTP.
			$fields[] = [
				'section'  => 'general',
				'meta_key' => '_pronamic_gateway_payu_otp',
				'title'    => __( 'OTP', 'knit-pay-lang' ),
				'type'     => 'description',
				'callback' => [ $this, 'field_submit_otp' ],
			];

			// PayU Automation description.
			$fields[] = [
				'section'     => 'general',
				'type'        => 'custom',
				'description' => __( 'Merchant Keys and Salt will be saved automatically on successful OTP verification.', 'knit-pay-lang' ),
			];
		}

		// Merchant ID
		$fields[] = [
			'section'     => 'general',
			'filter'      => FILTER_SANITIZE_NUMBER_INT,
			'meta_key'    => '_pronamic_gateway_payu_mid',
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

		if ( ! defined( 'KNIT_PAY_PAYU_BIZ_API' ) ) {
			// Load admin.js Javascript
			$fields[] = [
				'section'  => 'general',
				'type'     => 'custom',
				'callback' => function () {
					echo '<script src="' . plugins_url( '', __FILE__ ) . '/js/admin.js"></script>';},
			];
		}

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

	/**
	 * Field Enabled Payment Methods.
	 *
	 * @param array<string, mixed> $field Field.
	 * @return void
	 */
	public function field_send_phone_otp( $field ) {
		$classes = [
			'pronamic-pay-form-control',
		];

		$config_id = (int) \get_the_ID();

		$attributes['id']    = '_pronamic_gateway_payu_phone';
		$attributes['name']  = $attributes['id'];
		$attributes['type']  = 'tel';
		$attributes['class'] = implode( ' ', $classes );
		$attributes['value'] = $this->get_meta( $config_id, 'payu_phone' );

		$element = new Element( 'input', $attributes );

		$element->output();

		echo '<a id="payu-send-phone-otp" class="button button-primary"
		                  role="button" style="font-size: 21px;float: right;margin-right: 50px;">Click to Send OTP</a>';

		printf( '<br>Enter 10 digit Phone number registered at PayU.' );
	}

	/**
	 * Field Enabled Payment Methods.
	 *
	 * @param array<string, mixed> $field Field.
	 * @return void
	 */
	public function field_send_email_otp( $field ) {
		$classes = [
			'pronamic-pay-form-control',
		];

		$config_id = (int) \get_the_ID();

		$attributes['id']    = '_pronamic_gateway_payu_email';
		$attributes['name']  = $attributes['id'];
		$attributes['type']  = 'email';
		$attributes['class'] = implode( ' ', $classes );
		$attributes['value'] = $this->get_meta( $config_id, 'payu_email' );

		$element = new Element( 'input', $attributes );

		$element->output();

		echo '<a id="payu-send-email-otp" class="button button-primary"
		                  role="button" style="font-size: 21px;float: right;margin-right: 50px;">Click to Send OTP</a>';

		printf( '<br>Enter Email Address registered at PayU.' );
	}

	/**
	 * Field Enabled Payment Methods.
	 *
	 * @param array<string, mixed> $field Field.
	 * @return void
	 */
	public function field_submit_otp( $field ) {
		$classes = [
			'pronamic-pay-form-control',
		];

		$attributes['id']    = '_pronamic_gateway_payu_otp';
		$attributes['name']  = $attributes['id'];
		$attributes['type']  = 'number';
		$attributes['class'] = implode( ' ', $classes );

		$element = new Element( 'input', $attributes );

		$element->output();

		echo '<a id="payu-submit-otp" class="button button-primary"
		                  role="button" style="font-size: 21px;float: right;margin-right: 50px;">Submit OTP</a>';

		printf( '<br>Enter OTP received on registered phone number or email.' );
	}

	public function get_config( $post_id ) {
		$config = new Config();

		$config->mid                         = $this->get_meta( $post_id, 'payu_mid' );
		$config->merchant_key                = $this->get_meta( $post_id, 'payu_merchant_key' );
		$config->merchant_salt               = $this->get_meta( $post_id, 'payu_merchant_salt' );
		$config->transaction_fees_percentage = $this->get_meta( $post_id, 'payu_transaction_fees_percentage' );
		$config->transaction_fees_fix        = $this->get_meta( $post_id, 'payu_transaction_fees_fix' );
		$config->is_connected                = $this->get_meta( $post_id, 'payu_is_connected' );

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
	 * Save post.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save_post( $config_id ) {
		// Delete and recheck PayU configurations for missing Knit Pay Pro.
		delete_transient( 'knit_pay_payu_missing_knit_pay_pro_configs' );

		// Don't connect if Knit Pay Pro is activated.
		if ( defined( 'KNIT_PAY_PRO' ) ) {
			return;
		}

		if ( defined( 'KNIT_PAY_PAYU_BIZ_API' ) ) {
			$mid = filter_input( INPUT_POST, '_pronamic_gateway_payu_mid', FILTER_SANITIZE_STRING );
			update_post_meta( $config_id, '_pronamic_gateway_payu_is_connected', false );
			if ( ! empty( $mid ) ) {
				$config = $this->get_config( $config_id );
				$this->verify_merchant( $config );
				update_post_meta( $config_id, '_pronamic_gateway_payu_is_connected', true );
			}
			return;
		}

		$otp   = filter_input( INPUT_POST, '_pronamic_gateway_payu_otp', FILTER_SANITIZE_NUMBER_INT );
		$email = filter_input( INPUT_POST, '_pronamic_gateway_payu_email', FILTER_SANITIZE_EMAIL );
		$phone = filter_input( INPUT_POST, '_pronamic_gateway_payu_phone', FILTER_SANITIZE_NUMBER_INT );
		if ( empty( $phone ) ) {
			$phone = '';
		}
		if ( empty( $email ) ) {
			$email = '';
		}
		update_post_meta( $config_id, '_pronamic_gateway_payu_phone', $phone );
		update_post_meta( $config_id, '_pronamic_gateway_payu_email', $email );

		// Connect if Phone, Email and OTP are available.
		if ( ! ( empty( $otp ) || empty( $email ) || empty( $phone ) ) ) {
			$this->clear_config( $config_id );
			$config = $this->get_config( $config_id );

			$response = wp_remote_post(
				self::KNIT_PAY_PAYU_CONNECT_PLATFORM_URL,
				[
					'body'    => [
						'action'   => 'get-keys',
						'mode'     => $config->mode,
						'phone'    => $phone,
						'email'    => $email,
						'otp'      => $otp,
						'home_url' => rawurlencode( home_url( '/' ) ),
					],
					'timeout' => 60,
				]
			);
			$result   = wp_remote_retrieve_body( $response );
			$result   = json_decode( $result, true );

			if ( isset( $result['mid'] ) ) {
				update_post_meta( $config_id, '_pronamic_gateway_payu_mid', $result['mid'] );
				update_post_meta( $config_id, '_pronamic_gateway_payu_merchant_uuid', $result['merchant_uuid'] );
				update_post_meta( $config_id, '_pronamic_gateway_payu_merchant_key', $result['credentials']['prod_key'] );
				update_post_meta( $config_id, '_pronamic_gateway_payu_merchant_salt', $result['credentials']['prod_salt'] );
				update_post_meta( $config_id, '_pronamic_gateway_payu_is_connected', true );
			}
		}
	}

	private static function clear_config( $config_id ) {
		delete_post_meta( $config_id, '_pronamic_gateway_payu_mid' );
		delete_post_meta( $config_id, '_pronamic_gateway_payu_merchant_uuid' );
		delete_post_meta( $config_id, '_pronamic_gateway_payu_merchant_key' );
		delete_post_meta( $config_id, '_pronamic_gateway_payu_merchant_salt' );
		delete_post_meta( $config_id, '_pronamic_gateway_payu_is_connected' );
	}

	public function ajax_send_otp() {
		$mode     = filter_input( INPUT_POST, 'mode', FILTER_SANITIZE_STRING );
		$identity = filter_input( INPUT_POST, 'identity', FILTER_SANITIZE_STRING );
		$channel  = filter_input( INPUT_POST, 'channel', FILTER_SANITIZE_STRING );

		$response = wp_remote_post(
			self::KNIT_PAY_PAYU_CONNECT_PLATFORM_URL,
			[
				'body'    => [
					'action'   => 'send-otp',
					'mode'     => $mode,
					'identity' => $identity,
					'channel'  => $channel,
				],
				'timeout' => 10,
			]
		);
		$result   = wp_remote_retrieve_body( $response );
		echo $result;
		wp_die();
	}

	private function verify_merchant( $config ) {
		$knit_pay_uuid = '5336-24d0-901942b0-b4ab-7ab3d199dfd2';

		$data     = "$knit_pay_uuid|payumoney|$config->mid|$config->merchant_key|$config->merchant_salt";
		$checksum = hash( 'sha512', $data );

		wp_remote_post(
			self::KNIT_PAY_PAYU_CONNECT_PLATFORM_URL,
			[
				'body'    => [
					'action'       => 'verify-merchant',
					'mode'         => $config->mode,
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
