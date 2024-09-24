<?php

namespace KnitPay\Gateways\Stripe\Connect;

use KnitPay\Gateways\Stripe\Integration as Stripe_Integration;
use Pronamic\WordPress\Pay\Core\PaymentMethods;
use KnitPay\Utils as KnitPayUtils;

/**
 * Title: Stripe Connect Integration
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 1.0.0
 * @since   3.7.0
 */
class Integration extends Stripe_Integration {
	const KNIT_PAY_STRIPE_CONNECT_PLATFORM_URL       = 'https://www.knitpay.org/stripe-connect-server/';
	const STRIPE_CONNECT_APPLICATION_FEES_PERCENTAGE = 0.75;

	private $config;

	/**
	 * Construct Stripe Connect integration.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args( // TODO complete the array.
			$args,
			[
				'id'          => 'stripe-connect',
				'name'        => defined( 'KNIT_PAY_PRO' ) ? 'Stripe Connect' : 'Stripe Connect - For Indian Businesses',
				'product_url' => 'http://go.thearrangers.xyz/stripe?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=product-url',
				'provider'    => 'stripe-connect',
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

		// Connect/Disconnect Listener.
		$function = [ __NAMESPACE__ . '\Integration', 'update_connection_status' ];
		if ( ! has_action( 'wp_loaded', $function ) ) {
			add_action( 'wp_loaded', $function );
		}
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

		return $config->account_id;
	}

	/**
	 * Get settings fields.
	 *
	 * @return array
	 */
	public function get_settings_fields() {
		$fields = [];

		// Stripe Connect Description.
		if ( ! defined( 'KNIT_PAY_PRO' ) ) {
			$fields[] = [
				'section'  => 'general',
				'type'     => 'custom',
				'title'    => 'Stripe Connect',
				'callback' => function () {
					echo '<p><h1>' . __( 'How it works?' ) . '</h1></p>' .
					'<p>' . __( 'Stripe Connect - Knit Pay integration enables Indian merchants to accept payments using their Stripe account without purchasing the "Knit Pay - Stripe" Premium addon.' ) . '</p>' .
					'<p>' . __( '<strong>The free Stripe Connect integration includes an additional ' . self::STRIPE_CONNECT_APPLICATION_FEES_PERCENTAGE . '% fee above the Stripe pricing for processing payment.</strong> Additional charges will not be there in the Stripe premium addon, available at one-time fees.' ) . '</p>';
				},
			];
		}

		// Get Parent settings fields.
		$parent_fields = parent::get_settings_fields();
		foreach ( $parent_fields as $field ) {
			if ( ! in_array(
				$field['meta_key'],
				[
					'_pronamic_gateway_stripe_publishable_key',
					'_pronamic_gateway_stripe_secret_key',
					'_pronamic_gateway_stripe_test_publishable_key',
					'_pronamic_gateway_stripe_test_secret_key',
				],
				true
			) ) {
				$fields[] = $field;
			}
		}

		// Get Config ID from Post.
		$config_id = KnitPayUtils::get_gateway_config_id();

		if ( ! empty( $config_id ) ) {
			$this->config = $this->get_config( $config_id );
		}

		// GET mode from ajax call or saved mode if it's not ajax call.
		$selected_gateway_mode = isset( $_GET['gateway_mode'] ) ? sanitize_text_field( $_GET['gateway_mode'] ) : '';
		if ( empty( $selected_gateway_mode ) && isset( $this->config ) ) {
			$selected_gateway_mode = $this->config->mode;
		}

		// FIXME: Check flow if compatible with wp core pay 4.1.3
		if ( ! isset( $this->config ) ||
			( Gateway::MODE_TEST === $selected_gateway_mode && ! $this->config->is_test_set() ) ||
			( Gateway::MODE_LIVE === $selected_gateway_mode && ! $this->config->is_live_set() ) ) {
			// Connect.
			$fields[] = [
				'section'  => 'general',
				'type'     => 'custom',
				'callback' => function () {
					echo '<input id="stripe-connect" type="image" alt="Submit" height="50"
                    src="' . KNITPAY_URL . '/gateways/stripe/connect/src/assets/connect-with-stripe.svg">

                            <script>
                                document.getElementById("stripe-connect").addEventListener("click", function(event){
                                    event.preventDefault();
                                    document.getElementById("publish").click();;
                                });
                            </script>';
				},
			];
			return $fields;
		} else {
			// Remove Knit Pay as an Authorized Application.
			$fields[] = [
				'section'  => 'general',
				'title'    => __( 'Remove Knit Pay as an Authorized Application for my Stripe account.', 'knit-pay-lang' ),
				'type'     => 'custom',
				'callback' => function () {
					echo '<p>Removing Knit Pay as an Authorized Application for your Stripe account will remove the connection between all the sites that you have connected to Knit Pay using the same Stripe account and connect method. Proceed with caution while disconnecting if you have multiple sites connected.</p>' .
					'<br><a class="button button-primary button-large" target="_blank" href="https://dashboard.stripe.com/account/applications" role="button"><strong>View authorized applications in Stripe</strong></a>' .
					'<script>
                    document.getElementById("_pronamic_gateway_mode").addEventListener("change", function(event){
                                event.preventDefault();
                                document.getElementById("publish").click();
                            });
                 </script>';
				},
			];

			// Connected with Stripe.
			$fields[] = [
				'section'     => 'general',
				'filter'      => FILTER_VALIDATE_BOOLEAN,
				'meta_key'    => '_pronamic_gateway_stripe_is_connected',
				'title'       => __( 'Connected with Stripe', 'knit-pay-lang' ),
				'type'        => 'checkbox',
				'description' => 'This gateway configuration is connected with Stripe Connect. Uncheck this and save the configuration to disconnect it.',
				'label'       => __( 'Uncheck and save to disconnect the Stripe Account.', 'knit-pay-lang' ),
			];
		}

		// Return fields.
		return $fields;
	}

