<?php

namespace KnitPay\Gateways\Razorpay;

use KnitPay\Gateways\IntegrationOAuthClient;
use Pronamic\WordPress\Pay\Core\IntegrationModeTrait;
use Pronamic\WordPress\Pay\Payments\Payment;
use KnitPay\Utils as KnitPayUtils;


/**
 * Title: Razorpay Integration
 * Copyright: 2020-2026 Knit Pay
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

		// Uploads the invoices to Razorpay for Import Flow.
		InvoiceUploader::get_instance();

		// Enqueue media uploader scripts for admin.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_media_uploader_scripts' ] );
	}

	/**
	 * Enqueue media uploader scripts.
	 */
	public function enqueue_media_uploader_scripts( $hook ) {
		// Only load on post edit screen for pronamic_gateway post type.
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ] ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'pronamic_gateway' !== $screen->post_type ) {
			return;
		}

		// Enqueue WordPress media scripts.
		wp_enqueue_media();

		// Get plugin URL for Razorpay gateway directory.
		$plugin_url = plugins_url( '', __FILE__ );

		// Enqueue custom JavaScript for media uploader.
		wp_enqueue_script(
			'razorpay-media-uploader',
			$plugin_url . '/js/media-uploader.js',
			[ 'jquery', 'media-upload', 'media-views' ],
			KNITPAY_VERSION,
			true
		);

		// Add custom CSS for the media uploader buttons.
		wp_add_inline_style(
			'buttons',
			'
			.razorpay-media-buttons {
				display: inline-block;
				margin-left: 10px;
				vertical-align: middle;
			}
			.razorpay-media-buttons .button {
				margin-right: 5px;
			}
			#razorpay_checkout_image_preview {
				display: block;
				margin-top: 10px;
			}
			#razorpay_checkout_image_preview img {
				display: block;
			}
			'
		);
	}

	/**
	 * Render checkout image field with media uploader button.
	 */
	public function render_checkout_image_field() {
		?>
		<div class="razorpay-media-buttons">
			<button type="button" class="button button-primary" id="razorpay_checkout_image_upload_button">
				<?php esc_html_e( 'Upload/Select Image', 'knit-pay-lang' ); ?>
			</button>
			<button type="button" class="button button-secondary" id="razorpay_checkout_image_remove_button">
				<?php esc_html_e( 'Remove Image', 'knit-pay-lang' ); ?>
			</button>
		</div>
		<div id="razorpay_checkout_image_preview"></div>
		<?php
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

		$config              = $this->get_config( $payment->get_config_id() );
		$dashboard_order_url = 'https://dashboard.razorpay.com/app/orders/%s';
		if ( 'my' === $config->country ) {
			$dashboard_order_url = 'https://dashboard.curlec.com/app/orders/%s';
		}
		return \sprintf( $dashboard_order_url, $payment->get_meta( 'razorpay_order_id' ) );
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

	protected function show_common_setting_fields( $fields, $config ) {
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
			'classes'  => [ 'regular-text', 'code' ],
			'callback' => [ $this, 'render_checkout_image_field' ],
			'tooltip'  => __( 'Link to an image (usually your business logo) shown in the Checkout form. Can also be a base64 string, if loading the image from a network is not desirable. Keep it blank to use default image.', 'knit-pay-lang' ),
		];

		// Checkout Mode.
		if ( count( $checkout_modes_options ) > 1 ) {
			$fields[] = [
				'section'  => 'general',
				'filter'   => FILTER_SANITIZE_NUMBER_INT,
				'meta_key' => '_pronamic_gateway_razorpay_checkout_mode',
				'title'    => __( 'Checkout Mode', 'knit-pay-lang' ),
				'type'     => 'select',
				'options'  => $checkout_modes_options,
				'default'  => Config::CHECKOUT_STANDARD_MODE,
			];
		}

		// Country.
		$supported_countries = [
			'in'             => 'India',
			'in-import-flow' => 'Non-Indian (Import flow)',
		];
		if ( $this->is_auth_basic_enabled() ) {
			$supported_countries['my'] = 'Malaysia (Curlec)';
			$supported_countries['sg'] = 'Singapore';
			$supported_countries['us'] = 'United States of America';
		}
		$fields[] = [
			'section'     => 'general',
			'meta_key'    => '_pronamic_gateway_razorpay_country',
			'title'       => __( 'Country', 'knit-pay-lang' ),
			'type'        => 'select',
			'options'     => $supported_countries,
			'default'     => 'in',
			'description' => __( 'Import Flow is a payment solution designed for International (non-Indian) businesses to accept payments from Indian customers without any additional paperwork or registration.', 'knit-pay-lang' ),
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

		if ( 'in-import-flow' === $config->country ) {
			$fields[] = [
				'section' => 'advanced',
				'type'    => 'blank',
			];
			$fields[] = [
				'section' => 'advanced',
				'type'    => 'description',
				'title'   => __( 'SFTP Invoice Uploading', 'knit-pay-lang' ),
			];

			if ( InvoiceUploader::phpseclib_exists() ) {
				// Server Public IP.
				$fields[] = [
					'section'     => 'advanced',
					'title'       => __( 'Server Public IP', 'knit-pay-lang' ),
					'type'        => 'description',
					'description' => KnitPayUtils::get_server_public_ip(),
				];

				// Transaction Fees Percentage.
				$fields[] = [
					'section'     => 'advanced',
					'meta_key'    => '_pronamic_gateway_razorpay_sftp_username',
					'title'       => __( 'SFTP Username', 'knit-pay-lang' ),
					'type'        => 'text',
					'classes'     => [ 'regular-text', 'code' ],
					'description' => __( 'SFTP Server username shared by Razorpay.', 'knit-pay-lang' ),
				];

				// Transaction Fees Percentage.
				$fields[] = [
					'section'     => 'advanced',
					'meta_key'    => '_pronamic_gateway_razorpay_sftp_public_key',
					'title'       => __( 'SFTP Public Key', 'knit-pay-lang' ),
					'type'        => 'textarea',
					'classes'     => [ 'regular-text', 'code' ],
					'readonly'    => true,
					'description' => __( 'Public key of the SFTP server.', 'knit-pay-lang' ),
				];
			} else {
				$fields[] = [
					'section'  => 'advanced',
					'title'    => __( 'Missing Dependency', 'knit-pay-lang' ),
					'type'     => 'description',
					'callback' => function () {
						$message = "Knit Pay requires the phpseclib3 library to automatically upload invoices to Razorpay's SFTP server. Kindly install any plugin that contains the phpseclib3 library. A List of some of the plugins is given below. Even if you keep any of these plugins inactive, Knit Pay will work.
						<ol>
						<li><a target='_blank' href='" . admin_url( 'plugin-install.php?s=terrafrost&tab=search&type=author' ) . "'>SSH SFTP Updater Support</a></li>
						<li><a target='_blank' href='" . admin_url( 'plugin-install.php?s=databasebackup&tab=search&type=author' ) . "'>WP Database Backup</a></li>
						<li><a target='_blank' href='" . admin_url( 'plugin-install.php?s=BackWPup&tab=search&type=author' ) . "'>BackWPup</a></li>
						<li><a target='_blank' href='" . admin_url( 'plugin-install.php?s=Total%2520Upkeep%2520boldgrid&tab=search&type=term' ) . "'>Total Upkeep</a></li>
						<li><a target='_blank' href='" . admin_url( 'plugin-install.php?s=InstaWP%2520Connect%2520WP%2520Staging%2520Migration&tab=search&type=term' ) . "'>InstaWP Connect</a></li>
						<li><a target='_blank' href='" . admin_url( 'plugin-install.php?s=Wp%2520Social%2520Login%2520and%2520Register%2520Social%2520Counter%2520roxnor&tab=search&type=term' ) . "'>Wp Social</a></li>
						</ol>";

						echo $message;
					},
				];
			}
		}

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
					'knit-pay-lang'
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
				'knit-pay-lang'
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
		$config->expires_at                  = $this->get_meta( $post_id, 'razorpay_expires_at' );
		$config->access_token                = $this->get_meta( $post_id, 'razorpay_access_token' );
		$config->refresh_token               = $this->get_meta( $post_id, 'razorpay_refresh_token' );
		$config->country                     = $this->get_meta( $post_id, 'razorpay_country' );
		$config->company_name                = $this->get_meta( $post_id, 'razorpay_company_name' );
		$config->checkout_image              = KnitPayUtils::convert_relative_path_to_url( $this->get_meta( $post_id, 'razorpay_checkout_image' ) );
		$config->checkout_mode               = $this->get_meta( $post_id, 'razorpay_checkout_mode' );
		$config->transaction_fees_percentage = $this->get_meta( $post_id, 'razorpay_transaction_fees_percentage' );
		$config->transaction_fees_fix        = $this->get_meta( $post_id, 'razorpay_transaction_fees_fix' );
		$config->merchant_id                 = $this->get_meta( $post_id, 'razorpay_merchant_id' );
		$config->connection_fail_count       = $this->get_meta( $post_id, 'razorpay_connection_fail_count' );
		$config->expire_old_payments         = $this->get_meta( $post_id, 'razorpay_expire_old_payments' );
		$config->mode                        = $this->get_meta( $post_id, 'mode' );

		if ( 'in-import-flow' === $config->country ) {
			// SFTP Configurations.
			$config->sftp_private_key = $this->get_meta( $post_id, 'razorpay_sftp_private_key' );
			$config->sftp_public_key  = $this->get_meta( $post_id, 'razorpay_sftp_public_key' );
			$config->sftp_username    = $this->get_meta( $post_id, 'razorpay_sftp_username' );
		}

		if ( empty( $config->checkout_mode ) ) {
			$config->checkout_mode = Config::CHECKOUT_STANDARD_MODE;
		}
		$config->checkout_mode = (int) $config->checkout_mode;

		if ( empty( $config->country ) ) {
			$config->country = 'in';
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

		return $config;
	}

	/**
	 * Get gateway.
	 *
	 * @param int $post_id Post ID.
	 * @return Gateway
	 */
	public function get_gateway( $config_id ) {
		/** @var Config $config */
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
	}

	public function update_connection_status() {
		if ( ! isset( $_GET['gateway_id'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		//phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$gateway_id = sanitize_text_field( $_GET['gateway_id'] );

		// Don't interfere if rzp-wppcommerce attempting to connect.
		if ( 'rzp-woocommerce' === $gateway_id ) {
			return;
		}

		parent::update_connection_status();
	}

	protected function configure_webhook( $config_id ) {
		/** @var Config $config */
		$config = $this->get_config( $config_id );

		$webhook = new Webhook( $config_id, $config );
		$webhook->configure_webhook();
	}

	protected function save_child_config( $config_id ) {
		/** @var Config $config */
		$config = $this->get_config( $config_id );

		if ( 'in-import-flow' === $config->country && empty( $config->sftp_private_key ) ) {
			$this->generate_ssh_key_pair( $config_id );
		}
	}

	private function generate_ssh_key_pair( $config_id ) {
		// Don't generate keys if phpseclib is not available.
		if ( ! InvoiceUploader::phpseclib_exists() ) {
			return;
		}

		// Generate SSH key pair for SFTP connection using phpseclib.
		try {
			// Generate RSA 2048-bit key pair (optimal balance of compatibility, security, and speed).
			// RSA 2048-bit is universally supported across all SFTP servers and sufficient for SFTP authentication.
			$private = \phpseclib3\Crypt\RSA::createKey( 2048 );

			// Export private key in OpenSSH format for modern compatibility.
			$sftp_private_key = $private->toString( 'OpenSSH' );

			// Export public key in OpenSSH format (standard format: ssh-rsa AAAAB3NzaC1yc2EA...).
			$sftp_public_key = $private->getPublicKey()->toString( 'OpenSSH' );

			// Store generated keys.
			update_post_meta( $config_id, '_pronamic_gateway_' . $this->snake_case_id . '_sftp_private_key', $sftp_private_key );
			update_post_meta( $config_id, '_pronamic_gateway_' . $this->snake_case_id . '_sftp_public_key', $sftp_public_key );
		} catch ( \Exception $e ) {
			// Log error but don't fail the entire save operation.
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Knit Pay - Failed to generate SFTP SSH keys for config ' . $config_id . ': ' . $e->getMessage() );
		}
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
