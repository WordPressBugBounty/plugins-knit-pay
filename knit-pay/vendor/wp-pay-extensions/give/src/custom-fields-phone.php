<?php
//namespace Pronamic\WordPress\Pay\Extensions\Give;

/**
 * Custom Form Fields in Donation form
 *
 * @param $form_id
 */
function knitpay_give_donations_custom_form_fields( $form_id ) {
	
	// Get user info.
	$give_user_info = _give_get_prefill_form_field_values( $form_id );
	$phone          = empty( $give_user_info['give_phone'] ) ? '' : $give_user_info['give_phone'];
	
	?>
		<p id="give-phone-wrap" class="form-row form-row-wide">
			<label class="give-label" for="give-phone">
				<?php esc_attr_e( 'Phone Number', 'give' ); ?>
				<?php if ( give_field_is_required( 'give_phone', $form_id ) ) : ?>
					<span class="give-required-indicator">*</span>
				<?php endif ?>
				<?php echo Give()->tooltips->render_help( __( 'Please provide correct phone or mobile number.', 'give' ) ); ?>
			</label>

			<input
				class="give-input required"
				type="tel"
				name="give_phone"
				autocomplete="phone"
				placeholder="<?php esc_attr_e( 'Phone Number', 'give' ); ?>"
				id="give-phone"
				value="<?php echo esc_html( $phone ); ?>"
				<?php echo( give_field_is_required( 'give_phone', $form_id ) ? ' required aria-required="true" ' : '' ); ?>
			/>
			
		</p>
		<?php
}

add_action( 'give_donation_form_after_email', 'knitpay_give_donations_custom_form_fields' );

/**
 * Require custom field "Phone Number" field.
 *
 * @param $required_fields
 * @param $form_id
 *
 * @return array
 */
function knitpay_give_donations_require_fields( $required_fields, $form_id ) {

		$required_fields['give_phone'] = array(
			'error_id'      => 'give_phone',
			'error_message' => __( 'Please provide correct phone or mobile number.', 'give' ),
		);

		return $required_fields;
}

add_filter( 'give_donation_form_required_fields', 'knitpay_give_donations_require_fields', 10, 2 );


/**
 * Add Field to Payment Meta
 *
 * Store the custom field data custom post meta attached to the `give_payment` CPT.
 *
 * @param $payment_id
 *
 * @return mixed
 */
function knitpay_give_donations_save_custom_fields( $payment_id ) {

	if ( isset( $_POST['give_phone'] ) ) {
		$message = wp_strip_all_tags( $_POST['give_phone'], true );
		give_update_payment_meta( $payment_id, 'give_phone', $message );
	}

}

add_action( 'give_insert_payment', 'knitpay_give_donations_save_custom_fields' );

/**
 * Show Data in Transaction Details
 *
 * Show the custom field(s) on the transaction page.
 *
 * @param $payment_id
 */
function knitpay_give_donations_donation_details( $payment_id ) {

	$phone = give_get_meta( $payment_id, 'give_phone', true );

	if ( $phone ) : 
		?>
	
		<div class="column">
			<p><strong><?php esc_html_e( 'Phone Number:', 'give' ); ?></strong>
			<?php echo wpautop( $phone ); ?></p>
		</div>

		<?php 
	endif;

}

add_action( 'give_payment_view_details', 'knitpay_give_donations_donation_details', 10, 1 );


/**
 * Get Donation Referral Data
 *
 * Example function that returns Custom field data if present in payment_meta;
 * The example used here is in conjunction with the Give documentation tutorials.
 *
 * @param array $tag_args Array of arguments
 *
 * @return string
 */
function knitpay_donation_referral_data( $tag_args ) {
	$phone = give_get_meta( $tag_args['payment_id'], 'give_phone', true );

	$output = __( 'No referral data found.', 'give' );

	if ( ! empty( $phone ) ) {
		$output = wp_kses_post( $phone );
	}

	return $output;
}

/**
 * Adds a Custom "Phone Number" Tag
 *
 * This function creates a custom Give email template tag.
 */
function knitpay_add_sample_referral_tag() {
	give_add_email_tag( 'give_phone', 'This outputs the Phone Number', 'knitpay_donation_referral_data' );
}

add_action( 'give_add_email_tags', 'knitpay_add_sample_referral_tag' );

/**
 * Add Donation Phone Number fields.
 *
 * @params array    $args
 * @params int      $donation_id
 * @params int      $form_id
 *
 * @return array
 */
function knitpay_donation_receipt_args( $args, $donation_id, $form_id ) {

		$phone              = give_get_meta( $donation_id, 'give_phone', true );
		$args['give_phone'] = array(
			'name'    => __( 'Phone Number', 'give' ),
			'value'   => wp_kses_post( $phone ),
			// Do not show Phone field if empty
			'display' => empty( $phone ) ? false : true,
		);

		return $args;
}

add_filter( 'give_donation_receipt_args', 'knitpay_donation_receipt_args', 30, 3 );


/**
 * Add Donation Phone Number fields in export donor fields tab.
 */
function knitpay_donation_standard_donor_fields() {
	?>
	<li>
		<label for="give-phone">
			<input type="checkbox" checked
				   name="give_give_donations_export_option[give_phone]"
				   id="give-phone"><?php _e( 'Phone Number', 'give' ); ?>
		</label>
	</li>
	<?php
}

add_action( 'give_export_donation_standard_donor_fields', 'knitpay_donation_standard_donor_fields' );


/**
 * Add Donation Phone Number header in CSV.
 *
 * @param array $cols columns name for CSV
 *
 * @return  array $cols columns name for CSV
 */
function knitpay_update_columns_heading( $cols ) {
	if ( isset( $cols['give_phone'] ) ) {
		$cols['give_phone'] = __( 'Phone Number', 'give' );
	}

	return $cols;

}

add_filter( 'give_export_donation_get_columns_name', 'knitpay_update_columns_heading' );


/**
 * Add Donation Phone Number fields in CSV.
 *
 * @param array Donation data.
 * @param Give_Payment $payment Instance of Give_Payment
 * @param array $columns Donation data $columns that are not being merge
 *
 * @return array Donation data.
 */
function knitpay_export_donation_data( $data, $payment, $columns ) {
	if ( ! empty( $columns['give_phone'] ) ) {
		$phone              = $payment->get_meta( 'give_phone' );
		$data['give_phone'] = isset( $phone ) ? wp_kses_post( $phone ) : '';
	}

	return $data;
}

add_filter( 'give_export_donation_data', 'knitpay_export_donation_data', 10, 3 );

/**
 * Remove Custom meta fields from Export donation standard fields.
 *
 * @param array $responses Contain all the fields that need to be display when donation form is display
 * @param int $form_id Donation Form ID
 *
 * @return array $responses
 */
function knitpay_export_custom_fields( $responses, $form_id ) {

	if ( ! empty( $responses['standard_fields'] ) ) {
		$standard_fields = $responses['standard_fields'];
		if ( in_array( 'give_phone', $standard_fields ) ) {
			$standard_fields              = array_diff( $standard_fields, array( 'give_phone' ) );
			$responses['standard_fields'] = $standard_fields;
		}
	}

	return $responses;
}

add_filter( 'give_export_donations_get_custom_fields', 'knitpay_export_custom_fields', 10, 2 );