	/**
	 * Get configuration by post ID.
	 *
	 * @param int $post_id Post ID.
	 * @return Config
	 */
	public function get_config( $post_id ) {
		$config = new Config();
		$config->copy_properties( parent::get_config( $post_id ) );

		$config->account_id                  = $this->get_meta( $post_id, 'stripe_account_id' );
		$config->application_fees_percentage = $this->get_meta( $post_id, 'stripe_application_fees_percentage' );
		$config->is_connected                = $this->get_meta( $post_id, 'stripe_is_connected' );

		return $config;
	}

	/**
	 * Get gateway.
	 *
	 * @param int $config_id Post ID.
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
	 * When the post is saved, saves our custom data.
	 *
	 * @param int $config_id The ID of the post being saved.
	 * @return void
	 */
	public function save_post( $config_id ) {
		parent::save_post( $config_id );
		$config = $this->get_config( $config_id );

		if ( Gateway::MODE_LIVE === $config->mode && ! $config->is_live_set() ) {
			$this->connect( $config, $config_id );
			return;
		}

		// Clear Keys if not connected.
		if ( ! $config->is_connected && ! empty( $config->account_id ) ) {
			self::clear_config( $config_id );
			return;
		}

		if ( Gateway::MODE_LIVE === $config->mode && ! $config->is_live_set() ||
			Gateway::MODE_TEST === $config->mode && ! $config->is_test_set() ) {
			$this->connect( $config, $config_id );
		}
	}

	private function connect( $config, $config_id ) {
		// Clear Old config before creating new connection.
		self::clear_config( $config_id );

		$response = wp_remote_post(
			self::KNIT_PAY_STRIPE_CONNECT_PLATFORM_URL,
			[
				'body'    => [
					'admin_url'             => rawurlencode( admin_url() ),
					'stripe_connect_action' => 'connect',
					'gateway_id'            => $config_id,
					'mode'                  => $config->mode,
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
			wp_redirect( $result->return_url, 303 );
			exit;
		}
	}

	public static function update_connection_status() {
		if ( ! ( filter_has_var( INPUT_GET, 'stripe_connect_status' ) && current_user_can( 'manage_options' ) ) ) {
			return;
		}

		$code                  = filter_input( INPUT_GET, 'code', FILTER_SANITIZE_STRING );
		$state                 = filter_input( INPUT_GET, 'state', FILTER_SANITIZE_STRING );
		$gateway_id            = filter_input( INPUT_GET, 'gateway_id', FILTER_SANITIZE_STRING );
		$stripe_connect_status = filter_input( INPUT_GET, 'stripe_connect_status', FILTER_SANITIZE_STRING );

		if ( empty( $code ) || empty( $state ) || empty( $gateway_id ) || 'failed' === $stripe_connect_status ) {
			self::clear_config( $gateway_id );
			self::redirect_to_config( $gateway_id );
		}

		// GET keys.
		$response = wp_remote_post(
			self::KNIT_PAY_STRIPE_CONNECT_PLATFORM_URL,
			[
				'body'    => [
					'code'                  => $code,
					'state'                 => $state,
					'gateway_id'            => $gateway_id,
					'stripe_connect_action' => 'get-keys',
				],
				'timeout' => 60,
			]
		);
		$result   = wp_remote_retrieve_body( $response );
		$result   = json_decode( $result );

		if ( ! ( isset( $result->stripe_connect_status ) && 'connected' === $result->stripe_connect_status ) ) {
			return;
		}

		update_post_meta( $gateway_id, '_pronamic_gateway_stripe_publishable_key', $result->stripe_publishable_key );
		update_post_meta( $gateway_id, '_pronamic_gateway_stripe_secret_key', $result->stripe_secret_key );
		update_post_meta( $gateway_id, '_pronamic_gateway_stripe_account_id', $result->stripe_user_id );
		update_post_meta( $gateway_id, '_pronamic_gateway_stripe_test_publishable_key', $result->stripe_test_publishable_key );
		update_post_meta( $gateway_id, '_pronamic_gateway_stripe_test_secret_key', $result->stripe_test_secret_key );
		update_post_meta( $gateway_id, '_pronamic_gateway_stripe_application_fees_percentage', self::STRIPE_CONNECT_APPLICATION_FEES_PERCENTAGE / 100 );
		update_post_meta( $gateway_id, '_pronamic_gateway_stripe_is_connected', true );

		// Update active payment methods.
		PaymentMethods::update_active_payment_methods();

		self::redirect_to_config( $gateway_id );
	}

	private static function redirect_to_config( $gateway_id ) {
		wp_safe_redirect( get_edit_post_link( $gateway_id, false ) );
		exit;
	}

	private static function clear_config( $config_id ) {
		update_post_meta( $config_id, '_pronamic_gateway_stripe_publishable_key', '' );
		update_post_meta( $config_id, '_pronamic_gateway_stripe_secret_key', '' );
		update_post_meta( $config_id, '_pronamic_gateway_stripe_account_id', '' );
		update_post_meta( $config_id, '_pronamic_gateway_stripe_test_publishable_key', '' );
		update_post_meta( $config_id, '_pronamic_gateway_stripe_test_secret_key', '' );
		update_post_meta( $config_id, '_pronamic_gateway_stripe_application_fees_percentage', '' );
		update_post_meta( $config_id, '_pronamic_gateway_stripe_is_connected', false );
	}
}
