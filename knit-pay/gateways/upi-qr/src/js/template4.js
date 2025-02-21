function payViaUPI() {
	Swal.fire({
		title: 'Enter UTR Number',
		input: 'text',
		inputAttributes: {
			autocapitalize: 'off',
			oninput: "this.value = this.value.replace(/\\D/g, '').slice(0, 12);",
			required: true
		},
		showCancelButton: true,
		confirmButtonText: 'Submit',
		cancelButtonText: 'Back',
		showLoaderOnConfirm: true,
		preConfirm: async (utr) => {
			knit_pay_check_payment_status(utr);
		},
		allowOutsideClick: () => !Swal.isLoading()
	}).then((result) => {
	});
}

function generateQR(user_input) {
	document.getElementById('qrCodeWrapper').style.display = 'flex';
	jQuery('.qrCodeBody').html('');
	var qrcode = new QRCode(document.querySelector('.qrCodeBody'), {
		text: user_input,
		width: 270, //default 128
		height: 270,
		colorDark: '#000000',
		colorLight: '#ffffff',
		correctLevel: QRCode.CorrectLevel.H,
		quietZone: 10,
		//logo: jQuery("#image_dir_path").val() + "upi.svg",
		//logoHeight: '32',
	});

	jQuery(".download-qr-button").on("click", function() {
		qrcode.download("upi_qr");
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

function knit_pay_check_payment_status(utr = '') {
	payment_status_counter++;

	jQuery.post(knit_pay_upi_qr_vars.ajaxurl, {
		'action': 'knit_pay_upi_qr_payment_status_check',
		'knit_pay_transaction_id': document.querySelector('input[name=knit_pay_transaction_id]').value,
		'knit_pay_payment_id': document.querySelector('input[name=knit_pay_payment_id]').value,
		'check_status_count': payment_status_counter,
		'knit_pay_nonce': document.querySelector('input[name=knit_pay_nonce]').value,
		'knit_pay_utr': utr,
	}, function(msg) {
		if ('' !== utr && msg.data == 'Open') {
			Swal.fire({
				'title': 'Transaction Not Found!',
				'text': 'Please verify that the provided UTR is accurate.',
				'icon': 'error'
			}).then((result) => {
				payViaUPI();
			});

		} else if (msg.data == 'Success') {
			knit_pay_upi_qr_stop_polling();

			Swal.fire('Your Payment Received Successfully', 'Please Wait!', 'success')

			setTimeout(function() {
				document.getElementById('formSubmit').submit();
			}, 200);
		} else if (msg.data == 'Failure') {
			knit_pay_upi_qr_stop_polling();

			Swal.fire('Payment Failed', 'Please Wait!', 'error')

			setTimeout(function() {
				document.getElementById('formSubmit').submit();
			}, 200);
		}
	});
}

function knit_pay_upi_qr_stop_polling() {
	if (undefined !== payment_status_checker){
		clearInterval(payment_status_checker);
	}
}

let payment_status_counter = 0;
let payment_status_checker;
window.onload = function() {
	if (jQuery("#enable_polling").val()){
		payment_status_checker = setInterval(knit_pay_check_payment_status, 4000);
	}

	generateQR(jQuery("#upi_qr_text").val());

	knit_pay_countdown(jQuery("#payment_expiry_seconds").val(), 'countdown-timer', 'Expires in %mm:%ss', paymentExpiredAction);
};