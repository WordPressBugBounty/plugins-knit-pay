<script>
document.addEventListener('DOMContentLoaded', function () {
	// Keep Pay Button Disabled for 3 seconds
	setTimeout(function() {
			  document.getElementById('payButton').disabled = false;
	}, 3000);

	CollectJS.configure({
		'paymentType': 'cc',
		'callback': function (response) {
							//alert(response.token);
							var input = document.createElement("input");
							input.type = "hidden";
							input.name = "payment_token";
							input.value = response.token;
							var form = document.getElementsByTagName("form")[0];
							form.appendChild(input);
							form.submit();
		}
	});

	if (<?php echo $auto_submit; ?>){
		// Hide payment redirect container.
		document.getElementsByClassName("pronamic-pay-redirect-container")[0].style.visibility = 'hidden';
		CollectJS.startPaymentRequest();
	}

	document.getElementById('payButton').onclick = function(e){
		// Hide payment redirect container.
		document.getElementsByClassName("pronamic-pay-redirect-container")[0].style.visibility = 'hidden';
	}

	// Redirect if pop up box closed.
	// Create a proxy object to intercept the function call
	const closePaymentRequestProxy = new Proxy(CollectJS.closePaymentRequest, {
	  apply: function(target, thisArg, argumentsList) {
		// Call the original function
		const result = Reflect.apply(target, thisArg, argumentsList);

		// Add your code here to execute after the CollectJS.closePaymentRequest function is called
		console.log("Code executed after CollectJS.closePaymentRequest function.");

		setTimeout(function() {
		  window.location.href = document.getElementById('pronamic_ideal_form').action + '&action=Cancelled';
		}, 3000);

		//window.location.href = document.getElementById('pronamic_ideal_form').action + '&action=cancelled';

		// Return the result of the original function call
		return result;
	  },
	});
	// Assign the proxy function back to the CollectJS object
	CollectJS.closePaymentRequest = closePaymentRequestProxy;
});
</script>

<script id="nmi-checkout-js" src="https://secure.networkmerchants.com/token/Collect.js"
	data-tokenization-key="<?php echo $this->config->public_key; ?>"></script>
