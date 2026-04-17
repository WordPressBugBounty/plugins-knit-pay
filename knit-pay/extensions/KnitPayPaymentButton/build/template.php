<?php
wp_enqueue_script( 'jquery' );
$form_id             = 'knit-pay-payment-button-form-' . uniqid();
$honeypot_field_name = 'knit_pay_contact_' . substr( md5( $form_id ), 0, 8 );
?>

<form id="<?php echo $form_id; ?>" class="knit-pay-payment-button-form-class" method="post">
	<?php 
		$nonce_action = "knit_pay_payment_button|{$attributes['amount']}|{$attributes['currency']}|{$attributes['payment_description']}|{$attributes['config_id']}|{$honeypot_field_name}";
		wp_nonce_field( $nonce_action, 'knit_pay_nonce' );
	?>

	<!-- Honeypot field to catch bots (hidden from users) -->
	<input type="text" name="<?php echo esc_attr( $honeypot_field_name ); ?>" id="<?php echo esc_attr( $honeypot_field_name ); ?>" value="" style="position: absolute; left: -9999px; width: 1px; height: 1px;" tabindex="-1" autocomplete="off">
	<input type="hidden" name="knit_pay_honeypot_field" value="<?php echo esc_attr( $honeypot_field_name ); ?>">

	<input type="hidden" id="knit_pay_payment_button_amount" value="<?php echo esc_attr( $attributes['amount'] ); ?>">
	<input type="hidden" id="knit_pay_payment_button_currency" value="<?php echo esc_attr( $attributes['currency'] ); ?>">
	<input type="hidden" id="knit_pay_payment_button_payment_description" value="<?php echo esc_attr( $attributes['payment_description'] ); ?>">
	<input type="hidden" id="knit_pay_payment_button_config_id" value="<?php echo esc_attr( $attributes['config_id'] ); ?>">
	<input
		type="submit"
		id="knit-pay-payment-button-submit"
		value="<?php echo esc_attr( $attributes['text'] ); ?>"
		knit-pay-button-text="<?php echo esc_attr( $attributes['text'] ); ?>"
		<?php echo get_block_wrapper_attributes( [ 'class' => 'wp-block-button__link wp-element-button knit-pay-payment-button-submit-class' ] ); ?>
	></input>
</form>
