<?php

namespace KnitPay\Gateways\Paypal;

use Pronamic\WordPress\Pay\Core\IntegrationModeTrait;
use KnitPay\Gateways\IntegrationOAuthClient;

/**
 * Title: Paypal Integration
 * Copyright: 2020-2025 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.94.0.0
 * @since   8.94.0.0
 */
class Integration extends IntegrationOAuthClient {
	use IntegrationModeTrait;

	/**
	 * Construct Paypal integration.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'id'       => 'paypal',
				'name'     => 'Paypal',
				'provider' => 'paypal',
			]
		);

		parent::__construct( $args );

		// TODO https://developer.paypal.com/docs/api/webhooks/v1/#webhooks_post
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

		return empty( $config->merchant_id ) ? $config->client_id : $config->merchant_id;
	}

	protected function get_oauth_connect_button_fields( $fields ) {
		// Signup.
		// TODO Add signup button code.
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

		// Oauth Connect Description.
		$fields[] = [
			'section'  => 'general',
			'type'     => 'custom',
			'title'    => $this->get_name() . ' Connect',
			'callback' => function () {
				echo '<p><h1>' . __( 'How it works?' ) . '</h1></p>' .
				'<p>' . __( 'To provide a seamless integration experience, Knit Pay has introduced ' . $this->get_name() . ' Platform Connect. Now you can integrate ' . $this->get_name() . ' in Knit Pay with just a few clicks.' ) . '</p>' .
				'<p>' . __( 'Click on "<strong>Connect with ' . $this->get_name() . '</strong>" below to initiate the connection.' ) . '</p>';
			},
		];

		// Connect.
		$fields[] = [
			'section'  => 'general',
			'type'     => 'custom',
			'callback' => function () {
				$admin_url          = admin_url();
				$auth_response_data = $this->init_oauth_connect( $this->config, $this->config->config_id, true );
				$auth_url           = $auth_response_data->auth_url;
				$state              = $auth_response_data->state;
				$auth_url           = add_query_arg( [ 'displayMode' => 'minibrowser' ], $auth_url );

				echo '
				<a target="_blank" data-paypal-onboard-complete="onboardedCallback" href="' . $auth_url . '" data-paypal-button="true" class="button button-primary button-large"
		                  role="button" style="font-size: 21px;background: #3395ff;">Connect with <strong>' . $this->get_name() . '</strong></a>
				<script>
					function onboardedCallback(authCode, sharedId) {
						const admin_url = new URL("' . $admin_url . '");
						admin_url.searchParams.append("knitpay_oauth_auth_status", "connected");
						admin_url.searchParams.append("code", authCode);
						admin_url.searchParams.append("shared_id", sharedId);
						admin_url.searchParams.append("state", "' . $state . '");
						admin_url.searchParams.append("gateway_id", ' . $this->config->config_id . ');
						admin_url.searchParams.append("gateway", "' . $this->get_id() . '");

						// Close login window.
						window.open("", "PPMiniWin").close();
						window.location.href = admin_url.toString();
					}
				</script>
				<script id="paypal-js" src="https://www.sandbox.paypal.com/webapps/merchantboarding/js/lib/lightbox/partner.js"></script>';
			},
		];

		return $fields;
	}

	/**
	 * Get settings fields.
	 *
	 * @return array
	 */
	protected function get_basic_auth_fields( $fields ) {
		/*
		$fields = parent::get_oauth_connection_status_fields( $fields );

		// Merchant ID.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_paypal_merchant_id',
			'title'    => __( 'Merchant ID', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'readonly' => true,
		];*/

		// Client ID.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_paypal_client_id',
			'title'    => __( 'Client ID', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			// 'readonly' => true,
		];

		// Client Secret.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_paypal_client_secret',
			'title'    => __( 'Client Secret', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			// 'readonly' => true,
		];

		// Return fields.
		return $fields;
	}

	public function get_child_config( $post_id ) {
		$config = new Config();

		$config->client_id     = $this->get_meta( $post_id, 'paypal_client_id' );
		$config->client_secret = $this->get_meta( $post_id, 'paypal_client_secret' );

		// OAuth.
		$config->merchant_id  = $this->get_meta( $post_id, 'paypal_merchant_id' );
		$config->is_connected = $this->get_meta( $post_id, 'paypal_is_connected' );
		$config->connected_at = $this->get_meta( $post_id, 'paypal_connected_at' );

		$config->mode = $this->get_meta( $post_id, 'mode' );

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

	public function clear_child_config( $config_id ) {
		delete_post_meta( $config_id, '_pronamic_gateway_' . $this->get_id() . '_is_connected' );
		delete_post_meta( $config_id, '_pronamic_gateway_' . $this->get_id() . '_connected_at' );
		delete_post_meta( $config_id, '_pronamic_gateway_' . $this->get_id() . '_merchant_id' );

		delete_post_meta( $config_id, '_pronamic_gateway_' . $this->get_id() . '_client_id' );
		delete_post_meta( $config_id, '_pronamic_gateway_' . $this->get_id() . '_client_secret' );
	}

	protected function is_auth_basic_enabled( $config ) {
		return true;
	}

	protected function is_oauth_connected( $config ) {
		return ! empty( $config->client_secret );
	}

	protected function get_oauth_token_request_body( $oauth_token_request_body ) {
		$oauth_token_request_body['shared_id'] = isset( $_GET['shared_id'] ) ? sanitize_text_field( $_GET['shared_id'] ) : null;

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
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save_post( $post_id ) {
	}
}
