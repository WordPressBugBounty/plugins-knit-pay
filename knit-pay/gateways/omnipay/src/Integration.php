<?php

namespace KnitPay\Gateways\Omnipay;

use Pronamic\WordPress\Pay\AbstractGatewayIntegration;
use Pronamic\WordPress\Pay\Core\IntegrationModeTrait;
use Pronamic\WordPress\Pay\Payments\Payment;
use Omnipay\Omnipay;
use KnitPay\Utils as KnitPayUtils;

/**
 * Title: Omnipay Integration
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.72.0.0
 * @since   8.72.0.0
 */
class Integration extends AbstractGatewayIntegration {
	use IntegrationModeTrait;
	
	private $omnipay_gateway;

	private $args;

	/**
	 * Construct Omnitpay integration.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = [] ) {
		$this->omnipay_gateway = Omnipay::create( $args['omnipay_class'] );
		$this->args            = $args;

		$args = wp_parse_args(
			$args,
			[
				'name' => $this->omnipay_gateway->getName(),
			]
		);

		if ( key_exists( 'id', $args ) ) {
			$args['id'] = 'omnipay-' . $args['id'];
		}

		if ( isset( $args['beta'] ) ) {
			$args['name'] = $args['name'] . ' (Beta)';
		}

		if ( isset( $this->args['accept_notification'] ) ) {
			$args['supports'] = [
				'webhook',
				'webhook_log',
			];
		}

		parent::__construct( $args );
	}
	
	/**
	 * Setup.
	 */
	public function setup() {
		$rest_controller = new RestController( $this, $this->args );

		$rest_controller->setup();
	}

	/**
	 * Get settings fields.
	 *
	 * @return array
	 */
	public function get_settings_fields() {
		$config_id = KnitPayUtils::get_gateway_config_id();
		$fields    = [];
		
		if ( isset( $this->args['beta'] ) ) {
			$fields[] = [
				'section'  => 'general',
				'title'    => __( 'Please Note', 'knit-pay-lang' ),
				'type'     => 'custom',
				'callback' => function () {
					printf(
						'⚠️ %s',
						esc_html__(
							'This gateway integration is currently in beta, which means you might encounter some issues while using it. We recommend thoroughly testing it before going live. If you encounter any problems, feel free to reach out to Knit Pay support.',
							'knit-pay-lang'
						)
					);
				},
			];
		}

		$params = $this->get_omnipay_params();
		
		$meta_key_prefix = str_replace( '-', '_', '_pronamic_gateway_' . $this->get_id() . '_' );

		foreach ( $params as $parm => $val ) {
			switch ( getType( $val ) ) {
				case 'boolean':
					$type = 'checkbox';
					break;
				case 'string':
				default:
					$type = 'text';
					break;
			}
			$fields[] = [
				'section'  => 'general',
				'meta_key' => $meta_key_prefix . $parm,
				'title'    => $parm,
				'label'    => $parm,
				'type'     => $type,
				'classes'  => [ 'regular-text', 'code' ],
				'default'  => $val,

			];
		}
		
		// Some gateways requires static return URL.
		if ( isset( $this->args['static_return_url'] ) ) {
			// Static Return URL.
			$fields[] = [
				'section'  => 'general',
				'title'    => __( 'Static Return URL', 'knit-pay-lang' ),
				'type'     => 'text',
				'classes'  => [ 'large-text', 'code' ],
				'value'    => \rest_url( '/knit-pay/' . $this->get_id() . '/v1/return/' . \wp_hash( home_url( '/' ) ) . '/' . $config_id . '/' ),
				'readonly' => true,
			];
		}

		// Notification URL.
		if ( isset( $this->args['accept_notification'] ) ) {
			$fields[] = [
				'section'  => 'feedback',
				'title'    => __( 'Notification URL', 'knit-pay-lang' ),
				'type'     => 'text',
				'classes'  => [ 'large-text', 'code' ],
				'value'    => \rest_url( '/knit-pay/' . $this->get_id() . '/v1/notification/' . \wp_hash( home_url( '/' ) ) . '/' . $config_id . '/' ),
				'readonly' => true,
			];
		}
		
		return $fields;
	}

	public function get_config( $post_id ) {
		$config = [];

		$params = $this->get_omnipay_params();

		$meta_key_prefix = str_replace( '-', '_', $this->get_id() . '_' );

		foreach ( $params as $parm => $val ) {
			$config[ $parm ] = $this->get_meta( $post_id, $meta_key_prefix . $parm );

			if ( '' === $config[ $parm ] ) {
				$config[ $parm ] = $val;
			}
		}

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
		$gateway = new Gateway();

		$this->omnipay_gateway->initialize( $config );

		$mode = Gateway::MODE_LIVE;
		if ( $config['testMode'] ) {
			$mode = Gateway::MODE_TEST;
		}

		$this->set_mode( $mode );
		$gateway->set_mode( $mode );

		$gateway->init( $this->omnipay_gateway, $config, $this->args );

		return $gateway;
	}
	
	private function get_omnipay_params() {
		$params             = isset( $this->args['default_parameters'] ) ? $this->args['default_parameters'] : [];
		$params             = wp_parse_args(
			$params,
			$this->omnipay_gateway->getParameters()
		);
		$params['testMode'] = false;
		return $params;
	}
}
