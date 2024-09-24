<?php
/**
 * UPI Payment Page
 *
 * @author    Knit Pay
 * @copyright 2020-2024 Knit Pay
 * @license   GPL-3.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<!DOCTYPE html>

<html <?php language_attributes(); ?>>
	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>" />

		<meta name="viewport" content="width=device-width, initial-scale=1.0">

		<meta http-equiv = "refresh" content = "300; url = <?php echo add_query_arg( 'status', 'Expired', $payment->get_return_url() ); ?>" />

		<title><?php esc_html_e( 'Payment Page', 'knit-pay-lang' ); ?></title>

		<?php 
			wp_enqueue_scripts();
			wp_print_scripts( "knit-pay-upi-qr-template-{$this->config->payment_template}" );
			wp_print_styles( "knit-pay-upi-qr-template-{$this->config->payment_template}" );
		?>

		<?php
		
		$transaction_id = $payment->get_transaction_id();
		
		$amount                  = $payment->get_total_amount()->format_i18n( '%1$s%2$s' );
		$redirect_url            = $payment->get_return_url();
		$image_path              = KNIT_PAY_UPI_QR_IMAGE_URL;
		$hide_pay_button         = $this->config->hide_pay_button; // TODO make dynamic
		$show_download_qr_button = 'yes' === $this->config->show_download_qr_button && 2000 >= $payment->get_total_amount()->number_format( null, '.', '' );

		if ( ! wp_is_mobile() ) {
			$hide_pay_button = true;
		}

		$intent_url_parameters = $this->get_intent_url_parameters( $payment );
		$upi_qr_text           = $this->get_upi_qr_text( $payment );
		$payee_name            = rawurldecode( $intent_url_parameters['pn'] );
		
		$nonce_action = 'knit_pay_payment_status_check|' . $payment->get_id() . "|$transaction_id";
		echo wp_nonce_field( $nonce_action, 'knit_pay_nonce', true, true );

		/**
		 * Break out of iframe.
		 * 
		 * @link https://github.com/pronamic/wp-pronamic-pay-give/issues/2
		 * @link https://github.com/pronamic/wp-pronamic-pay/commit/6936ec048c6778e688386d3c15f6a6c1cbaa8eb9
		 */

		?>
		<script>
			if ( window.top.location !== window.location ) {
				window.top.location = window.location;
			}
		</script>
	</head>

	<body>
		<input type='hidden' id='upi_qr_text' value='<?php echo $upi_qr_text; ?>'>
		<input type='hidden' id='image_dir_path' value='<?php echo KNIT_PAY_UPI_QR_IMAGE_URL; ?>'>
		<input type='hidden' name='knit_pay_transaction_id' value='<?php echo $transaction_id; ?>'>
		<input type='hidden' name='knit_pay_payment_id' value='<?php echo $payment->get_id(); ?>'>
		
		<form id='formSubmit' action='<?php echo $redirect_url; ?>' method='post' style='display: none;'>
			<input type='hidden' name='status' value='Success'>
		</form>

		<?php 
			require_once "template{$this->config->payment_template}.php"; 
		?>
	</body>
</html>
