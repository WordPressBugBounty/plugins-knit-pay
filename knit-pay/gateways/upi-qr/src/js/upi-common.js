/*
* Download and Share QR Code functionality for Knit Pay UPI QR Gateway
*/
function knit_pay_load_download_share() {
    jQuery(".download-qr-button").on("click", function () {
        knit_pay_downloadQR();
    });

    if (navigator.canShare){
        jQuery(".share-qr-button").on("click", function () {
            knit_pay_shareQR(window.knit_pay_qrcode);
        });
    } else {
        jQuery(".share-qr-button").remove();
    }
}

function knit_pay_downloadQR() {
    window.knit_pay_qrcode.download("upi_qr_" + document.querySelector('input[name=knit_pay_transaction_id]').value);
}

async function knit_pay_shareQR(qrcode) {
    const imageUrl = qrcode._oDrawing.dataURL;
    const response = await fetch(imageUrl);
    const blob = await response.blob();
    const file = new File([blob], "upi_qr_" + document.querySelector('input[name=knit_pay_transaction_id]').value + ".png", {
        type: blob.type
    });

    if (navigator.canShare && navigator.canShare({
        files: [file]
    })) {
        await navigator.share({
            title: "Pay via Google Pay",
            text: "Scan this QR code to pay via Google Pay.",
            files: [file],
        });
    } else {
        alert('Web Share API is not supported in your browser.');
    }
}

/*
* confirm payment listner.
*/
function confirmPayment() {
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

let payment_status_counter = 0;
let payment_status_checker;
let checking_payment_status = false;
let skip_payment_status_check_counter = 0;
function knit_pay_check_payment_status(utr = '') {
	if (checking_payment_status) {
		if (++skip_payment_status_check_counter > 3) {
			skip_payment_status_check_counter = 0;
			checking_payment_status = false;
		} else {
			return; // Skip if already checking
		}
	}

	payment_status_counter++;
	checking_payment_status = true;

	jQuery.post(knit_pay_upi_qr_vars.ajaxurl, {
		'action': 'knit_pay_upi_qr_payment_status_check',
		'knit_pay_transaction_id': document.querySelector('input[name=knit_pay_transaction_id]').value,
		'knit_pay_payment_id': document.querySelector('input[name=knit_pay_payment_id]').value,
		'check_status_count': payment_status_counter,
		'knit_pay_nonce': document.querySelector('input[name=knit_pay_nonce]').value,
		'knit_pay_utr': utr,
	}, function(msg) {
		checking_payment_status = false;
		skip_payment_status_check_counter = 0;

		if ('' !== utr && msg.data == 'Open') {
			Swal.fire({
				'title': 'Transaction Not Found!',
				'text': 'Please verify that the provided UTR is accurate.',
				'icon': 'error'
			}).then((result) => {
				confirmPayment();
			});

		} else if (msg.data == 'Success') {
			knit_pay_upi_qr_stop_polling();

			Swal.fire('Your Payment Received Successfully', 'Please Wait!', 'success')

			setTimeout(function() {
				document.getElementById('formSubmit').submit();
			}, 200);
		} else if (msg.data == 'Failure' || msg.data == 'Expired') {
			knit_pay_upi_qr_stop_polling();

			Swal.fire('Payment '+ msg.data, 'Please Wait!', 'error')

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

// Stop monitoring when user leaves the page
window.addEventListener('beforeunload', function() {
    knit_pay_upi_qr_stop_polling();
});