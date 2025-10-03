function generateQR(user_input) {
	jQuery('.appPayment').css('display', 'none');
	jQuery('#backBtn').attr('onclick', 'qr_back();')
	document.getElementById('qrCodeWrapper').style.display = 'flex';
	jQuery('.qrCodeBody').html('');
	window.knit_pay_qrcode = new QRCode(document.querySelector('.qrCodeBody'), {
		text: user_input,
		width: 250, //default 128
		height: 250,
		colorDark: '#000000',
		colorLight: '#ffffff',
		correctLevel: QRCode.CorrectLevel.H,
		quietZone: 10,
	});
	paymentClicked();

	knit_pay_load_download_share();
}


function qr_back() {
	document.getElementById('qrCodeWrapper').style.display = 'none';
	jQuery('.appPayment').css('display', 'flex');
	jQuery('#backBtn').attr('onclick', 'cancelOrder();')
	//document.getElementById('continue-first-btn').style.display = 'none';
}

function paymentClicked() {
	//document.getElementById('continue-first-btn').style.display = 'block';
}

function paynow() {
	document.getElementById('payment-first').style.display = 'none';
	document.getElementById('payment-second').style.display = 'block';
	document.getElementById('payment-third').style.display = 'none';
}

function paynow_back() {
	document.getElementById('payment-first').style.display = 'block';
	document.getElementById('payment-second').style.display = 'none';
	document.getElementById('payment-third').style.display = 'none';
	//document.getElementById('continue-first-btn').style.display = 'none';
}

function cancelOrder() {
	document.getElementById('payment-first').style.display = 'none';
	document.getElementById('payment-second').style.display = 'none';
	document.getElementById('payment-third').style.display = 'block';
}

function cancelTransaction() {
	jQuery("#formSubmit [name='status']").val('Cancelled');
	jQuery("#formSubmit").submit();
}

window.onload = function() {
	if (jQuery("#enable_polling").val()){
		knit_pay_start_polling();
	}
};