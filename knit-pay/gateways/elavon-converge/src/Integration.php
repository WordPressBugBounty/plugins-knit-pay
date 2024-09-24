<?php

namespace KnitPay\Gateways\ElavonConverge;

use Pronamic\WordPress\Pay\AbstractGatewayIntegration;
use Pronamic\WordPress\Pay\Core\IntegrationModeTrait;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;

/**
 * Title: Elavon Converge Integration
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 1.0.0
 * @since   4.3.0
 */
class Integration extends AbstractGatewayIntegration {
	use IntegrationModeTrait;
	/**
	 * Construct Elavon Converge integration.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'id'          => 'elavon-converge',
				'name'        => 'Elavon - Converge',
				'product_url' => '',
				'provider'    => 'elavon-converge',
			]
		);

		parent::__construct( $args );
	}

	/**
	 * Setup.
	 */
	public function setup() {
		\add_filter(
			'pronamic_gateway_configuration_display_value_' . $this->get_id(),
			[ $this, 'gateway_configuration_display_value' ],
			10,
			2
		);

		// Payment Redirect Listener.
		$function = [ __NAMESPACE__ . '\Integration', 'payment_redirect_listener' ];
		if ( ! has_action( 'wp_loaded', $function ) ) {
			add_action( 'wp_loaded', $function );
		}
	}

	public static function payment_redirect_listener() {
		if ( ! ( filter_has_var( INPUT_GET, 'kp_elavon_converge_redirect' ) ) ) {
			return;
		}

		$ssl_invoice_number = filter_input( INPUT_POST, 'ssl_invoice_number', FILTER_SANITIZE_STRING );
		$error_message      = filter_input( INPUT_POST, 'errorMessage', FILTER_SANITIZE_STRING );

		$payment = get_pronamic_payment_by_transaction_id( $ssl_invoice_number );

		if ( ! isset( $payment ) ) {
			return;
		}

		if ( ! empty( $error_message ) ) {
			$payment->add_note( 'Error: ' . $error_message );
			$payment->set_status( PaymentStatus::FAILURE );
			$payment->save();
			return;
		}

		$ssl_txn_id = filter_input( INPUT_POST, 'ssl_txn_id', FILTER_SANITIZE_STRING );
		$return_url = add_query_arg(
			[
				'ssl_txn_id' => $ssl_txn_id,
			],
			$payment->get_return_url()
		);

		// Redirect to Return URL.
		wp_safe_redirect( $return_url );
		exit;
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

		return $config->merchant_id;
	}

	/**
	 * Get settings fields.
	 *
	 * @return array
	 */
	public function get_settings_fields() {
		$fields = [];
		
		// Get mode from Integration mode trait.
		$fields[] = $this->get_mode_settings_fields();

		// Merchant ID.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_elavon_converge_merchant_id',
			'title'    => __( 'Account ID (6-7 digits)', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( 'Merchant ID. Elavon-assigned Converge Account ID (AID)', 'knit-pay-lang' ),
		];

		// Converge User ID.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_elavon_converge_user_id',
			'title'    => __( 'Converge User ID', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( 'The user ID with Hosted Payment API User status that can send transaction requests through the terminal. Found on the Employees page.', 'knit-pay-lang' ),
		];

		// Terminal PIN.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_elavon_converge_terminal_pin',
			'title'    => __( 'Terminal PIN', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( 'Unique identifier of the terminal that will process the transaction request and submit to the Converge gateway. Found on the Employees page.', 'knit-pay-lang' ),
		];

		// Multi Currency Enabled.
		$fields[] = [
			'section'  => 'general',
			'filter'   => FILTER_VALIDATE_BOOLEAN,
			'meta_key' => '_pronamic_gateway_elavon_converge_multi_currency_enabled',
			'title'    => __( 'Multi Currency Enabled', 'knit-pay-lang' ),
			'type'     => 'checkbox',
			'label'    => __( 'Send WordPress Transaction Currency to Elavon - Converge', 'knit-pay-lang' ),
		];

		$fields[] = [
			'section'  => 'general',
			'title'    => \__( 'Redirect URL', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'large-text', 'code' ],
			'value'    => add_query_arg( 'kp_elavon_converge_redirect', '', home_url( '/' ) ),
			'readonly' => true,
			'tooltip'  => sprintf(
				/* translators: %s: PayUmoney */
				__(
					'Copy the Redirect URL to the %s dashboard.',
					'knit-pay'
				),
				__( 'Converge', 'knit-pay-lang' )
			),
		];

		// Return fields.
		return $fields;
	}

	public function get_config( $post_id ) {
		$config = new Config();

		$config->merchant_id            = $this->get_meta( $post_id, 'elavon_converge_merchant_id' );
		$config->user_id                = $this->get_meta( $post_id, 'elavon_converge_user_id' );
		$config->terminal_pin           = $this->get_meta( $post_id, 'elavon_converge_terminal_pin' );
		$config->multi_currency_enabled = $this->get_meta( $post_id, 'elavon_converge_multi_currency_enabled' );
		$config->mode                   = $this->get_meta( $post_id, 'mode' );

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
