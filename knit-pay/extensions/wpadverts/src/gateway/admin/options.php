<?php
/**
 * Displays Knit Pay Options Page
 *
 * This file is a template for wp-admin / Classifieds / Options / Knit Pay
 *
 * It is being loaded by adext_knit_pay_page_options function.
 *
 * @see adext_knit_pay_page_options()
 * @since 0.1
 */
?>
<div class="wrap">
	<h2 class="">
		<?php echo $payment_method_name; ?>
	</h2>

	<?php adverts_admin_flash(); ?>

	<form action="" method="post" class="adverts-form">
		<table class="form-table">
			<tbody>
			<?php echo adverts_form_layout_config( $form ); ?>
			</tbody>
		</table>

		<p class="submit">
			<input type="submit" value="<?php esc_attr_e( $button_text, 'knit-pay-lang' ); ?>" class="button-primary" name="<?php _e( 'Submit', 'knit-pay-lang' ); ?>" />
		</p>

	</form>

</div>
