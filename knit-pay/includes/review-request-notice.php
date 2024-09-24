<?php

function knit_pay_review_request_admin_notice() {
	if ( get_transient( 'knit_pay_review_request_notice_dismiss' )
		|| knit_pay_get_first_config_time() > strtotime( '-3 months' )
		|| ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$remind_later = wp_nonce_url( add_query_arg( 'knit_pay_rating_notice_action', 'later' ), 'knit_pay_review_request_notice_action_later' ); 
	$no_thanks    = wp_nonce_url( add_query_arg( 'knit_pay_rating_notice_action', 'no_thanks' ), 'knit_pay_review_request_notice_action_no_thanks' );
	$contact_us   = '<a href="https://www.knitpay.org/contact-us/?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=review-support" target="_blank">contact us</a>';

	?>
	<div class="notice notice-success">
		<p><strong>Knit Pay:</strong> Congratulations for completing more than three months with Knit Pay. Could you please spend a moment and write a 5-star review for us? It will motivate us to provide you with even better support in the future.
		If you think we don't deserve a 5-star, <?php echo $contact_us; ?> and share your feedback. We ensure you that we will do our best to get a 5-star from you.</p>
		<p><a href="https://wordpress.org/support/plugin/knit-pay/reviews/?filter=5#new-post" target="_blank" class="button button-primary"><?php _e( 'Rate 5-star', 'knit-pay-lang' ); ?></a>&nbsp;
		   <a href="https://www.knitpay.org/contact-us/?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=review-support" target="_blank" class="button button-primary">Contact Us</a>&nbsp;
		<a href="<?php echo $remind_later; ?>" class="button button-secondary" style="color: white;background: black;">Remind Later</a>&nbsp;
		<a href="<?php echo $no_thanks; ?>" class="button button-secondary" style="color: white;background: green;">Already Reviewed</a></p>
	</div>
	<?php
}
add_action( 'admin_notices', 'knit_pay_review_request_admin_notice' );

function knit_pay_dismiss_review_request_admin_notice() {
	if ( ! filter_has_var( INPUT_GET, 'knit_pay_rating_notice_action' ) ) {
		return;
	}

	$action = filter_input( INPUT_GET, 'knit_pay_rating_notice_action', FILTER_SANITIZE_STRING );
	
	if ( 'later' === $action ) {
		check_admin_referer( 'knit_pay_review_request_notice_action_later' );
		set_transient( 'knit_pay_review_request_notice_dismiss', true, 3 * MONTH_IN_SECONDS );
	} elseif ( 'no_thanks' === $action ) {
		check_admin_referer( 'knit_pay_review_request_notice_action_no_thanks' );
		set_transient( 'knit_pay_review_request_notice_dismiss', true, 3 * YEAR_IN_SECONDS );
	}

	wp_redirect( remove_query_arg( [ 'knit_pay_rating_notice_action', '_wpnonce' ] ) );
	exit;
}
add_action( 'admin_init', 'knit_pay_dismiss_review_request_admin_notice' );

function knit_pay_get_first_config_time() {
	$first_config_time = get_option( 'knit_pay_first_config_time' );
	if ( $first_config_time ) {
		return $first_config_time;
	}

	$args = [
		'post_type'      => 'pronamic_gateway',
		'orderby'        => 'ID',
		'order'          => 'ASC',
		'posts_per_page' => 1,
	];

	$query = new WP_Query( $args );

	$first_config_time = time();
	if ( ! empty( $query->posts ) ) {
		$post = $query->posts[0];
		if ( is_object( $post ) ) {
			$first_config_time = strtotime( $post->post_date );
		}
	}

	update_option( 'knit_pay_first_config_time', $first_config_time );
	return $first_config_time;
}
