<?php

// Show Error If no configuration Found
function knit_pay_admin_google_workspace_price_hike_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( get_transient( 'knit_pay_google_workspace_price_hike_notice_dismiss' ) ) {
		return;
	}

	$is_google_workspace = get_transient( 'knit_pay_is_google_workspace' );

	if ( empty( $is_google_workspace ) ) {
		$is_google_workspace = 'no';
		$host_name           = knitpay_getDomain( $_SERVER['HTTP_HOST'] );
		if ( ! getmxrr( $host_name, $mx_details ) ) {
			set_transient( 'knit_pay_is_google_workspace', $is_google_workspace, WEEK_IN_SECONDS );
			return;
		}

		foreach ( $mx_details as $mx ) {
			if ( false !== strpos( $mx, 'google' ) ) {
				$is_google_workspace = 'yes';
				break;
			}
		}
		set_transient( 'knit_pay_is_google_workspace', $is_google_workspace, WEEK_IN_SECONDS );
	}

	if ( 'no' === $is_google_workspace ) {
		return;
	}

	$remind_later = wp_nonce_url( add_query_arg( 'knit_pay_google_workspace_price_hike_notice_action', 'later' ), 'knit_pay_google_workspace_price_hike_notice_action_later' );
	$no_thanks    = wp_nonce_url( add_query_arg( 'knit_pay_google_workspace_price_hike_notice_action', 'no_thanks' ), 'knit_pay_google_workspace_price_hike_notice_action_no_thanks' ); ?>

	<div class="notice notice-warning">
		<p><strong>Knit Pay:</strong> <?php _e( 'Do you know? Google Workspace prices are getting revised from 10 April 2023. We are an official Google Cloud partner and can help you renew your Google Workspace subscription in advance with upto a 50% discount on the current price. Contact us to know more.', 'knit-pay-lang' ); ?></p>
		<p><a href="https://host.thearrangers.xyz/g-suite?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=google-workspace-notice" target="_blank" class="button button-primary">Visit Website</a>&nbsp;
		<a href="https://www.knitpay.org/contact-us/?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=google-workspace-notice" target="_blank" class="button button-primary">Contact Us</a>&nbsp;
		<a href="<?php echo $remind_later; ?>" class="button button-secondary" style="color: white;background: black;">Remind Later</a>&nbsp;
		<a href="<?php echo $no_thanks; ?>" class="button button-secondary" style="color: white;background: darkred;">Don't Remind</a></p>
	</div>

	<?php
}
add_action( 'admin_notices', 'knit_pay_admin_google_workspace_price_hike_notice' );

function knit_pay_google_workspace_price_hike_notice_action_listener() {    
	if ( ! filter_has_var( INPUT_GET, 'knit_pay_google_workspace_price_hike_notice_action' ) ) {
		return;
	}

	$action = filter_input( INPUT_GET, 'knit_pay_google_workspace_price_hike_notice_action', FILTER_SANITIZE_STRING );

	if ( 'later' === $action ) {
		check_admin_referer( 'knit_pay_google_workspace_price_hike_notice_action_later' );
		set_transient( 'knit_pay_google_workspace_price_hike_notice_dismiss', true, WEEK_IN_SECONDS );
	} elseif ( 'no_thanks' === $action ) {
		check_admin_referer( 'knit_pay_google_workspace_price_hike_notice_action_no_thanks' );
		set_transient( 'knit_pay_google_workspace_price_hike_notice_dismiss', true, 100 * YEAR_IN_SECONDS );
	}

	wp_redirect( remove_query_arg( [ 'knit_pay_google_workspace_price_hike_notice_action', '_wpnonce' ] ) );
	exit;
}
add_action( 'admin_init', 'knit_pay_google_workspace_price_hike_notice_action_listener' );
