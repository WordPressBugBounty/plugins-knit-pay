<?php

namespace KnitPay\Gateways;

use Pronamic\WordPress\Pay\AbstractGatewayIntegration;

/**
 * Title: Other Payment Provider Integration
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.79.3.0
 * @since   8.79.3.0
 */
class Integration extends AbstractGatewayIntegration {
	/**
	 * Construct Test integration.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'id'          => 'other',
				'name'        => 'Other Payment Providers',
				'product_url' => KNITPAY_GLOBAL_GATEWAY_LIST_URL,
				'provider'    => 'other',
			]
		);

		parent::__construct( $args );
	}

	/**
	 * Get settings fields.
	 *
	 * @return array
	 */
	public function get_settings_fields() {
		$fields = [];

		if ( ! defined( 'KNIT_PAY_PRO' ) ) {
			$plugins           = get_plugins();
			$knit_pay_pro_base = 'knit-pay-pro/knit-pay-pro.php';

			$plugins = get_plugins();
			if ( isset( $plugins[ $knit_pay_pro_base ] ) ) {
				$url  = esc_url( wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=' . $knit_pay_pro_base ), 'activate-plugin_' . $knit_pay_pro_base ) );
				$link = '<a class="button button-primary" target="_blank" href="' . $url . '">' . __( 'Activate it', 'knit-pay-lang' ) . '</a>';
			} else {
				$url  = esc_url( wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=knit-pay-pro' ), 'install-plugin_knit-pay-pro' ) );
				$link = '<a class="button button-primary" target="_blank" href="' . $url . '">' . __( 'Install it', 'knit-pay-lang' ) . '</a>';
			}

			$fields[] = [
				'section'     => 'general',
				'type'        => 'custom',
				'title'       => 'Install Knit Pay - Pro',
				'description' => '<h1>If the Payment Gateway provider which you want to integrate is not on the list above, try installing Knit Pay - Pro. Now you can use 50+ Knit Pay premium addons with Knit Pay Pro.</h1>'
				. '<br><br>' . $link,
			];

			// Return fields.
			return $fields;
		}

		$fields[] = [
			'section'     => 'general',
			'type'        => 'custom',
			'title'       => 'Contact Us',
			'description' => '<h1>If the Payment Gateway provider which you want to integrate is not on the list above, contact us to learn about the premium addon.</h1>'
			. '<br><br><a class="button button-primary" target="_blank" href="https://www.knitpay.org/contact-us/?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=other-gateways"
		    role="button"><strong>Contact Us</strong></a>',
		];

		// Return fields.
		return $fields;
	}

	/**
	 * Get gateway.
	 *
	 * @param int $post_id Post ID.
	 * @return Gateway
	 */
	public function get_gateway( $config_id ) {
		return new Gateway();
	}
}
