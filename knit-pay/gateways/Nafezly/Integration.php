<?php

namespace KnitPay\Gateways\Nafezly;

use Pronamic\WordPress\Pay\AbstractGatewayIntegration;
use KnitPay\Gateways\IntegrationModeTrait;

/**
 * Title: Nafezly Payments Integration
 * Copyright: 2020-2025 Knit Pay
 *
 * @author  Knit Pay
 * @version 1.0.0
 * @since   1.0.0
 */
class Integration extends AbstractGatewayIntegration {
	use IntegrationModeTrait;

	/**
	 * @var array
	 */
	private array $args;

	/**
	 * Construct the integration.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'id'            => 'nafezly',
				'name'          => 'Nafezly Payments',
				'provider'      => 'nafezly',
				'nafezly_class' => '',
				'product_url'   => '',
				'beta'          => false,
				'config_keys'   => [],
			]
		);

		if ( key_exists( 'id', $args ) ) {
			$args['id'] = 'nafezly-' . $args['id'];
		}

		if ( isset( $args['beta'] ) && $args['beta'] ) {
			$args['name'] = $args['name'] . ' (Beta)';
		}

		$this->args = $args;

		parent::__construct( $args );
	}

	/**
	 * Get settings fields.
	 *
	 * @return array
	 */
	public function get_settings_fields() {
		$fields = [];

		if ( isset( $this->args['beta'] ) && $this->args['beta'] ) {
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

		// Mode selector (Test / Live).
		$fields[] = $this->get_mode_settings_fields();

		// If specific config keys are passed, show only those.
		$config_keys = ! empty( $this->args['config_keys'] )
			? $this->args['config_keys']
			: [];

		$meta_key_prefix = str_replace( '-', '_', $this->get_id() . '_' );

		foreach ( $config_keys as $key => $def ) {
			// Support both old flat strings and new array definitions.
			if ( is_int( $key ) ) {
				$key = $def;
				$def = [];
			}

			// Skip auto-populated keys (currency / language) — they are injected
			// from the Payment object at runtime, not configured by the admin.
			$auto_keys = [];
			if ( ! empty( $this->args['currency_key'] ) ) {
				$auto_keys[] = $this->args['currency_key'];
			}
			if ( ! empty( $this->args['language_key'] ) ) {
				$auto_keys[] = $this->args['language_key'];
			}
			if ( in_array( $key, $auto_keys, true ) ) {
				continue;
			}

			$title   = $def['title'] ?? $key;
			$default = $def['default'] ?? '';

			$fields[] = [
				'section'  => 'general',
				'meta_key' => '_pronamic_gateway_' . $meta_key_prefix . $key,
				'title'    => $title,
				'type'     => 'text',
				'classes'  => [ 'regular-text', 'code' ],
				'default'  => $default,
			];
		}

		return $fields;
	}

	/**
	 * Get config from post meta.
	 *
	 * @param int $post_id Post ID.
	 * @return Config
	 */
	public function get_config( $post_id ) {
		$config = new Config();

		$meta_key_prefix = str_replace( '-', '_', $this->get_id() . '_' );

		$config_keys = ! empty( $this->args['config_keys'] )
			? $this->args['config_keys']
			: [];

		$merged = [];
		foreach ( $config_keys as $key => $def ) {
			// Support both old flat strings and new array definitions.
			if ( is_int( $key ) ) {
				$key = $def;
				$def = [];
			}

			$val = $this->get_meta( $post_id, $meta_key_prefix . $key );

			if ( '' === $val || is_null( $val ) ) {
				$val = $def['default'] ?? '';
			}

			$merged[ $key ] = $val;
		}

		// Auto-set test/live URL based on chosen mode.
		if ( ! empty( $this->args['url_config_key'] ) ) {
			$url_config_key = $this->args['url_config_key'];
			$mode           = $this->get_meta( $post_id, 'mode' );
			if ( Gateway::MODE_TEST === $mode && ! empty( $this->args['test_url'] ) ) {
				$merged[ $url_config_key ] = $this->args['test_url'];
			} else {
				// Default to live URL (covers unsaved/empty mode too).
				$merged[ $url_config_key ] = $this->args['live_url'] ?? '';
			}
		}

		$config->nafezly_config = $merged;
		$config->mode           = get_post_meta( $post_id, '_pronamic_gateway_mode', true );

		return $config;
	}

	/**
	 * Get gateway.
	 *
	 * @param int $config_id Post ID.
	 * @return Gateway
	 */
	public function get_gateway( $config_id ) {
		$config = $this->get_config( $config_id );

		$gateway = new Gateway();

		$mode = Gateway::MODE_LIVE;
		if ( 'test' === $config->mode ) {
			$mode = Gateway::MODE_TEST;
		}

		$this->set_mode( $mode );
		$gateway->set_mode( $mode );

		$gateway->init( $config, $this->args );

		return $gateway;
	}
}
