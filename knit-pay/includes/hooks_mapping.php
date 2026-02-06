<?php

add_action(
	'pronamic_payment_status_update',
	function ( $payment, $can_redirect, $previous_status, $updated_status ) {
		do_action( 'knit_pay_payment_status_update', $payment, $can_redirect, $previous_status, $updated_status );
	},
	0,
	4
);

function knit_pay_plugin() {
	return pronamic_pay_plugin();
}
