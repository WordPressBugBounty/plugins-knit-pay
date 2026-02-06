<div class="container-wrapper">
	<div class="container no-drag">
		<!-- Header Section -->
		<div class="header">
			<div class="header-info">
				<img src="<?php echo get_site_icon_url( 512, $image_path . 'upi_icon.svg' ); ?>" class="logo" alt="UPI">
				<div>
					<div class="merchant-name"><?php echo $payee_name; ?></div>
					<?php if ( $this->merchant_verified ) { ?>
						<div class="verified-badge">
							<span class="dashicons dashicons-yes-alt"></span>
							Verified
						</div>
					<?php } ?>
				</div>
			</div>
			<!-- Cancel Button - Top Right -->
			<button class="cancel-btn-top" id="cancelPaymentBtnTop" onclick="cancelTransaction()" title="Cancel Payment">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>

		<!-- Timer Section -->
		<div class="timer">
			<span id="countdown-timer" class="expires-span"></span>
		</div>

		<!-- Conditional Section: UPI Instructions OR QR Code -->
		<?php if ( isset( $customer_upi_id ) && ! empty( $customer_upi_id ) ) { ?>
			<!-- UPI ID Instructions Section -->
			<div class="upi-instructions">
				<div class="instruction-icon dashicons dashicons-smartphone"></div>

				<h3 class="instruction-title">Payment Request Sent!</h3>

				<div class="instruction-steps">
					<div class="instruction-step">
						<div class="step-number">1</div>
						<div class="step-text">Open your UPI app (Google Pay, PhonePe, Paytm, etc.)</div>
					</div>
					<div class="instruction-step">
						<div class="step-number">2</div>
						<div class="step-text">Check for payment request notification</div>
					</div>
					<div class="instruction-step">
						<div class="step-number">3</div>
						<div class="step-text">Approve the payment to complete transaction</div>
					</div>
				</div>

				<div class="upi-id-display">
					<div class="upi-id-label">Payment request sent to:</div>
					<div class="upi-id-value"><?php echo $this->mask_upi_id( $customer_upi_id ); ?></div>
				</div>

				<div class="waiting-indicator">
					<div class="spinner"></div>
					<span>Waiting for payment approval...</span>
				</div>
			</div>
		<?php } else { ?>
			<!-- QR Code Section -->
			<div class="qr-section">
				<div class="qr-container">
					<div class='qrCodeWrapper' id='qrCodeWrapper' style='display: none;'>
						<div class='qr-code no-drag qrCodeBody'></div>
					</div>
					<div class="qr-overlay">
						<img src="<?php echo $image_path; ?>upi_icon.svg" alt="UPI" class="no-drag">
					</div>
				</div>
				<div class="scan-text">Scan QR code with any UPI app</div>

				<div class="upi-apps">
					<img src="<?php echo $image_path; ?>gpay_icon.svg" alt="Google Pay" class="upi-app-icon no-drag">
					<img src="<?php echo $image_path; ?>phonepe.svg" alt="PhonePe" class="upi-app-icon no-drag">
					<img src="<?php echo $image_path; ?>paytm_icon.svg" alt="Paytm" class="upi-app-icon no-drag">
					<img src="<?php echo $image_path; ?>upi_icon.svg" alt="BHIM" class="upi-app-icon no-drag">
				</div>

				<?php if ( $show_download_qr_button ) { ?>
					<div class="action-buttons">
						<button class="btn btn-primary download-qr-button">
							<span class="dashicons dashicons-download" style="margin-right: 3px;"></span> Save QR
						</button>
					</div>
				<?php } ?>

				<!-- QR Waiting Indicator -->
				<?php if ( $this->enable_polling ) { ?>
					<div class="qr-waiting-indicator">
						<div class="spinner-small"></div>
						<span>Waiting for payment...</span>
					</div>
				<?php } ?>
			</div>
		<?php } ?>

		<!-- Confirm Payment Section -->
		<?php if ( $this->show_manual_confirmation ) { ?>
			<div class="confirm-payment-section">
				<button class="confirm-btn" id="confirmPaymentBtn" onclick="confirmPayment()">
					<span id="btnText">I've Made the Payment</span>
				</button>
			</div>
		<?php } ?>

		<!-- Payment Methods Section, Show this section only on android not working on apple. -->
		<?php
		if ( ! isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			$is_android = false;
		} else {
			// phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__HTTP_USER_AGENT__
			$is_android = false !== strpos( sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ), 'Android' );
		}

		if ( ( ! isset( $customer_upi_id ) || empty( $customer_upi_id ) ) && $is_android ) {
			?>
			<div class="payment-methods">
				<div class="section-title">Pay with other methods</div>
				<div class="method-list">
					<a href="<?php echo esc_url( add_query_arg( $paytm_intent_url_params, 'paytmmp://cash_wallet' ), [ 'paytmmp' ] ); ?>" target="_blank">
						<div class="method-item">
							<img src="<?php echo esc_url( $image_path ); ?>paytm_icon.svg" class="method-icon no-drag" alt="Paytm">
							<span class="method-name"><?php echo esc_html__( 'Paytm', 'knit-pay-lang' ); ?></span>
						</div>
					</a>

					<?php if ( $show_download_qr_button ) { ?>
						<a href="#" class="share-qr-button">
							<div class="method-item">
								<img src="<?php echo esc_url( $image_path ); ?>gpay_icon.svg" class="method-icon no-drag" alt="Google Pay">
								<span class="method-name"><?php echo esc_html__( 'Share QR', 'knit-pay-lang' ); ?></span>
							</div>
						</a>
					<?php } ?>
				</div>
			</div>
		<?php } ?>

		<!-- Order Summary -->
		<div class="order-summary">
			<?php if ( isset( $order_id ) ) { ?>
				<div class="order-row">
					<span class="label">Order ID:</span>
					<span class="value"><?php echo $order_id; ?></span>
				</div>
			<?php } ?>
			<div class="order-row">
				<span class="label">Transaction ID:</span>
				<span class="value"><?php echo $transaction_id; ?></span>
			</div>
			<div class="order-row">
				<span class="label">Order Amount:</span>
				<span class="value">₹<?php echo $intent_url_parameters['am']; ?></span>
			</div>
		</div>

		<!-- Footer Section -->
		<div class="footer">
			<div>All UPI Accepted</div><br>
			<div>Need help? <a href="mailto:<?php echo $this->config->support_email; ?>"><?php echo $this->config->support_email; ?></a></div>
		</div>
	</div>
</div>
