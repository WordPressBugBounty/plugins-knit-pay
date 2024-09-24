<div class='paymentWrapper'>
	<div class='paymentWrapperCard'>
		<div class='paymentContainer' id='payment-first'>
			<div class='topPaymentWrapper'>
				<img src='<?php echo $image_path; ?>back_icon.svg' alt='back button'
					id='backBtn' onclick='cancelOrder()'>
				<h1>Choose a payment option</h1>
				<p>
					Payable Now <span style='font-weight: 800;'><?php echo $amount; ?></span>
				</p>
				<p class='orderid'>Transation Number : <?php echo $transaction_id; ?></p>
			</div>
			<div class='paymentMethodWrapper'>
				<div class='methodHeading'>Payment Options</div>

	<?php if ( ! $hide_pay_button ) { ?>

<a href='<?php echo add_query_arg( $intent_url_parameters, 'gpay://upi/pay' ); ?>'  class='methodsWrapper appPayment'>
			<div class='leftSide'>
			  <div>
				<img src='<?php echo $image_path; ?>gpay_icon.svg' alt='gpay'>
			  </div>
			  <div>
				<p style='margin-bottom:0.3rem;font-weight:bold;'>Google Pay</p>
				<span>Pay with Google Pay UPI</span>
			  </div>
			</div>
			<div class='rightSide'>
			  <img src='<?php echo $image_path; ?>right_icon.svg' alt='button'>
			</div>
		  </a>
		  <a href='<?php echo add_query_arg( $intent_url_parameters, 'phonepe://pay' ); ?>' class='methodsWrapper appPayment'>
			<div class='leftSide'>
			  <div>
				<img src='<?php echo $image_path; ?>phonepe.svg' alt='phonepe'>
			  </div>
			  <div>
				<p style='margin-bottom:0.3rem;font-weight:bold;'>PhonePe</p>
				<span>Pay with PhonePe UPI</span>
			  </div>
			</div>
			<div class='rightSide'>
			  <img src='<?php echo $image_path; ?>right_icon.svg' alt='button'>
			</div>
		  </a>
		  <a href='<?php echo add_query_arg( $intent_url_parameters, 'paytmmp://pay' ); ?>'  class='methodsWrapper appPayment'>
			<div class='leftSide'>
			  <div>
				<img src='<?php echo $image_path; ?>paytm_icon.svg' alt='paytm'>
			  </div>
			  <div>
				<p style='margin-bottom:0.3rem;font-weight:bold;'>Paytm</p>
				<span>Pay with Paytm UPI</span>
			  </div>
			</div>
			<div class='rightSide'>
			  <img src='<?php echo $image_path; ?>right_icon.svg' alt='button'>
			</div>
		  </a>
			  <a href='<?php echo add_query_arg( $intent_url_parameters, 'bhim://pay' ); ?>'  class='methodsWrapper appPayment'>
			<div class='leftSide'>
			  <div>
				<img src='<?php echo $image_path; ?>bhim_icon.svg' alt='paytm'>
			  </div>
			  <div>
				<p style='margin-bottom:0.3rem;font-weight:bold;'>BHIM UPI</p>
				<span>Pay with BHIM UPI</span>
			  </div>
			</div>
			<div class='rightSide'>
			  <img src='<?php echo $image_path; ?>right_icon.svg' alt='button'>
			</div>
		  </a>
		  <a href='<?php echo add_query_arg( $intent_url_parameters, 'whatsapp://pay' ); ?>'  class='methodsWrapper appPayment'>
			<div class='leftSide'>
			  <div>
				<img src='<?php echo $image_path; ?>whatspp_pay.svg' alt='paytm'>
			  </div>
			  <div>
				<p style='margin-bottom:0.3rem;font-weight:bold;'>Whatsapp Pay</p>
				<span>Pay with Whatsapp Pay UPI</span>
			  </div>
			</div>
			<div class='rightSide'>
			  <img src='<?php echo $image_path; ?>right_icon.svg' alt='button'>
			</div>
		  </a>
		  <a href='<?php echo add_query_arg( $intent_url_parameters, 'upi://pay' ); ?>'  class='methodsWrapper appPayment'>
			<div class='leftSide'>
			  <div>
				<img src='<?php echo $image_path; ?>upi_icon.svg' alt='UPI'>
			  </div>
			  <div>
				<p style='margin-bottom:0.3rem;font-weight:bold;'>UPI</p>
				<span>Pay with any UPI App</span>
			  </div>
			</div>
			<div class='rightSide'>
			  <img src='<?php echo $image_path; ?>right_icon.svg' alt='button'>
			</div>
		  </a>
		<?php
	}
	if ( ! wp_is_mobile() || ! $this->config->hide_mobile_qr ) {
		?>
	<a href='javascript:void(0)' onclick='generateQR(" <?php echo $upi_qr_text; ?>");' class='methodsWrapper'>
			<div class='leftSide'>
			  <div>
				<img src='<?php echo $image_path; ?>qr_icon.svg' alt='qr_icon'>
			  </div>
			  <div>
				<p style='margin-bottom:0.3rem;font-weight:bold;'>Show QRCode</p>
				<span>Pay with Any UPI App</span>
			  </div>
			</div>
			<div class='rightSide'>
			  <img src='<?php echo $image_path; ?>right_icon.svg' alt='button'>
			</div>
		  </a>
		  <div class='qrCodeWrapper' id='qrCodeWrapper' style='display: none;'>
			  <?php if ( $hide_pay_button ) { ?>
				<div class='qr-code qrCodeBody'></div>
			  <?php } else { ?>
				<a href='<?php echo add_query_arg( $intent_url_parameters, 'upi://pay' ); ?>'>
					<div class='qr-code qrCodeBody'></div>
				</a>
			  <?php } ?>
			<!-- <div class='btnWrapper'>
			  <button class='paymentContinueBtn' onclick='qr_back();'>Back</button>
			</div> -->
		  </div>
		  <?php if ( $show_download_qr_button ) { ?>
				  <div class="btnWrapper"><button class="download-qr-button"><span class="dashicons dashicons-download"></span>Download QR</button></div>
		  <?php } ?>
	<?php } ?>
