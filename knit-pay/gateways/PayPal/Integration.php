<?php

namespace KnitPay\Gateways\PayPal;

use KnitPay\Gateways\IntegrationModeTrait;
use KnitPay\Gateways\IntegrationOAuthClient;
use KnitPay\Utils;


/**
 * Title: PayPal Integration
 * Copyright: 2020-2026 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.96.19.0
 * @since   8.94.0.0
 */
class Integration extends IntegrationOAuthClient {
	use IntegrationModeTrait;

	const PARTNER_ATTRIBUTION_ID = 'LogicBridgeTechnoMartLLP_SI';

	/**
	 * Construct PayPal integration.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'id'          => 'paypal',
				'name'        => 'PayPal',
				'url'         => 'http://go.thearrangers.xyz/paypal?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=',
				'product_url' => 'http://go.thearrangers.xyz/paypal?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=product-url',
				'provider'    => 'paypal',
				'supports'    => [
					'webhook',
					'webhook_log',
					'webhook_no_config',
				],
			]
		);

		parent::__construct( $args );

		// Actions.
		$function = [ __NAMESPACE__ . '\Webhook', 'listen' ];

		if ( ! has_action( 'wp_loaded', $function ) ) {
			add_action( 'wp_loaded', $function );
		}

		// Register AJAX endpoints used by the PayPal v6 SDK payment page.
		add_action( 'wp_ajax_knitpay_paypal_create_order', [ __NAMESPACE__ . '\Gateway', 'ajax_create_order' ] );
		add_action( 'wp_ajax_nopriv_knitpay_paypal_create_order', [ __NAMESPACE__ . '\Gateway', 'ajax_create_order' ] );
		add_action( 'wp_ajax_knitpay_paypal_capture_order', [ __NAMESPACE__ . '\Gateway', 'ajax_capture_order' ] );
		add_action( 'wp_ajax_nopriv_knitpay_paypal_capture_order', [ __NAMESPACE__ . '\Gateway', 'ajax_capture_order' ] );
	}

	/**
	 * Setup.
	 */
	public function setup() {
		parent::setup();

		// Add Partner ID.
		add_filter( 'http_request_args', [ $this, 'http_request_args' ], 1000, 2 ); // phpcs:ignore WordPressVIPMinimum.Hooks.RestrictedHooks.http_request_args -- Not changing timout.
		add_filter( 'wp_redirect', [ $this, 'filter_wp_redirect' ], 1000 );

		$this->auto_save_on_mode_change = true;
	}

	/**
	 * Add PayPal Partner ID in request header.
	 *
	 * @param array  $parsed_args Parsed arguments.
	 * @param string $url         URL.
	 * @return array
	 */
	public function http_request_args( $parsed_args, $url ) {
		if ( str_contains( $url, 'paypal.com' ) ) {
			$parsed_args['headers']['PayPal-Partner-Attribution-Id'] = self::PARTNER_ATTRIBUTION_ID;
		}

		return $parsed_args;
	}

	/**
	 * Append Partner ID as query args to PayPal redirect URLs for attribution.
	 *
	 * @param string $location Redirect URL.
	 * @return string
	 */
	public function filter_wp_redirect( $location ) {
		if ( str_contains( $location, 'paypal.com' ) ) {
			$location = add_query_arg(
				[
					'bn'      => self::PARTNER_ATTRIBUTION_ID,
					'at_code' => self::PARTNER_ATTRIBUTION_ID,
				],
				$location
			);
		}

		return $location;
	}

	/**
	 * Return merchant_id if available, otherwise fall back to the shorter client_id.
	 *
	 * @param string $display_value Display value.
	 * @param int    $post_id       Gateway configuration post ID.
	 * @return string
	 */
	public function gateway_configuration_display_value( $display_value, $post_id ) {
		$config = $this->get_config( $post_id );

		return empty( $config->merchant_id ) ? $config->client_id : $config->merchant_id;
	}

