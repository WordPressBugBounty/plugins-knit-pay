<?php
/**
 * Redirect via HTML
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

		<title><?php echo $payment_page_title; ?></title>

		<?php wp_print_styles( 'pronamic-pay-redirect' ); ?>

		<?php

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
		<div class="pronamic-pay-redirect-page">
			<div class="pronamic-pay-redirect-container">
				<h1><?php echo $payment_page_title; ?></h1>

				<p>
					<?php echo $payment_page_description; ?>
				</p>

				<div class="pp-page-section-container">
					<div class="pp-page-section-wrapper">

						<?php $this->output_form( $payment ); ?>
					</div>
				</div>

				<div class="pp-page-section-container">
					<div class="pp-page-section-wrapper alignleft">
						<h2><?php esc_html_e( 'Payment', 'knit-pay-lang' ); ?></h2>

						<dl>
							<dt><?php esc_html_e( 'Date', 'knit-pay-lang' ); ?></dt>
							<dd><?php echo esc_html( $payment->get_date()->format_i18n() ); ?></dd>

							<?php $transaction_id = $payment->get_transaction_id(); ?>

							<?php if ( ! empty( $transaction_id ) ) : ?>

								<dt><?php esc_html_e( 'Transaction ID', 'knit-pay-lang' ); ?></dt>
								<dd><?php echo esc_html( $transaction_id ); ?></dd>

							<?php endif; ?>

							<dt><?php esc_html_e( 'Description', 'knit-pay-lang' ); ?></dt>
							<dd><?php echo esc_html( (string) $payment->get_description() ); ?></dd>

							<dt><?php esc_html_e( 'Amount', 'knit-pay-lang' ); ?></dt>
							<dd><?php echo esc_html( $payment->get_total_amount()->format_i18n() ); ?></dd>
						</dl>
					</div>
				</div>
			</div>
		</div>
	</body>
</html>
