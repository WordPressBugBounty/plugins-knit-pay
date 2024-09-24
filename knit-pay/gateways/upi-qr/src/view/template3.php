<div class="kp-upi-template-3-qr-card">
	<div class="header">
		<h2><?php echo $payee_name; ?></h2>
		<p>Scan to Pay ₹<?php echo $intent_url_parameters['am']; ?></p>
	</div>
	<div class='qrCodeWrapper' id='qrCodeWrapper' style='display: none;'>
			<?php if ( $hide_pay_button ) { ?>
			<div class='qr-code qrCodeBody'></div>
			<?php } else { ?>
			<a href='<?php echo add_query_arg( $intent_url_parameters, 'upi://pay' ); ?>'>
				<div class='qr-code qrCodeBody'></div>
			</a>
			<?php } ?>
	</div>
	<?php if ( $show_download_qr_button ) { ?>
		<button class="template-3-button download-qr-button">Download QR</button>
	<?php } ?>
	<div class="kp-upi-template-3-footer">
		<span id="countdown-timer" class="expires-span"></span>
		<span>
			<button onclick='cancelTransaction();' class="template-3-button cancel-button">Cancel</button>
		</span>
	</div>
</div>
