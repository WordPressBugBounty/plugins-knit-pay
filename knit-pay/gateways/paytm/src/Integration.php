<?php

namespace KnitPay\Gateways\Paytm;

use Pronamic\WordPress\Pay\AbstractGatewayIntegration;
use Pronamic\WordPress\Pay\Core\IntegrationModeTrait;

/**
 * Title: Paytm Integration
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 4.9.0
 * @since   4.9.0
 */
class Integration extends AbstractGatewayIntegration {
	use IntegrationModeTrait;
	/**
	 * Construct Paytm integration.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'id'          => 'paytm',
				'name'        => 'Paytm',
				'product_url' => 'http://go.thearrangers.xyz/paytm?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=product-url',
				'provider'    => 'paytm',
			]
		);

		parent::__construct( $args );
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
			'meta_key' => '_pronamic_gateway_paytm_merchant_id',
			'title'    => __( 'Merchant ID', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'required' => true,
		];

		// Merchant Key.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_paytm_merchant_key',
			'title'    => __( 'Merchant Key', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'required' => true,
		];

		// Website.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_paytm_website',
			'title'    => __( 'Website', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'required' => true,
		];

		// Paytm Order ID Format.
		$fields[] = [
			'section'     => 'general',
			'meta_key'    => '_pronamic_gateway_order_id_format',
			'title'       => __( 'Order ID Format', 'knit-pay-lang' ),
			'type'        => 'text',
			'classes'     => [ 'large-text', 'code' ],
			'default'     => '{transaction_id}',
			'description' => $this->fields_description(),
		];

		// Expire Old Pending Payments.
		$fields[] = [
			'section'     => 'advanced',
			'filter'      => FILTER_VALIDATE_BOOLEAN,
			'meta_key'    => '_pronamic_gateway_paytm_expire_old_payments',
			'title'       => __( 'Expire Old Pending Payments', 'knit-pay-lang' ),
			'type'        => 'checkbox',
			'description' => 'If this option is enabled, 24 hours old pending payments will be marked as expired in Knit Pay.',
			'label'       => __( 'Mark old pending Payments as expired in Knit Pay.', 'knit-pay-lang' ),
		];

		// Return fields.
		return $fields;
	}

	public function get_config( $post_id ) {
		$config = new Config();

		$config->merchant_id         = $this->get_meta( $post_id, 'paytm_merchant_id' );
		$config->merchant_key        = $this->get_meta( $post_id, 'paytm_merchant_key' );
		$config->website             = $this->get_meta( $post_id, 'paytm_website' );
		$config->expire_old_payments = $this->get_meta( $post_id, 'paytm_expire_old_payments' );
		$config->order_id_format     = $this->get_meta( $post_id, 'order_id_format' );
		$config->mode                = $this->get_meta( $post_id, 'mode' );

		if ( empty( $config->order_id_format ) ) {
			$config->order_id_format = '{transaction_id}';
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
		
		$mode = Gateway::MODE_LIVE;
		if ( Gateway::MODE_TEST === $config->mode ) {
			$mode = Gateway::MODE_TEST;
		}
		
		$this->set_mode( $mode );
		$gateway->set_mode( $mode );
		$gateway->init( $config );
		
		return $gateway;
	}

	private function fields_description() {
		return sprintf( __( 'Available tags: %s', 'knit-pay-lang' ), sprintf( '<code>%s</code> <code>%s</code> <code>%s</code>', '{transaction_id}', '{payment_description}', '{order_id}' ) );
	}

	/**
	 * Save post.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save_post( $config_id ) {
		$value = array_key_exists( '_pronamic_gateway_paytm_merchant_key', $_POST ) ? \wp_kses_post( \wp_unslash( $_POST['_pronamic_gateway_paytm_merchant_key'] ) ) : '';
		\update_post_meta( $config_id, '_pronamic_gateway_paytm_merchant_key', $value );
	}
}
