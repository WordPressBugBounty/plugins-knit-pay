<?php

namespace KnitPay\Gateways\PhonePe;

use Pronamic\WordPress\Pay\AbstractGatewayIntegration;
use Pronamic\WordPress\Pay\Core\IntegrationModeTrait;
use KnitPay\Utils;

/**
 * Title: PhonePe Integration
 * Copyright: 2020-2025 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.73.0.0
 * @since   8.73.0.0
 */
class Integration extends AbstractGatewayIntegration {
	use IntegrationModeTrait;
	
	/**
	 * Construct PhonePe integration.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'id'       => 'phonepe',
				'name'     => 'PhonePe PG',
				'provider' => 'phonepe',
				'supports' => [
					'webhook',
					'webhook_log',
					'webhook_no_config',
				],
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
		
		return ( 'v1' === $config->api_version ) ? $config->merchant_id : $config->client_id;
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

		$config_id = Utils::get_gateway_config_id();
		if ( ! empty( $config_id ) ) {
			$config = $this->get_config( $config_id );
		}

		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_phonepe_api_version',
			'title'    => __( 'API Version', 'knit-pay-lang' ),
			'type'     => 'select',
			'options'  => [
				'v1' => 'v1',
				'v2' => 'v2',
			],
			'default'  => $config->api_version,
			'callback' => function () {
				?>
				<script>
					jQuery( document ).ready( function( $ ) {
						$( '#_pronamic_gateway_phonepe_api_version' ).on( 'change', function(){
							var api_version = $( this ).val();
							if ( 'v1' === api_version ) {
								$( '#_pronamic_gateway_phonepe_merchant_id' ).closest( 'tr' ).show();
								$( '#_pronamic_gateway_phonepe_salt_key' ).closest( 'tr' ).show();
								$( '#_pronamic_gateway_phonepe_salt_index' ).closest( 'tr' ).show();
								$( '#_pronamic_gateway_phonepe_client_id' ).closest( 'tr' ).hide();
								$( '#_pronamic_gateway_phonepe_client_secret' ).closest( 'tr' ).hide();
								$( '#_pronamic_gateway_phonepe_client_version' ).closest( 'tr' ).hide();
							} else {
								$( '#_pronamic_gateway_phonepe_merchant_id' ).closest( 'tr' ).hide();
								$( '#_pronamic_gateway_phonepe_salt_key' ).closest( 'tr' ).hide();
								$( '#_pronamic_gateway_phonepe_salt_index' ).closest( 'tr' ).hide();
								$( '#_pronamic_gateway_phonepe_client_id' ).closest( 'tr' ).show();
								$( '#_pronamic_gateway_phonepe_client_secret' ).closest( 'tr' ).show();
								$( '#_pronamic_gateway_phonepe_client_version' ).closest( 'tr' ).show();
							}
						} ).trigger( 'change' );
					} );
				</script>
				<?php
			},
		];

		// Merchant ID.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_phonepe_merchant_id',
			'title'    => __( 'Merchant ID', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
		];

		// Salt Key.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_phonepe_salt_key',
			'title'    => __( 'Salt Key', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
		];

		// Salt Index.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_phonepe_salt_index',
			'title'    => __( 'Salt Index', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
		];

		// Client ID.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_phonepe_client_id',
			'title'    => __( 'Client ID', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
		];

		// Client Secret.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_phonepe_client_secret',
			'title'    => __( 'Client Secret', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
		];

		// Client Version.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_phonepe_client_version',
			'title'    => __( 'Client Version', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
		];

		// Return fields.
		return $fields;
	}

	/**
	 * Get config.
	 *
	 * @param int $post_id Post ID.
	 * @return Config
	 */
	public function get_config( $post_id ) {
		$config = new Config();

		$config->merchant_id = $this->get_meta( $post_id, 'phonepe_merchant_id' );
		$config->salt_key    = $this->get_meta( $post_id, 'phonepe_salt_key' );
		$config->salt_index  = $this->get_meta( $post_id, 'phonepe_salt_index' );

		$config->client_id      = $this->get_meta( $post_id, 'phonepe_client_id' );
		$config->client_secret  = $this->get_meta( $post_id, 'phonepe_client_secret' );
		$config->client_version = $this->get_meta( $post_id, 'phonepe_client_version' );

		$config->mode        = $this->get_meta( $post_id, 'mode' );
		$config->api_version = $this->get_meta( $post_id, 'phonepe_api_version' );

		// Set API version to v1 if salt key is set and client secret is not set.
		if ( ! empty( $config->salt_key ) && empty( $config->client_secret ) ) {
			$config->api_version = 'v1';
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
}