	protected function get_oauth_connect_button_fields( $fields ) {
		// Auto-save and reload the page when gateway changes to work around PayPal connection quirks.
		if ( isset( $_GET['gateway_id'] ) && 'paypal' === sanitize_text_field( wp_unslash( $_GET['gateway_id'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin settings page reload trigger, not a form submission. Admin is already protected by auth cookies.
			$fields[] = [
				'section'  => 'general',
				'type'     => 'custom',
				'callback' => function () {
					echo '<script>
						document.body.insertAdjacentHTML("beforeend", "<div id=\"loading\" style=\"position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255, 255, 255, 0.8); z-index: 9999; display: flex; align-items: center; justify-content: center;\"><div style=\"font-size: 24px;\">Loading...</div></div>");
						document.getElementById("publish").click();
					</script>';
				},
			];
			return $fields;
		}

		// Oauth Connect Description (inherited from parent).
		$fields[] = $this->get_oauth_connect_how_to_connect_field();

		// Connect.
		$fields[] = [
			'section'  => 'general',
			'type'     => 'custom',
			'callback' => function () {
				$admin_url     = admin_url();
				$auth_response = $this->init_oauth_connect( $this->config, $this->config->config_id, true );

				if ( ! $auth_response->success ) {
					if ( isset( $auth_response->data ) ) {
						echo 'Error: ' . esc_html( $auth_response->data->message );
						return;
					}
					echo 'Error: ' . esc_html( $auth_response->errors[0]->message );
					return;
				}

				$auth_response_data = $auth_response->data;
				$auth_url           = $auth_response_data->auth_url;
				$state              = $auth_response_data->state;
				$auth_url           = add_query_arg( [ 'displayMode' => 'minibrowser' ], $auth_url );

				echo '
				<a style="display: none;" target="_blank" data-paypal-onboard-complete="knitpayPaypalOnboardedCallback" href="' . esc_url( $auth_url ) . '" data-paypal-button="PPLtBlue">Connect with PayPal</a>
				<script>
					function knitpayPaypalOnboardedCallback(authCode, sharedId) {
						// Close login window.
						window.open("", "PPMiniWin").close();

						// Show loading.
						document.body.insertAdjacentHTML("beforeend", "<div id=\"loading\" style=\"position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255, 255, 255, 0.8); z-index: 9999; display: flex; align-items: center; justify-content: center;\"><div style=\"font-size: 24px;\">Loading...</div></div>");

						const admin_url = new URL("' . esc_url( $admin_url ) . '");
						admin_url.searchParams.append("knitpay_oauth_auth_status", "connected");
						admin_url.searchParams.append("code", authCode);
						admin_url.searchParams.append("shared_id", sharedId);
						admin_url.searchParams.append("state", "' . esc_attr( $state ) . '");
						admin_url.searchParams.append("gateway_id", ' . esc_attr( $this->config->config_id ) . ');
						admin_url.searchParams.append("gateway", "' . esc_attr( $this->get_id() ) . '");

						window.location.href = admin_url.toString();
					}

					// Show Connect Button after page load.
					window.addEventListener("load", function() {
						jQuery("a[data-paypal-onboard-complete]").removeAttr( "style" );
					});
				</script>
				<script id="paypal-js" src="https://www.paypal.com/webapps/merchantboarding/js/lib/lightbox/partner.js"></script>';
			},
		];

		return $fields;
	}

	/**
	 * Get settings fields.
	 *
	 * @return array
	 */
	protected function get_oauth_connection_status_fields( $fields ) {
		$fields = parent::get_oauth_connection_status_fields( $fields );

		// Merchant ID.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_paypal_merchant_id',
			'title'    => __( 'Merchant ID', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'readonly' => true,
		];

		// Client ID.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_paypal_client_id',
			'title'    => __( 'Client ID', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'readonly' => true,
		];

		// Client Secret.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_paypal_client_secret',
			'title'    => __( 'Client Secret', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'readonly' => true,
		];

		// Return fields.
		return $fields;
	}

	protected function show_common_setting_fields( $fields, $config ) {
		// Invoice Prefix.
		$fields[] = [
			'section'     => 'general',
			'meta_key'    => '_pronamic_gateway_paypal_invoice_prefix',
			'title'       => __( 'Invoice Prefix', 'knit-pay-lang' ),
			'type'        => 'text',
			'classes'     => [ 'regular-text' ],
			'default'     => preg_replace( '/[^A-Za-z]/', 'i', wp_generate_password( 6, false ) ) . '-',
			'description' => __( 'Add a unique prefix to invoice numbers for site-specific tracking (recommended).', 'knit-pay-lang' ),
		];

		// Test Buyer Country.
		if ( Gateway::MODE_TEST === $config->mode ) {
			$fields[] = [
				'section'     => 'general',
				'meta_key'    => '_pronamic_gateway_paypal_test_buyer_country',
				'title'       => __( 'Test Buyer Country', 'knit-pay-lang' ),
				'type'        => 'select',
				'classes'     => [ 'regular-text' ],
				'default'     => '',
				'options'     => [
					''   => __( 'Use address country (billing/shipping)', 'knit-pay-lang' ),
					'AU' => 'Australia (Afterpay, Pay Later, Zip)',
					'AT' => 'Austria (EPS, Trustly)',
					'BE' => 'Belgium (Bancontact)',
					'BR' => 'Brazil (Boleto Bancário, Pix International)',
					'CN' => 'China (Alipay, WeChat Pay)',
					'DK' => 'Denmark (Trustly)',
					'EE' => 'Estonia (Estonia Banks, Trustly)',
					'FI' => 'Finland (Trustly, Verkkopankki)',
					'FR' => 'France (Pay Later)',
					'DE' => 'Germany (Pay Later, Pay upon Invoice, Trustly)',
					'HK' => 'Hong Kong (Alipay, WeChat Pay)',
					'IN' => 'India',
					'ID' => 'Indonesia (Alfamart, DOKU, GoPay, Indomaret, Indonesia Banks, Jenius Pay, Kredivo, LinkAja, OVO)',
					'IT' => 'Italy (Bancomat Pay, MyBank, Pay Later, Satispay, Scalapay)',
					'JP' => 'Japan',
					'LV' => 'Latvia (Latvia Banks, Trustly)',
					'LT' => 'Lithuania (Lithuania Banks, Paysera, Trustly)',
					'MY' => 'Malaysia (FPX, GrabPay)',
					'MX' => 'Mexico (OXXO Pay)',
					'NL' => 'Netherlands (iDEAL, Trustly)',
					'NZ' => 'New Zealand (Afterpay)',
					'NO' => 'Norway (Trustly)',
					'PH' => 'Philippines (Dragonpay, GrabPay)',
					'PL' => 'Poland (BLIK, Przelewy24)',
					'PT' => 'Portugal (MB WAY, Multibanco)',
					'SG' => 'Singapore (GrabPay)',
					'KR' => 'South Korea',
					'ES' => 'Spain (Bizum, Pay Later, Trustly)',
					'SE' => 'Sweden (Swish, Trustly)',
					'CH' => 'Switzerland (TWINT)',
					'TW' => 'Taiwan',
					'TH' => 'Thailand (Thailand Banks)',
					'GB' => 'United Kingdom (Pay Later, PayPal Credit, Trustly)',
					'US' => 'United States (Apple Pay, Bank ACH, Google Pay, Pay Later, PayPal Credit, Venmo)',
					'VN' => 'Vietnam',
				],
				'description' => __( 'Simulate a buyer from this country in sandbox mode. Choose a specific country to test region-locked APMs (iDEAL, BLIK, Bancontact, etc.). Select "Use address country" to auto-detect from the order\'s billing/shipping address. Falls back to browser-based detection if no address is available.', 'knit-pay-lang' ),
			];
		}

		// Auto Webhook Setup Supported.
		$fields[] = [
			'section'     => 'feedback',
			'title'       => __( 'Auto Webhook Setup Supported', 'knit-pay-lang' ),
			'type'        => 'description',
			'description' => 'Knit Pay automatically creates webhook configuration in PayPal Dashboard as soon as PayPal configuration is published or saved. Kindly raise the Knit Pay support ticket or configure the webhook manually if the automatic webhook setup fails.',
		];

		// Webhook URL.
		$fields[] = [
			'section'  => 'feedback',
			'title'    => \__( 'Webhook URL', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'large-text', 'code' ],
			'value'    => add_query_arg( 'kp_paypal_webhook', $config->config_id, home_url( '/' ) ),
			'readonly' => true,
			'tooltip'  => sprintf(
				/* translators: %s: PayPal */
				__(
					'Copy the Webhook URL to the %s dashboard to receive automatic transaction status updates.',
					'knit-pay-lang'
				),
				__( 'PayPal', 'knit-pay-lang' )
			),
		];

		$fields[] = [
			'section'     => 'feedback',
			'title'       => \__( 'Supported Events', 'knit-pay-lang' ),
			'type'        => 'description',
			'description' => 'CHECKOUT.ORDER.APPROVED',
		];

		return $fields;
	}

	public function get_child_config( $post_id ) {
		$config = new Config();

		$config->client_id          = $this->get_meta( $post_id, 'paypal_client_id' );
		$config->client_secret      = $this->get_meta( $post_id, 'paypal_client_secret' );
		$config->invoice_prefix     = $this->get_meta( $post_id, 'paypal_invoice_prefix' );
		$config->webhook_id         = $this->get_meta( $post_id, 'paypal_webhook_id' );
		$config->test_buyer_country = $this->get_meta( $post_id, 'paypal_test_buyer_country' );

		// OAuth.
		$config->merchant_id = $this->get_meta( $post_id, 'paypal_merchant_id' );

		$config->mode = $this->get_meta( $post_id, 'mode' );

		return $config;
	}

	public function clear_child_config( $config_id ) {
		delete_post_meta( $config_id, '_pronamic_gateway_' . $this->get_id() . '_client_id' );
		delete_post_meta( $config_id, '_pronamic_gateway_' . $this->get_id() . '_client_secret' );
	}

	protected function is_oauth_connected( $config ) {
		return ! empty( $config->client_secret );
	}

	protected function get_oauth_token_request_body( $oauth_token_request_body ) {
		$oauth_token_request_body['shared_id'] = isset( $_GET['shared_id'] ) ? sanitize_text_field( wp_unslash( $_GET['shared_id'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth callback from PayPal servers; nonce not applicable. CSRF protection via state parameter.

		return $oauth_token_request_body;
	}

	protected function save_token( $gateway_id, $token_data, $new_connection = false ) {
		if ( ! ( isset( $token_data->success ) && $token_data->success ) ) {
			return;
		}

		$token_data = $token_data->data;

		$token_data->is_connected = true;
		$token_data->merchant_id  = $token_data->payer_id;

		if ( $new_connection ) {
			$token_data->connected_at = time();
		}

		foreach ( $token_data as $key => $value ) {
			update_post_meta( $gateway_id, '_pronamic_gateway_' . $this->get_id() . '_' . $key, $value );
		}
	}

	/**
	 * Save post.
	 *
	 * @param int $config_id The ID of the post being saved.
	 * @return void
	 */
	public function save_post( $config_id ) {
		// Clear Keys if connected and disconnect action is initiated.
		if ( filter_has_var( INPUT_POST, 'knit_pay_oauth_client_disconnect' ) ) {
			self::clear_config( $config_id );
			return;
		}

		$this->configure_webhook( $config_id );
	}

	protected function configure_webhook( $config_id ) {
		$webhook = new Webhook( $config_id, $this->get_config( $config_id ) );
		$webhook->configure_webhook();
	}
}
