<?php

namespace KnitPay\Gateways;

use Pronamic\WordPress\DateTime\DateTime;
use Pronamic\WordPress\Pay\AbstractGatewayIntegration;
use Pronamic\WordPress\Pay\Core\IntegrationModeTrait;
use Pronamic\WordPress\Pay\Core\PaymentMethods;
use Pronamic\WordPress\Pay\Payments\Payment;
use KnitPay\Utils;

/**
 * Title: Integration for Gateway OAuth Client
 * Copyright: 2020-2025 Knit Pay
 *
 * @author  Knit Pay
 * @version 1.0.0
 * @since   1.7.0
 */
abstract class IntegrationOAuthClient extends AbstractGatewayIntegration {
	use IntegrationModeTrait;

	protected $config;
	private $can_create_connection;
	private $gateway_name;

	const KNIT_PAY_OAUTH_SERVER_URL        = 'https://oauth-server.knitpay.org/api/';
	const RENEWAL_TIME_BEFORE_TOKEN_EXPIRE = 15 * MINUTE_IN_SECONDS; // 15 minutes.

	/**
	 * Construct Integration for Gateway OAuth Client.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = [] ) {
		parent::__construct( $args );

		$this->gateway_name = isset( $args['gateway_name'] ) ? $args['gateway_name'] : $this->get_name();

		// create connection if Merchant ID not available.
		$this->can_create_connection = true;
	}

	abstract public function get_child_config( $post_id );
	abstract public function clear_child_config( $post_id );

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

		\add_filter( 'pronamic_payment_provider_url_' . $this->get_id(), [ $this, 'payment_provider_url' ], 10, 2 );

		// Connect/Disconnect Listener.
		$function = [ $this, 'update_connection_status' ];
		if ( ! has_action( 'wp_loaded', $function ) ) {
			add_action( 'wp_loaded', $function );
		}

		// Get new access token if it's about to get expired.
		add_action( 'knit_pay_' . $this->get_id() . '_refresh_access_token', [ $this, 'refresh_access_token' ], 10, 1 );
	}

	public function allowed_redirect_hosts( $hosts ) {
		return $hosts;
	}

	/**
	 * Payment provider URL.
	 *
	 * @param string|null $url     Payment provider URL.
	 * @param Payment     $payment Payment.
	 * @return string|null
	 */
	public function payment_provider_url( $url, Payment $payment ) {
		return $url;
	}

	/**
	 * Get settings fields.
	 *
	 * @return array
	 */
	public function get_settings_fields() {
		$fields = [];
		
		$config_id = Utils::get_gateway_config_id();

		if ( ! empty( $config_id ) ) {
			$this->config = $this->get_config( $config_id );
		}

		$mode = isset( $_GET['gateway_mode'] ) ? sanitize_text_field( $_GET['gateway_mode'] ) : null;

		// Get mode from Integration mode trait.
		$fields[] = $this->get_mode_settings_fields();

		if ( $this->is_auth_basic_enabled( $this->config ) ) {
			$fields = $this->get_signup_button_field( $fields );
			$fields = $this->get_basic_auth_fields( $fields );
		} elseif ( ! $this->is_oauth_connected( $this->config ) ) {
			$fields = $this->get_signup_button_field( $fields );
			$fields = $this->get_oauth_connect_button_fields( $fields );
		} else {
			$fields = $this->get_oauth_connection_status_fields( $fields );
		}
		
		$fields = $this->show_common_setting_fields( $fields );
		
		return $fields;
	}

	protected function get_basic_auth_fields( $fields ) {
		return $fields;
	}

