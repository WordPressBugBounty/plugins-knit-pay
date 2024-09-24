<?php
/**
 * Admin View: Notice - Knit Pay Pro Required.
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
$config_ids = get_transient( 'knit_pay_payu_missing_knit_pay_pro_configs' );

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
	delete_transient( 'knit_pay_payu_missing_knit_pay_pro_configs' );

	return;
}

// Get the current date
$current_date = new DateTime();

$support_end_date = new DateTime( '2024-06-01' );
$grace_end_date   = new DateTime( '2024-06-30' );

// Compare the dates
if ( $support_end_date > $current_date ) {
	$pre_message = 'PayU will not be supported in Knit Pay after 1 June 2024.';
} elseif ( $grace_end_date > $current_date ) {
	$pre_message = 'As announced on 11 May 2024, support of PayU is ended in Knit Pay on 1 June 2024. You are now in grace period which will end on 30 June 2024. After grace period ends, you will not be able to receive new payments in PayU using Knit Pay.';
} else {
	$pre_message = 'As announced on 11 May 2024, support of PayU is ended in Knit Pay. You will not be able to receive new payments in PayU using Knit Pay.';
}

?>
<div class="notice notice-error">
	<p>
		<strong><?php esc_html_e( 'Knit Pay', 'knit-pay-lang' ); ?></strong> —
		<?php

		$message = sprintf(
			/* translators: 1: configuration link(s) */
			_n(
				'Please click on the PayU configuration %s to know more about the changes. If you are not using this configuration, kindly delete this configuration from Knit Pay to close this error message.',
				'Please click on the PayU configurations %s to know more about the changes. If you are not using any of these configurations, kindly delete these configurations from Knit Pay to close this error message.',
				count( $config_ids ),
				'knit-pay'
			),
			implode( ', ', $gateways ) // WPCS: xss ok.
		);

		echo wp_kses(
			$pre_message . ' ' . $message,
			[
				'a' => [
					'href'  => true,
					'title' => true,
				],
			]
		);

		?>
	</p>
</div>
