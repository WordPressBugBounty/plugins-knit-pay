<?php
/**
 * Admin View: Notice - Deprecated Razorpay API Keys
 *
 * @author    Knit Pay
 * @copyright 2020-2024 Knit Pay
 * @license   GPL-3.0-or-later
 */

use Pronamic\WordPress\Pay\Admin\AdminGatewayPostType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get Razorpay config IDs without company name.
$config_ids = get_transient( 'knit_pay_upi_supported_configs' );

if ( ! is_array( $config_ids ) ) {
	return;
}

// Build gateways list.
$gateways = [];

foreach ( $config_ids as $config_id ) :
	if ( AdminGatewayPostType::POST_TYPE !== get_post_type( $config_id ) ) {
		continue;
	}

	$gateways[] = sprintf(
		'<a href="%1$s" title="%2$s">%2$s</a>',
		get_edit_post_link( $config_id ),
		get_the_title( $config_id )
	);

endforeach;

// Don't show notice if non of the gateways exists.
if ( empty( $gateways ) ) {
	// Delete transient.
	delete_transient( 'knit_pay_upi_supported_configs' );

	return;
}

?>
<div class="notice notice-warning">
	<p>
		<strong><?php esc_html_e( 'Knit Pay', 'knit-pay-lang' ); ?></strong> —
		<?php

		$message = sprintf(
			/* translators: 1: configuration link(s) */
			_n(
				'Exciting News! You no longer have to manually check the payment status of your UPI/QR payments for configuration %s. Knit Pay can now check the payment status automatically for you.',
				'Exciting News! You no longer have to manually check the payment status of your UPI/QR payments for configurations %s. Knit Pay can now check the payment status automatically for you.',
				count( $config_ids ),
				'knit-pay'
			),
			implode( ', ', $gateways ) // WPCS: xss ok.
		);

		$contact_us = ' <a target="_blank" href="' . admin_url( 'plugin-install.php?s=Knit%2520Pay%2520UPI%2520QR%2520code%2520RapidAPI&tab=search&type=term' ) . '">Unlock this feature now! Click here.</a>';

		echo wp_kses(
			$message . $contact_us,
			[
				'a' => [
					'href'   => true,
					'target' => true,
				],
			]
		);

		?>
	</p>
</div>
