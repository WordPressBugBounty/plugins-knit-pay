<?php

namespace KnitPay\Gateways\GoUrl;

use Pronamic\WordPress\Pay\AbstractGatewayIntegration;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;


/**
 * Title: GoUrl Integration
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.76.0.0
 * @since   8.76.0.0
 */
class Integration extends AbstractGatewayIntegration {
	/**
	 * Construct GoUrl integration.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'id'          => 'go-url',
				'name'        => 'GoUrl.io (Crypto-Currency Payment Gateway)',
				'url'         => 'http://go.thearrangers.xyz/gourl?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=',
				'product_url' => 'http://go.thearrangers.xyz/gourl?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=product-url',
				'provider'    => 'go-url',
			]
		);

		parent::__construct( $args );
	}

	/**
	 * Setup.
	 */
	public function setup() {
		add_action( 'cryptobox_after_new_payment', [ $this, 'cryptobox_after_new_payment' ], 10, 4 );
	}

	/**
	 * Get settings fields.
	 *
	 * @return array
	 */
	public function get_settings_fields() {
		$fields = [];

		// Introduction to GoUrl.
		$fields[] = [
			'section'     => 'general',
			'type'        => 'custom',
			'title'       => 'GoUrl.io is a Crypto-Currency Payment Gateway',
			'description' => 'GoUrl.io can be used to accept Bitcoin, BitcoinCash, BitcoinSV, Litecoin, Dash, Dogecoin, Speedcoin, Reddcoin, Potcoin, Feathercoin, Vertcoin, Peercoin, UniversalCurrency, MonetaryUnit payments.'
			. '<br /><br> <a class="button button-primary" target="_blank" href="' . $this->get_url() . 'help-signup"
		    role="button"><strong>Sign Up</strong></a>',
		];

		// Need Help?.
		$fields[] = [
			'section'     => 'general',
			'type'        => 'custom',
			'title'       => 'Need help with setup?',
			'description' => 'Contact us now and get free integration support.'
			. '<br /><br> <a class="button button-primary" target="_blank" href="https://www.knitpay.org/contact-us/"
		    role="button"><strong>Contact Us</strong></a>',
		];

		if ( ! class_exists( 'gourlclass' ) ) {
			$fields[] = [
				'section'  => 'general',
				'type'     => 'custom',
				'title'    => 'Prerequisite',
				'callback' => function () { 
					$gourl_parent_base = 'gourl-bitcoin-payment-gateway-paid-downloads-membership/gourl_wordpress.php';
					$plugin_link       = '<a href="https://wordpress.org/plugins/gourl-bitcoin-payment-gateway-paid-downloads-membership/" target="_blank">GoUrl Bitcoin Payment Gateway & Paid Downloads & Membership</a>';
					
					$error = '<h2>For Knit Pay to work with GoUrl, %s plugin is required. Please %s to continue!</h2>';
					
					$plugins = \get_plugins();
					if ( isset( $plugins[ $gourl_parent_base ] ) ) {
						$url  = esc_url( wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=' . $gourl_parent_base ), 'activate-plugin_' . $gourl_parent_base ) );
						$link = '<a href="' . $url . '" target="_blank">' . __( 'activate it', 'knit-pay-lang' ) . '</a>';
					} else {
						$url  = esc_url( wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=gourl-bitcoin-payment-gateway-paid-downloads-membership' ), 'install-plugin_gourl-bitcoin-payment-gateway-paid-downloads-membership' ) );
						$link = '<a href="' . $url . '" target="_blank">' . __( 'install it', 'knit-pay-lang' ) . '</a>';
					}
					
					$msg = '<p>' . sprintf( $error, $plugin_link, $link ) . '</p>';
					echo $msg;
				},
			];

			return $fields;
		}

		$fields[] = [
			'section'     => 'general',
			'type'        => 'custom',
			'title'       => 'GoUrl.io Settings',
			'description' => '<h1>Kindly visit the <a target="_blank" href="' . GOURL_ADMIN . GOURL . 'settings' . '">GoUrl Settings</a> page for the API and callback configuration.</h1>',
		];

		// Payment Status.
		$payment_statuses = [
			PaymentStatus::ON_HOLD => PaymentStatus::ON_HOLD,
			PaymentStatus::OPEN    => __( 'Pending', 'knit-pay-lang' ),
			PaymentStatus::SUCCESS => PaymentStatus::SUCCESS,
		];
		$fields[]         = [
			'section'     => 'general',
			'meta_key'    => '_pronamic_gateway_go_url_payment_received_status',
			'title'       => __( 'Payment Status - Cryptocoin Payment Received', 'knit-pay-lang' ),
			'type'        => 'select',
			'options'     => $payment_statuses,
			'default'     => PaymentStatus::OPEN,
			'description' => sprintf( __( "Payment is received successfully from the customer. You will see the bitcoin/altcoin payment statistics in one common table <a href='%s'>'All Payments'</a> with details of all received payments.<br>If you sell digital products / software downloads you can use the status 'Completed' showing that particular customer already has instant access to your digital products", 'knit-pay-lang' ), GOURL_ADMIN . GOURL . 'payments&s=knitpay' ),
		];
		$fields[]         = [
			'section'     => 'general',
			'meta_key'    => '_pronamic_gateway_go_url_payment_confirmed_status',
			'title'       => __( 'Payment Status - Previously Received Payment Confirmed', 'knit-pay-lang' ),
			'type'        => 'select',
			'options'     => $payment_statuses,
			'default'     => PaymentStatus::SUCCESS,
			'description' => __( 'About one hour after the payment is received, the bitcoin transaction should get 6 confirmations (for transactions using other cryptocoins ~ 20-30min).<br>A transaction confirmation is needed to prevent double spending of the same money', 'knit-pay-lang' ),
		];

		// Return fields.
		return $fields;
	}

	public function get_config( $post_id ) {
		$config = new Config();

		$config->payment_received_status  = $this->get_meta( $post_id, 'go_url_payment_received_status' );
		$config->payment_confirmed_status = $this->get_meta( $post_id, 'go_url_payment_confirmed_status' );

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

		$this->set_mode( Gateway::MODE_LIVE );
		$gateway->set_mode( Gateway::MODE_LIVE );
		$gateway->init( $config );

		return $gateway;
	}

	function cryptobox_after_new_payment( $user_id, $order_id, $payment_details, $box_status ) {
		if ( empty( $order_id ) ) {
			return false;
		}

		if ( ! in_array( $box_status, [ 'cryptobox_newrecord', 'cryptobox_updated' ] ) ) {
			return false;
		}

		if ( ! $user_id || $payment_details['status'] != 'payment_received' ) {
			return false;
		}

		$payment = get_pronamic_payment_by_transaction_id( $order_id );

		if ( null === $payment ) {
			exit;
		}

		// Add note.
		$note = sprintf(
			/* translators: %s: GoUrl */
			__( 'Webhook requested by %s.', 'knit-pay-lang' ),
			__( 'GoUrl', 'knit-pay-lang' )
		);

		$payment->add_note( $note );

		// Log webhook request.
		do_action( 'pronamic_pay_webhook_log_payment', $payment );

		$config  = $this->get_config( $payment->get_config_id() );
		$gateway = $this->get_gateway( $payment->get_config_id() );

		if ( $payment_details['is_confirmed'] ) {
			$payment->set_status( $config->payment_confirmed_status );
		} elseif ( $payment_details['is_paid'] ) {
			$payment->set_status( $config->payment_received_status );
		}

		$gateway->save_payment_details( $payment, $payment_details );
		$payment->save();
		return true;
	}
}
