<input id="rzp-button" class="pronamic-pay-btn" type="submit"
	name="pay" value="Pay" />
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
var options = <?php echo wp_json_encode( $data ); ?>;

options.modal = {
	ondismiss: function () {
		window.location.href = options.callback_url + '&action=cancelled';
	}
};

var rzp = new Razorpay(options);

document.getElementById('rzp-button').onclick = function(e){
	// Hide payment redirect container.
	document.getElementsByClassName("pronamic-pay-redirect-container")[0].style.visibility = 'hidden';

	rzp.open();
	e.preventDefault();
}
</script>
