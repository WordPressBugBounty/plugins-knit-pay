<?php
namespace KnitPay\Extensions\KnitPayPaymentLink;

/**
 * Knit Pay - Payment Link - Create Payment Link Page
 *
 * @author    Knit Pay
 * @copyright 2020-2024 Knit Pay
 * @license   GPL-3.0-or-later
 * @package   KnitPay\Gateways\KnitPayPaymentLink
 */

?>

<div class="wrap pronamic-pay-settings">
	<h1 class="wp-heading-inline"><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<hr class="wp-header-end">

	<form id="create-payment-link" action="<?php echo esc_url( \admin_url( 'admin.php?page=knit_pay_payment_link' ) ); ?>" method="post">
		<?php
			// Create nonce.
			$rand = uniqid( 'knit_pay_payment_link_rand_' );
			wp_nonce_field( 'knit_pay_create_payment_link|' . $rand, 'knit_pay_nonce' );
			echo '<input type="hidden" id="knit_pay_payment_link_rand" value="' . $rand . '" />';

			// Display Payment link form and submit Button.
			do_settings_sections( 'knit_pay_payment_link' );
			submit_button( 'Create Payment Link' );
		?>
	</form>

	<script>
		jQuery("#create-payment-link").submit(function(event) {
			event.preventDefault();

			jQuery("#create-payment-link-notice").remove();

			var submit_button = jQuery("#create-payment-link #submit");
			submit_button.attr("disabled", "");
			submit_button.val("Loading...");

			jQuery.get(ajaxurl, {
					"action": "knit_pay_create_payment_link",
					"knit_pay_nonce": jQuery("#knit_pay_nonce").val(),
					"currency": jQuery("#knit_pay_payment_link_currency").val(),
					"amount": jQuery("#knit_pay_payment_link_amount").val(),
					"payment_description": jQuery("#knit_pay_payment_link_payment_description").val(),
					"payment_ref_id": jQuery("#knit_pay_payment_link_payment_ref_id").val(),
					"customer_name": jQuery("#knit_pay_payment_link_customer_name").val(),
					"customer_email": jQuery("#knit_pay_payment_link_customer_email").val(),
					"customer_phone": jQuery("#knit_pay_payment_link_customer_phone").val(),
					"config_id": jQuery("#knit_pay_payment_link_config_id").val(),
					"rand": jQuery("#knit_pay_payment_link_rand").val(),
				},
				function(msg) {
					submit_button.removeAttr("disabled");
					submit_button.val("Create Payment Link");

					if (msg.success) {
						alert("Payment Link Generated");
						jQuery("#create-payment-link").after('<div id="create-payment-link-notice" class="notice notice-success"><p>Payment Link Generated:<br><strong>' + msg.data + '</strong></p></div>');
						jQuery('html, body').animate({
							scrollTop: jQuery("#create-payment-link-notice").offset().top
						}, 2000);
					} else {
						alert(msg.data);
					}
				});
		});
	</script>
</div>
