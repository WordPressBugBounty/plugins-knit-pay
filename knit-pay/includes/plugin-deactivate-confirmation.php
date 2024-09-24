<?php
// TODO improve it WTE
add_action( 'admin_footer', 'knitpay_enqueue_scripts_deactivate_confirm', 30 );

/**
 * Show confirmation popup box if user tries to deactivate Plugin
 */
function knitpay_enqueue_scripts_deactivate_confirm() {
	$current_screen = get_current_screen();
	if ( 'plugins' !== $current_screen->id ) {
		return;
	}
	wp_enqueue_script(
		'jquery-ui-dialog',
		'',
		[
			'jquery',
			'jquery-ui',
		]
	);
	wp_enqueue_style( 'wp-jquery-ui-dialog' );

	echo '
<div id="knitpay-deactivation-confirm"
	style="max-width: 800px;display: none;">
	<p>
		<span class="dashicons dashicons-warning"
			style="float: left; margin: 12px 12px 20px 0;"></span>If you need help with setup, customization, or anything else, kindly contact us on Whatsapp at +917738456813.
	</p>
</div>
<script>
window.onload = function () {
    jQuery(\'#deactivate-knit-pay-pro, #deactivate-knit-pay-upi, #deactivate-knit-pay\').on(\'click\', function(event) {
        event.preventDefault()

    	jQuery("#knitpay-deactivation-confirm").dialog({
    		title: \'Deactivate Knit Pay?\',
    		resizable: false,
    		height: "auto",
    		width: 400,
    		modal: true,
    		closeOnEscape: true,
    		buttons: {
    			"Whatsapp Us": function () {
    				jQuery(this).dialog("close");
    				var redirectWindow = window.open("https://wa.me/917738456813", \'_blank\');
    				redirectWindow.location;
    			},
    			"Need Customization": function () {
    				jQuery(this).dialog("close");
    				var redirectWindow = window.open("https://www.knitpay.org/contact-us/", \'_blank\');
    				redirectWindow.location;
    			},
    			"Deactivate": function () {
    				jQuery(this).dialog("close");
    				window.location.href = event.target.getAttribute(\'href\');
    			},
    		}
    	});
	});
}
</script>';
}
