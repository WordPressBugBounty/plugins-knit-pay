<?php

namespace KnitPay\Gateways\Instamojo;

use Pronamic\WordPress\Pay\AbstractGatewayIntegration;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;

/**
 * Title: Instamojo Integration
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 1.0.0
 * @since   1.0.0
 */
class Integration extends AbstractGatewayIntegration {
	/**
	 * Construct Instamojo integration.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'id'            => 'instamojo',
				'name'          => 'Instamojo',
				'url'           => 'http://go.thearrangers.xyz/instamojo?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=',
				'product_url'   => 'http://go.thearrangers.xyz/instamojo?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=product-url',
				'dashboard_url' => 'http://go.thearrangers.xyz/instamojo?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=signup',
				'provider'      => 'instamojo',
				'supports'      => [
					'webhook',
					'webhook_log',
					'webhook_no_config',
				],
				// TODO:
				// 'manual_url'    => \__( 'http://go.thearrangers.xyz/instamojo', 'knit-pay-lang' ),
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

		\add_filter( 'pronamic_payment_provider_url_instamojo', [ $this, 'payment_provider_url' ], 10, 2 );
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

		return $config->client_id;
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

		if ( null === $transaction_id || PaymentStatus::SUCCESS !== $payment->get_status() ) {
			return $url;
		}

		$mode = 'test' === $payment->get_mode() ? 'test' : 'www';

		return \sprintf( 'https://%s.instamojo.com/payments/?query=%s', $mode, $transaction_id );
	}

	/**
	 * Get settings fields.
	 *
	 * @return array
	 */
	public function get_settings_fields() {
		$fields = [];

		// Steps to Integrate Instamojo.
		$fields[] = [
			'section'  => 'general',
			'type'     => 'custom',
			'title'    => 'Steps to Integrate Instamojo',
			'callback' => function () { 
				echo '<p>' . __(
					'Instamojo is a free Payment Gateway for 12,00,000+ Businesses in India. There is no setup or annual fee. Just pay a transaction fee of 2% + ₹3 for the transactions. Instamojo accepts Debit Cards, Credit Cards, Net Banking, UPI, Wallets, and EMI.',
					'knit-pay'
				) . '</p><p><strong>' . __( 'Steps to Integrate Instamojo' ) . '</strong></p>' .

				'<ol><li>Some features may not work with the old Instamojo account! We
                    recommend you create a new account. Sign up process will hardly
                    take 10-15 minutes.<br />
                    <br /> <a class="button button-primary" target="_blank" href="' . $this->get_url() . 'help-signup"
                     role="button"><strong>Sign Up on Instamojo Live</strong></a>
                    <a class="button button-primary" target="_blank" href="https://test.instamojo.com"
                     role="button"><strong>Sign Up on Instamojo Test</strong></a>
                    </li>
                    <br />
		    
                    <li>During signup, Instamojo will ask your PAN and Bank
                    account details, after filling these details, you will reach
                    Instamojo Dashboard.</li>
		    
                    <li>On the left-hand side menu, you will see the option "API &
						Plugins" click on this button.</li>
		    
                    <li>This plugin is based on Instamojo API v2.0, So it will not
                    work with API Key and Auth Token. For this plugin to work, you
                    will have to generate a Client ID and Client Secret. On the bottom
                    of the "API & Plugins" page, you will see Generate Credentials /
                    Create new Credentials button. Click on this button.</li>
		    
                    <li>Now choose a platform from the drop-down
                    menu. You can choose any of them, but we will recommend choosing
                    option "WooCommerce/WordPress"</li>
		    
                    <li>Copy "Client ID" & "Client Secret" and paste it in the
                    Knit Pay Configuration Page.</li>

                    <li>You don\'t need to select configuration mode in the Instamojo configuration. Knit Pay will automatically detect configuration mode (Test or Live).</li>
		    
                    <li>Fill "Instamojo Account Email Address" field.</li>
		    
					<li>Save the settings using the "Publish" or "Update" button on the configuration page.</li>

                    <li>After saving the settings, test the settings using the Test block on the bottom of the configuration page. If you are getting an error while test the payment, kindly re-check Keys and Mode and save them again before retry.</li>

                    <li>Visit the <strong>Advanced</strong> tab above to configure advance options.</li>

                    </ol>' .
					'For more details about Instamojo service and details about transactions, you need to access Instamojo dashboard. <br />
                    <a target="_blank" href="' . $this->get_url() . 'know-more">Access Instamojo</a>';
			},
		];

		// Client ID.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_instamojo_client_id',
			'title'    => __( 'Client ID', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( 'Client ID as mentioned in the Instamojo dashboard at the "API & Plugins" page.', 'knit-pay-lang' ),
			'required' => true,
		];

		// Client Secret.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_instamojo_client_secret',
			'title'    => __( 'Client Secret', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( 'Client Secret as mentioned in the Instamojo dashboard at the "API & Plugins" page.', 'knit-pay-lang' ),
			'required' => true,
		];

		// Registered Email Address.
		$fields[] = [
			'section'  => 'general',
			'filter'   => FILTER_SANITIZE_EMAIL,
			'meta_key' => '_pronamic_gateway_instamojo_email',
			'title'    => __( 'Instamojo Account Email Address', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( 'Email Address used for Instamojo Account.', 'knit-pay-lang' ),
			'required' => true,
		];

		// Get Discounted Price.
		$fields[] = [
			'section'     => 'general',
			'filter'      => FILTER_VALIDATE_BOOLEAN,
			'meta_key'    => '_pronamic_gateway_instamojo_get_discount',
			'title'       => __( 'Get Discounted Fees', 'knit-pay-lang' ),
			'type'        => 'checkbox',
			'description' => 'Knit Pay will try to activate discounted transaction fees on your Instamojo account. Discounts are available on a case-to-case basis.<br>Discounted transaction fees will get activated before the 10th of next month on eligible accounts.',
			'tooltip'     => __( 'Tick to show your interested in discounted transaction fees.', 'knit-pay-lang' ),
			'label'       => __( 'I am interested in discounted Instamojo transaction fees.', 'knit-pay-lang' ),
		];

		// Expire Old Pending Payments.
		$fields[] = [
			'section'     => 'advanced',
			'filter'      => FILTER_VALIDATE_BOOLEAN,
			'meta_key'    => '_pronamic_gateway_instamojo_expire_old_payments',
			'title'       => __( 'Expire Old Pending Payments', 'knit-pay-lang' ),
			'type'        => 'checkbox',
			'description' => 'If this option is enabled, 24 hours old pending payments will be marked as expired in Knit Pay.',
			'label'       => __( 'Mark old pending Payments as expired in Knit Pay.', 'knit-pay-lang' ),
			'default'     => true,
		];

		// Send SMS.
		$fields[] = [
			'section'  => 'advanced',
			'filter'   => FILTER_VALIDATE_BOOLEAN,
			'meta_key' => '_pronamic_gateway_instamojo_send_sms',
			'title'    => __( 'Send SMS', 'knit-pay-lang' ),
			'type'     => 'checkbox',
			'label'    => __( 'Send payment request link via sms.', 'knit-pay-lang' ),
		];

		// Send Email.
		$fields[] = [
			'section'  => 'advanced',
			'filter'   => FILTER_VALIDATE_BOOLEAN,
			'meta_key' => '_pronamic_gateway_instamojo_send_email',
			'title'    => __( 'Send Email', 'knit-pay-lang' ),
			'type'     => 'checkbox',
			'label'    => __( 'Send payment request link via email.', 'knit-pay-lang' ),
		];

		// Top Bar Mode.
		$fields[] = [
			'section'     => 'advanced',
			'meta_key'    => '_pronamic_gateway_instamojo_top_bar_mode',
			'title'       => __( 'Top Bar Mode', 'knit-pay-lang' ),
			'type'        => 'select',
			'classes'     => [ 'regular-text', 'code' ],
			'description' => 'Show/Hide the "Top Bar" and "Cancel Button" on the payment page. In many plugins, this option helps customers to go back and review the cart. We recommend choosing the "Show" option.',
			'options'     => [
				'show' => __( 'Show', 'knit-pay-lang' ),
				'hide' => __( 'Hide', 'knit-pay-lang' ),
			],
			'default'     => 'show',
		];

		// Return fields.
		return $fields;
	}

	public function get_config( $post_id ) {
		$config = new Config();

		$config->client_id           = $this->get_meta( $post_id, 'instamojo_client_id' );
		$config->client_secret       = $this->get_meta( $post_id, 'instamojo_client_secret' );
		$config->email               = $this->get_meta( $post_id, 'instamojo_email' );
		$config->get_discount        = $this->get_meta( $post_id, 'instamojo_get_discount' );
		$config->expire_old_payments = $this->get_meta( $post_id, 'instamojo_expire_old_payments' );
		$config->send_sms            = $this->get_meta( $post_id, 'instamojo_send_sms' );
		$config->send_email          = $this->get_meta( $post_id, 'instamojo_send_email' );
		$config->top_bar_mode        = $this->get_meta( $post_id, 'instamojo_top_bar_mode' );

		$config->mode = Gateway::MODE_LIVE;
		if ( str_starts_with( $config->client_id, 'test' ) ) {
			$config->mode = Gateway::MODE_TEST;
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
		
		$this->set_mode( $config->mode );
		$gateway->set_mode( $config->mode );
		$gateway->init( $config );
		
		return $gateway;
	}

	/**
	 * When the post is saved, saves our custom data.
	 *
	 * @param int $post_id The ID of the post being saved.
	 * @return void
	 */
	public function save_post( $post_id ) {
		$config = $this->get_config( $post_id );

		if ( ! empty( $config->email ) ) {

			if ( empty( $config->get_discount ) ) {
				$config->get_discount = 0;
			}

			// Update Get Discount Preference.
			$data                     = [];
			$data['emailAddress']     = $config->email;
			$data['entry.1021922804'] = home_url( '/' );
			$data['entry.497676257']  = $config->get_discount;
			// TODO: Remove it after 30 Sep 2024.
			wp_remote_post(
				'https://docs.google.com/forms/u/0/d/e/1FAIpQLSdC2LvXnpkB-Wl4ktyk8dEerqdg8enDTycNK2tufIe0AOwo1g/formResponse',
				[
					'body' => $data,
				]
			);

			// Make Instamojo connection and raise discount request.
			wp_remote_post(
				'https://instamojo-connect.knitpay.org/connect/',
				[
					'body' => wp_json_encode(
						[
							'email'            => $config->email,
							'request_discount' => strval( $config->get_discount ),
							'website'          => home_url( '/' ),
							'mode'             => $config->mode,
							'source'           => 'kp',
						]
					),
				]
			);
		}

	}
}
