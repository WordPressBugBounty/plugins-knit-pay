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

		<title><?php esc_html_e( 'Payment Page', 'knit-pay-lang' ); ?></title>

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
		<?php $this->output_form( $payment ); ?>
	</body>
</html>
