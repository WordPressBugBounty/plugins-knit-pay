function generateQR(user_input) {
	document.getElementById('qrCodeWrapper').style.display = 'flex';
	jQuery('.qrCodeBody').html('');
	window.knit_pay_qrcode = new QRCode(document.querySelector('.qrCodeBody'), {
		text: user_input,
		width: 200, //default 128
		height: 200,
		colorDark: '#000000',
		colorLight: '#ffffff',
		correctLevel: QRCode.CorrectLevel.H,
		quietZone: 20,
		//logo: jQuery("#image_dir_path").val() + "upi.svg",
		//logoHeight: '32',
	});
}

function cancelTransaction() {
	jQuery("#formSubmit [name='status']").val('Cancelled');
	jQuery("#formSubmit").submit();
}

function paymentExpiredAction() {
	jQuery("#countdown-timer").text("Expired");
	jQuery("#formSubmit [name='status']").val('Expired');
	jQuery("#formSubmit").submit();
};

function knit_pay_check_payment_status() {
	// stop polling.
	payment_status_counter++;

	jQuery.post(knit_pay_upi_qr_vars.ajaxurl, {
		'action': 'knit_pay_upi_qr_payment_status_check',
		'knit_pay_transaction_id': document.querySelector('input[name=knit_pay_transaction_id]').value,
		'knit_pay_payment_id': document.querySelector('input[name=knit_pay_payment_id]').value,
		'check_status_count': payment_status_counter,
		'knit_pay_nonce': document.querySelector('input[name=knit_pay_nonce]').value
	}, function (msg) {// start polling.
		if (msg.data == 'Success') {
			clearInterval(payment_status_checker);

			Swal.fire('Your Payment Received Successfully', 'Please Wait!', 'success')

			setTimeout(function () {
				document.getElementById('formSubmit').submit();
			}, 200);
		} else if (msg.data == 'Failure') {
			clearInterval(payment_status_checker);

			Swal.fire('Payment Failed', 'Please Wait!', 'error')

			setTimeout(function () {
				document.getElementById('formSubmit').submit();
			}, 200);
		}
	});
}

let payment_status_counter = 0;
window.onload = function () {
	if (jQuery("#enable_polling").val()) {
		payment_status_checker = setInterval(knit_pay_check_payment_status, 4000);
	}

	generateQR(jQuery("#upi_qr_text").val());
	knit_pay_load_download_share();

	knit_pay_countdown(jQuery("#payment_expiry_seconds").val(), 'countdown-timer', 'Complete payment in %mm:%ss', paymentExpiredAction);
};