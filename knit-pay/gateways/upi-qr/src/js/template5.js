function generateQR(user_input) {
	let qrWrapperElement =  document.getElementById('qrCodeWrapper');
	if (!qrWrapperElement) {
		return;
	}

	qrWrapperElement.style.display = 'flex';
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

	knit_pay_load_download_share();
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

window.onload = function () {
	if (jQuery("#enable_polling").val()) {
		payment_status_checker = setInterval(knit_pay_check_payment_status, 4000);
	}

	generateQR(jQuery("#upi_qr_text").val());

	knit_pay_countdown(jQuery("#payment_expiry_seconds").val(), 'countdown-timer', 'Complete payment in %mm:%ss', paymentExpiredAction);
};