	/**
	 * Get config.
	 *
	 * @param int $post_id Post ID.
	 * @return Config
	 */
	public function get_config( $post_id ) {
		$config = $this->get_child_config( $post_id );

		$config->config_id = $post_id;

		// Schedule next refresh token if not done before.
		if ( isset( $config->expires_at ) ) {
			self::schedule_next_refresh_access_token( $post_id, $config->expires_at );
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

	/**
	 * When the post is saved, saves our custom data.
	 *
	 * @param int $config_id The ID of the post being saved.
	 * @return void
	 */
	public function save_post( $config_id ) {
		parent::save_post( $config_id );

		// Execute below code only for OAuth Mode.
		$config = $this->get_config( $config_id );

		if ( $this->is_auth_basic_enabled( $config ) ) {
			$this->create_basic_connection( $config_id );

			self::configure_webhook( $config_id );
			return;
		}
		
		if ( ! $this->is_oauth_connected( $config ) || $this->is_mode_changed( $config ) ) {
			return $this->init_oauth_connect( $config, $config_id );
		}

		// Clear Keys if not connected.
		if ( ! $config->is_connected && $this->is_oauth_connected( $config ) ) {
			self::clear_config( $config_id );
			return;
		}

		self::configure_webhook( $config_id );
	}

	protected function init_oauth_connect( $config, $config_id, $return_response = false ) {
		// Clear Old config before creating new connection.
		self::clear_config( $config_id );

		$response = wp_remote_post(
			self::KNIT_PAY_OAUTH_SERVER_URL . $this->get_id() . '/oauth/authorize',
			[
				'body'    => wp_json_encode(
					[
						'admin_url'  => admin_url(),
						'gateway_id' => $config_id,
						'mode'       => $config->mode,
					]
				),
				'timeout' => 60,
			]
		);
		$result   = wp_remote_retrieve_body( $response );
		$result   = json_decode( $result );

		if ( ! isset( $result->success ) ) {
			$result = (object) [
				'success' => false,
				'data'    => (object) [
					'message' => 'Not receiving a valid response from the Knit Pay OAuth Server. Please try again after some time or report the issue to the Knit Pay support team.',
				],
			];
		}

		if ( $return_response ) {
			return $result;
		} elseif ( $result->success ) {
			add_filter( 'allowed_redirect_hosts', [ $this, 'allowed_redirect_hosts' ] );
			wp_safe_redirect( $result->data->auth_url );
			exit;
		} elseif ( isset( $result->data ) ) {
			echo $result->data->message;
			exit;
		} elseif ( isset( $result->errors ) ) {
			echo $result->errors[0]->message;
			exit;
		}
	}

	protected function clear_config( $config_id ) {
		$this->clear_child_config( $config_id );

		// Stop Refresh Token Scheduler.
		$timestamp_next_schedule = wp_next_scheduled( 'knit_pay_' . $this->get_id() . '_refresh_access_token', [ 'config_id' => $config_id ] );
		wp_unschedule_event( $timestamp_next_schedule, 'knit_pay_' . $this->get_id() . '_refresh_access_token', [ 'config_id' => $config_id ] );
	}

	public function update_connection_status() {
		if ( ! ( filter_has_var( INPUT_GET, 'knitpay_oauth_auth_status' ) && current_user_can( 'manage_options' ) ) ) {
			return;
		}

		$code                      = isset( $_GET['code'] ) ? sanitize_text_field( $_GET['code'] ) : null;
		$state                     = isset( $_GET['state'] ) ? sanitize_text_field( $_GET['state'] ) : null;
		$gateway_id                = isset( $_GET['gateway_id'] ) ? sanitize_text_field( $_GET['gateway_id'] ) : null;
		$gateway                   = isset( $_GET['gateway'] ) ? sanitize_text_field( $_GET['gateway'] ) : null;
		$knitpay_oauth_auth_status = isset( $_GET['knitpay_oauth_auth_status'] ) ? sanitize_text_field( $_GET['knitpay_oauth_auth_status'] ) : null;

		if ( $this->get_id() !== $gateway ) {
			return;
		}

		// Don't interfere if rzp-wppcommerce attempting to connect.
		// TODO, move to Razorpay.
		if ( 'rzp-woocommerce' === $gateway_id ) {
			return;
		}

		if ( empty( $code ) || empty( $state ) || 'failed' === $knitpay_oauth_auth_status ) {
			self::clear_config( $gateway_id );
			$this->redirect_to_config( $gateway_id );
		}

		$config = $this->get_config( $gateway_id );

		// GET keys.
		$oauth_token_request_body = [
			'code'       => $code,
			'state'      => $state,
			'gateway_id' => $gateway_id,
			'mode'       => $config->mode,
		];
		$oauth_token_request_body = $this->get_oauth_token_request_body( $oauth_token_request_body );
		$response                 = wp_remote_post(
			self::KNIT_PAY_OAUTH_SERVER_URL . $this->get_id() . '/oauth/token',
			[
				'body'    => wp_json_encode( $oauth_token_request_body ),
				'timeout' => 90,
			]
		);
		$result                   = wp_remote_retrieve_body( $response );
		$result                   = json_decode( $result );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			self::redirect_to_config( $gateway_id );
			return;
		}

		$this->save_token( $gateway_id, $result, true );

		// Update active payment methods.
		PaymentMethods::update_active_payment_methods();

		// TODO move to razorpay.
		// self::configure_webhook( $gateway_id );

		self::redirect_to_config( $gateway_id );
	}

	public function refresh_access_token( $config_id ) {
		if ( 'publish' !== get_post_status( $config_id ) ) {
			return;
		}
		
		// Don't refresh again if already refreshing.
		if ( get_transient( 'knit_pay_' . $this->get_id() . '_refreshing_access_token_' . $config_id ) ) {
			return;
		}
		set_transient( 'knit_pay_' . $this->get_id() . '_refreshing_access_token_' . $config_id, true, MINUTE_IN_SECONDS );
		
		$config = $this->get_config( $config_id );

		// Don't proceed further if it's API key connection.
		if ( $this->is_auth_basic_connected( $config ) ) {
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
		// TODO fix cashfree refresh token.
		$response = wp_remote_post(
			self::KNIT_PAY_OAUTH_SERVER_URL . $this->get_id() . '/oauth/token',
			[
				'body'    => wp_json_encode(
					[
						'refresh_token' => $config->refresh_token,
						'gateway_id'    => $config_id,
						'mode'          => $config->mode,
					]
				),
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

		if ( $this->refresh_failed_action( $result, $config, $config_id ) ) {
			return;
		}

		$this->save_token( $config_id, $result );
	}

	protected function save_token( $gateway_id, $token_data, $new_connection = false ) {
		if ( ! ( isset( $token_data->success ) && $token_data->success ) ) {
			return;
		}
		
		$token_data = $token_data->data;
		$expires_id = isset( $token_data->expires_in ) ? $token_data->expires_in : 86400;

		$token_data->expires_at   = time() + $expires_id - 1800;
		$token_data->is_connected = true;
		
		if ( $new_connection ) {
			$token_data->connected_at = time();
		}
		
		unset( $token_data->expires_in );
		unset( $token_data->connection_status );
		unset( $token_data->token_type );
		
		foreach ( $token_data as $key => $value ) {
			update_post_meta( $gateway_id, '_pronamic_gateway_' . $this->get_id() . '_' . $key, $value );
		}

		// Reset Connection Fail Counter.
		delete_post_meta( $gateway_id, '_pronamic_gateway_' . $this->get_id() . '_connection_fail_count' );

		$this->schedule_next_refresh_access_token( $gateway_id, $token_data->expires_at );
	}

	private function redirect_to_config( $gateway_id ) {
		wp_safe_redirect( get_edit_post_link( $gateway_id, false ) );
		exit;
	}

	private function schedule_next_refresh_access_token( $config_id, $expires_at ) {
		if ( empty( $expires_at ) ) {
			return;
		}
		
		// Don't set next refresh cron if already refreshing.
		if ( get_transient( 'knit_pay_' . $this->get_id() . '_refreshing_access_token_' . $config_id ) ) {
			return;
		}

		$next_schedule_time = wp_next_scheduled( 'knit_pay_' . $this->get_id() . '_refresh_access_token', [ 'config_id' => $config_id ] );
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
			'knit_pay_' . $this->get_id() . '_refresh_access_token',
			[ 'config_id' => $config_id ]
		);
	}

	private static function configure_webhook( $config_id ) {
		return;
	}

	private function create_basic_connection( $config_id ) {
		return;
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
		update_post_meta( $config_id, '_pronamic_gateway_' . $this->get_id() . '_connection_fail_count', $connection_fail_count );
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
		$access_token_info  = '<dl>';
		$access_token_info .= isset( $connected_at ) ? sprintf( '<dt><strong>Connected at:</strong></dt><dd>%s</dd>', $connected_at->format_i18n() ) : '';

		if ( isset( $config->expires_at ) ) {
			$expire_date = new DateTime();
			$expire_date->setTimestamp( $config->expires_at );
			$access_token_info .= sprintf( '<dt><strong>Access Token Expiry Date:</strong></dt><dd>%s</dd>', $expire_date->format_i18n() );

			$renew_schedule_time = new DateTime();
			$renew_schedule_time->setTimestamp( wp_next_scheduled( 'knit_pay_' . $this->get_id() . '_refresh_access_token', [ 'config_id' => $config->config_id ] ) );
			$access_token_info .= sprintf( '<dt><strong>Next Automatic Renewal Scheduled at:</strong></dt><dd>%s</dd>', $renew_schedule_time->format_i18n() );
		}

		$access_token_info .= '</dl>';
		echo $access_token_info;
	}

	protected function is_auth_basic_enabled( $config ) {
		return false;
		// TODO
		return defined( 'KNIT_PAY_RAZORPAY_API' ) || 'razorpay-pro' === $this->get_id();
	}
	
	protected function is_oauth_connected( $config ) {
		return ! empty( $config->access_token );
	}

	private function is_auth_basic_connected( $config ) {
		return false;
	}

	private function is_mode_changed( $config ) {
		return false;
	}

	private function get_signup_button_field( $fields ) {
		 // SignUp.
		$fields[] = [
			'section'  => 'general',
			'type'     => 'custom',
			'title'    => 'Sign Up Now',
			'callback' => function () {
				echo sprintf(
					__( 'Before proceeding, kindly create an account at %1$s if you don\'t have one already.%2$s', 'knit-pay-lang' ),
					$this->gateway_name,
					'<br><br><a class="button button-primary button-large" target="_blank" href="' . $this->get_url() . 'help-signup"
					 role="button"><strong>Sign Up for ' . $this->gateway_name . '</strong></a>'
				);
			},
		];

		return $fields;
	}
	
	protected function get_oauth_connect_button_fields( $fields ) {
		// Oauth Connect Description.
		$fields[] = [
			'section'  => 'general',
			'type'     => 'custom',
			'title'    => $this->gateway_name . ' Connect',
			'callback' => function () {
				echo '<p><h1>' . __( 'How it works?' ) . '</h1></p>' .
				'<p>' . __( 'To provide a seamless integration experience, Knit Pay has introduced ' . $this->gateway_name . ' Platform Connect. Now you can integrate ' . $this->gateway_name . ' in Knit Pay with just a few clicks.' ) . '</p>' .
				'<p>' . __( 'Click on "<strong>Connect with ' . $this->gateway_name . '</strong>" below to initiate the connection.' ) . '</p>';
			},
		];
		
		// Connect.
		$fields[] = [
			'section'  => 'general',
			'type'     => 'custom',
			'callback' => function () {
				echo '<a id="' . $this->get_id() . '-platform-connect" class="button button-primary button-large"
		                  role="button" style="font-size: 21px;">Connect with <strong>' . $this->gateway_name . '</strong></a>
                        <script>
                            document.getElementById("' . $this->get_id() . '-platform-connect").addEventListener("click", function(event){
                                event.preventDefault();
                                document.getElementById("publish").click();
                            });
                        </script>';
			},
		];
		
		return $fields;
	}

	protected function get_oauth_connection_status_fields( $fields ) {
		// Remove Knit Pay as an Authorized Application.
			/*
			$fields[] = [
			'section'     => 'general',
				'title'       => __( 'Remove Knit Pay as an Connected Application for my '. $this->gateway_name .' account.', 'knit-pay-lang' ),
				'type'        => 'custom',
				'callback'    => function () {
					echo '<script>
					document.getElementById("_pronamic_gateway_mode").addEventListener("change", function(event){
								event.preventDefault();
								document.getElementById("publish").click();
							});
				 </script>';
				},
				'description' => '<p>Removing Knit Pay as an Connected Application for your '.$this->gateway_name.' account will remove the connection between all the sites that you have connected to Knit Pay using the same '.$this->gateway_name.' account and connect method. Proceed with caution while disconnecting if you have multiple sites connected.</p>' .
				'<br><a class="button button-primary button-large" target="_blank" href="https://dashboard.razorpay.com/app/website-app-settings/applications" role="button"><strong>View connected applications in '.$this->gateway_name.'</strong></a>',
			];*/

			// Connected with OAuth.
			$fields[] = [
				'section'     => 'general',
				'filter'      => FILTER_VALIDATE_BOOLEAN,
				'meta_key'    => '_pronamic_gateway_' . $this->get_id() . '_is_connected',
				'title'       => __( 'Connected with ', 'knit-pay-lang' ) . $this->gateway_name,
				'type'        => 'checkbox',
				'description' => 'This gateway configuration is connected with ' . $this->gateway_name . ' Platform Connect. Uncheck this and save the configuration to disconnect it.',
				'label'       => __( 'Uncheck and save to disconnect the ' . $this->gateway_name . ' Account.', 'knit-pay-lang' ),
			];

			// Connection Status.
			$fields[] = [
				'section'  => 'general',
				'title'    => __( 'Connection Status', 'knit-pay-lang' ),
				'type'     => 'custom',
				'callback' => [ $this, 'connection_status_box' ],
			];

			return $fields;
	}
	
	protected function show_common_setting_fields( $fields ) {
		return $fields;
	}

	protected function get_oauth_token_request_body( $oauth_token_request_body ) {
		return $oauth_token_request_body;
	}

	protected function refresh_failed_action( $result, $config, $config_id ) {
		if ( isset( $result->success ) && ! $result->success ) {
			$this->inc_refresh_token_fail_counter( $config, $config_id );
			self::schedule_next_refresh_access_token( $config_id, $config->expires_at );

			return true;
		}
		return false;
	}
}
