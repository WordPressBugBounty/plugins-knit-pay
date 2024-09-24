<input id="instamojo-pay-button" class="pronamic-pay-btn" type="submit" name="pay" value="Pay" />
<script src="https://js.instamojo.com/v1/checkout.js"></script>

<div class="top-bar">
	<div class="row ">
		<div class="columns">
			<div class="left top-bar-button">
				<a id="instamojo-powered-button" target="_blank" rel="nofollow" style="background: none;">
					<img src="<?php echo KNITPAY_URL . '/images/instamojo/icon.svg'; ?>" alt="share smart link" class="push-half--right" style="height: 38px;background: white;border-radius: 4px;">
				</a>
			</div>
			<div class="right top-bar-button">
				<a id="instamojo-cancel-button" style="display: block; padding: 8px 16px; color: rgb(255, 255, 255); height: 100%;">
					&#x274C;
					<span class="hide-on-small">Cancel</span>
				</a>
			</div>
		</div>
	</div>
</div>

<script>
	var options = <?php echo $instamojo_data; ?>;

	if (options.hide_top_bar){
		document.getElementsByClassName('top-bar')[0].setAttribute("hidden", true);
	}

	document.getElementById('instamojo-powered-button').setAttribute("href", options.instamojo_signup_url);
	document.getElementById('instamojo-cancel-button').setAttribute("href", options.cancel_url);

	document.getElementById('instamojo-pay-button').onclick = function(e) {
		Instamojo.configure({
			directPaymentMode: options.payment_method,
			handlers: {
				onClose: function() {
						// Hide payment redirect container.
					document.getElementsByClassName("pronamic-pay-redirect-container")[0].style.visibility = 'hidden';

					window.location.href = options.cancel_url;
				},
			}
		});

		// Hide payment redirect container.
		Instamojo.open(options.action_url);

		e.preventDefault();
	}
</script>

<style>
	.top-bar {
		width: 100%;
		position: fixed;
		top: 0;
		left: 0;
		z-index: 9999999999;
		background-color: #2c475d;
		height: 48px;
		font-size: 14px !important;
		font-weight: 500 !important;
	}

	.top-bar .top-bar-button {
		padding: 5px;
	}

	.top-bar .top-bar-button a {
		height: 17px;
		max-width: 146px;
		font-weight: 500;
		border-radius: 4px;
		background-color: #35526a;
		box-shadow: -1px 0 0 0 rgb(0 56 91 / 15%), 1px 0 0 0 rgb(0 56 94 / 15%);
		text-decoration: none;
		box-sizing: unset;
	}

	.row {
		max-width: 62.5rem;
		margin-left: auto;
		margin-right: auto;
	}

	.left {
		float: left !important;
	}

	.right {
		float: right !important;
	}

	.column, .columns {
		width: 100%;
		float: left;
		padding-left: 0.9375rem;
		padding-right: 0.9375rem;
	}

	@media screen and (min-width: 40em)
	.column, .columns {
		padding-left: 0.9375rem;
		padding-right: 0.9375rem;
	}

	@media only screen and (max-width: 40em) {
		.hide-on-small {
			display: none!important;
		}
	}

	.im-modal-content {
	  background: white;
	}
</style>
