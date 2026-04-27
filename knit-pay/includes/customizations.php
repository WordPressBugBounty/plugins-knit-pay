<?php

/**
 * Auto-generate title for pronamic_gateway posts if title is empty or "Auto Draft",
 * using the gateway integration’s display name and appending the post ID for uniqueness.
 */
add_action( 'save_post_pronamic_gateway', 'knit_pay_auto_generate_gateway_title', 99 );
function knit_pay_auto_generate_gateway_title( $post_id ) {
	// Skip autosaves and revisions.
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}

	$post = get_post( $post_id );
	if ( ! $post || 'trash' === $post->post_status ) {
		return;
	}

	$title = get_the_title( $post_id );
	if ( ! empty( $title ) && 'Auto Draft' !== $title ) {
		return;
	}

	$gateway_id = get_post_meta( $post_id, '_pronamic_gateway_id', true );
	if ( empty( $gateway_id ) ) {
		return;
	}

	$plugin = pronamic_pay_plugin();
	if ( null === $plugin ) {
		return;
	}

	$integration = $plugin->gateway_integrations->get_integration( $gateway_id );
	if ( null === $integration ) {
		return;
	}

	$post_title = $integration->get_name();
	if ( empty( $post_title ) ) {
		$post_title = __( 'Payment Gateway', 'knit-pay-lang' );
	}

	// Append post ID to make the title unique.
	$post_title = $post_title . ' #' . $post_id;

	remove_action( 'save_post_pronamic_gateway', 'knit_pay_auto_generate_gateway_title', 99 );

	wp_update_post(
		[
			'ID'         => $post_id,
			'post_title' => $post_title,
		]
	);

	add_action( 'save_post_pronamic_gateway', 'knit_pay_auto_generate_gateway_title', 99 );
}
