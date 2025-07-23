<div class="qr-wrapper">
	<div class="qr-container">
		<div class="qr-title"><?php echo $payee_name; ?></div>
		<div class='qrCodeWrapper' id='qrCodeWrapper' style='display: none;'>
			<?php
			if ( $hide_pay_button ) {
				?>
				<div class='qr-code qrCodeBody'></div>
				<?php
			} else {
				?>
				<a href='<?php echo add_query_arg( $intent_url_parameters, 'upi://pay' ); ?>'>
					<div class='qr-code qrCodeBody'></div>
				</a>
			<?php } ?>
		</div>

		<div class="amount">Scan to pay ₹<?php echo $intent_url_parameters['am']; ?></div>
		<div>
			<?php if ( $show_download_qr_button ) { ?>
				<button class="template-4-button download-qr-button"><span class="dashicons dashicons-download"></span>Download QR</button>
			<?php } ?>
				<button class="template-4-button pay-button" onclick="confirmPayment()">Confirm Payment</button>
		</div>
		<div id="countdown-timer" class="validity"></div>
	</div>
</div>
