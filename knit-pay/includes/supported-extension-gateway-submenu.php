<?php
add_action( 'admin_menu', 'knit_pay_admin_menu', 100 );
add_action( 'admin_footer', 'knit_pay_admin_menu_script' );

/**
 * Add submenu in Knit Pay Menu
 */
function knit_pay_admin_menu() {
	global $submenu;

	$submenu['pronamic_ideal'][] = [ '<div id="kp-menu-supported-ext">Supported Extensions</div>', 'manage_options', 'https://wordpress.org/plugins/knit-pay/#tab-description' ];
	$submenu['pronamic_ideal'][] = [ '<div id="kp-menu-supported-gateway">Supported Gateways</div>', 'manage_options', KNITPAY_GLOBAL_GATEWAY_LIST_URL ];
	$submenu['pronamic_ideal'][] = [ '<div id="kp-menu-support">Support</div>', 'manage_options', 'https://www.knitpay.org/contact-us/?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=admin-menu' ];
	$submenu['pronamic_ideal'][] = [ '<div id="kp-menu-support"><strong>Refer and Earn</strong></div>', 'manage_options', 'https://www.knitpay.org/contact-us/?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=admin-menu' ];
}

function knit_pay_admin_menu_script() {
	?>
	<script type="text/javascript">
		jQuery(document).ready( function($) {
			$('#kp-menu-supported-ext').parent().attr('target','_blank');
			$('#kp-menu-supported-gateway').parent().attr('target','_blank');
			$('#kp-menu-support').parent().attr('target','_blank');
		});
	</script>
	<?php
}
