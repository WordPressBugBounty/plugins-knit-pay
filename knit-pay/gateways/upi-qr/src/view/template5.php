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
	  <div class="amount-display">₹<?php echo $intent_url_parameters['am']; ?></div>
	</div>

	<!-- Timer Section -->
	<div class="timer">
	  <span id="countdown-timer" class="expires-span"></span>
	</div>

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
	</div>

	<!-- Confirm Payment Section -->
	<?php if ( $this->show_manual_confirmation ) { ?>
	  <div class="confirm-payment-section">
		<button class="confirm-btn" id="confirmPaymentBtn" onclick="confirmPayment()">
		  <span id="btnText">I've Made the Payment</span>
		</button>
	  </div>
	<?php } ?>

	<!-- Payment Methods Section -->
	<?php if ( wp_is_mobile() ) { ?>
	  <div class="payment-methods">
		<div class="section-title">Pay with other methods</div>
		<div class="method-list">

		  <a href="<?php echo add_query_arg( $paytm_intent_url_params, 'paytmmp://cash_wallet' ); ?>">
			<div class="method-item">
			  <img src="<?php echo $image_path; ?>paytm_icon.svg" class="method-icon no-drag" alt="Paytm">
			  <span class="method-name">Paytm</span>
			</div>
		  </a>

		  <?php if ( $show_download_qr_button ) { ?>
			<a href="#" class="share-qr-button">
			  <div class="method-item">
				<img src="<?php echo $image_path; ?>gpay_icon.svg" class="method-icon no-drag" alt="Google Pay">
				<span class="method-name">Share QR</span>
			  </div>
			</a>
		  <?php } ?>
		</div>
	  </div>
	<?php } ?>

	<!-- Order Summary -->
	<div class="order-summary">
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
