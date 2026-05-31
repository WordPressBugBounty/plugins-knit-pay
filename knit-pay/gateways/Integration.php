<?php

namespace KnitPay\Gateways;

use Pronamic\WordPress\Pay\AbstractGatewayIntegration;

/**
 * Title: Base Integration for Gateways
 * Copyright: 2020-2026 Knit Pay
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
		parent::__construct( $args );

		add_action( 'admin_notices', [ $this, 'admin_notice' ] );
	}

	public function admin_notice() {
		$error = get_transient( 'knit_pay_post_save_error_' . $this->get_id() );
		if ( ! empty( $error ) ) {
			delete_transient( 'knit_pay_post_save_error_' . $this->get_id() );
			wp_admin_notice(
				$error,
				[
					'type'        => 'error',
					'dismissible' => true,
				]
			);
			echo '<script>alert("Error: ' . esc_js( $error ) . '");</script>';
		}

		$success = get_transient( 'knit_pay_post_save_success_' . $this->get_id() );
		if ( ! empty( $success ) ) {
			delete_transient( 'knit_pay_post_save_success_' . $this->get_id() );
			wp_admin_notice(
				$success,
				[
					'type'        => 'success',
					'dismissible' => true,
				]
			);
		}
	}

	/**
	 * Store a post-save notice message in transient so it survives redirects.
	 *
	 * @param string $message Message to display.
	 * @param string $type    Notice type: 'error' or 'success'.
	 */
	protected function knit_pay_post_save_notice( $message, $type = 'error' ) {
		if ( 'error' === $type ) {
			set_transient( 'knit_pay_post_save_error_' . $this->get_id(), $message, 60 );
		} else {
			set_transient( 'knit_pay_post_save_success_' . $this->get_id(), $message, 60 );
		}
	}
}