</div>

		<!-- For now don't allow to enter UTR. It will be used latter.
		<div class='btnWrapper' id='continue-first-btn' style='display:none;'>
		  <button class='paymentContinueBtn' onclick='paynow();'>Continue</button>
		</div> -->
	  </div>
	  <div class='paymentContainer' id='payment-second' style='display:none'>
		<div class='topPaymentWrapper'>
		  <img src='<?php echo $image_path; ?>back_icon.svg' alt='back button' onclick='paynow_back()'>
		  <h1>Transaction Details</h1>
		  <p style='padding: 0 1rem;margin-top:1.5rem;'>Please enter transaction details to validate payment!</p>
		</div>
		<div class='paymentMethodWrapper'>
		  <div class='inputWrapper' style='margin-top:2rem'>
			<label for='customerUTRNumber'>UTR Number (12 Digits)</label>
			<input type='number' id='customerUTRNumber' name='customerUTRNumber' autocomplete='off' oninput='validateUTRNumber(this);' onkeydown='if(this.value.length==12 && event.keyCode!=8) return false;'/>
		  </div>
		  <div class='btnWrapper utrContinueBtn' style='display:none;'>
			<button class='cardPaymentButton' onclick='orderPlaced();'>Continue</button>
		  </div>
		</div>
	  </div>
	  <div class='paymentContainer' id='payment-third' style='display:none;'>
		  <div class='topPaymentWrapper'>
			  <h1>Order Cancelled!</h1>
			  <p style='padding: 0 1rem;margin-top:1.5rem;'>You have cancelled the order! If cancelled by mistake try again.</p>
			</div>
		  <div class='successIconWrapper'>
			<img src='<?php echo $image_path; ?>unchecked.svg' alt='Success Icon'>
			<p style='margin-top:2rem;margin-bottom:0rem;'>Your order has been cancelled!</p>
		  </div>
		  <div class='btnWrapper' style='margin: 2rem 2rem 0rem 2rem;'>
			<button onclick='cancelTransaction();'>Cancel</button>
		  </div>
		  <div class='btnWrapper' style='margin: 2rem 2rem 0rem 2rem;'>
			<button onclick='paynow_back();'>Retry</button>
		  </div>
	  </div>
	  <div class='paymentFooter'>
		<div class='innerWrapper'>
		  <p>Powered By</p>
		  <div style='display: flex;gap: 14px;margin:10px;'>
			<img src='<?php echo $image_path; ?>upi.svg' alt='UPI Icon'>
		  </div>
		</div>
	  </div>
	</div>
  </div>
<?php
wp_footer();
if ( ! wp_is_mobile() || $hide_pay_button ) {
	?>
	<script>
	generateQR("<?php echo $upi_qr_text; ?>");
	jQuery('#backBtn').attr( 'onclick', 'cancelOrder();');
	</script>
<?php } ?>
