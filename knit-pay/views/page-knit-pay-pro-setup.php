<?php
/**
 * Knit Pay Pro - Set Up Page
 *
 * @author    Knit Pay
 * @copyright 2020-2023 Knit Pay
 * @license   GPL-3.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( isset( $_GET['settings-updated'] ) ) {
	// add settings saved message with the class of "updated"
	add_settings_error( 'wporg_messages', 'wporg_message', __( 'Settings Saved', 'knit-pay-pro' ), 'updated' );
}

// show error/update messages
settings_errors( 'wporg_messages' );

?>

<div class="wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<hr class="wp-header-end">

	<form action="options.php" method="post">
		<?php
			settings_fields( 'knit_pay_pro_setup_page' );

			do_settings_sections( 'knit_pay_pro_setup_page' );

			submit_button();
		?>
	</form>
</div>
