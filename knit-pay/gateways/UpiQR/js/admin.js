function KnitPayQRCodeScan(element) {
	document.getElementById("upi-file-label").textContent = "Loading...";
	element.disabled = true;

	var reader = new FileReader();
	reader.onloadend = function() {

		// see: https://github.com/nuintun/qrcode/tree/3.3.5?tab=readme-ov-file#decoder
		const qrcode = new QRCode.Decoder();
		qrcode
			.scan(reader.result)
			.then(result => {

				var url = JSON.parse(JSON.stringify(result.data));

				let params = (new URL(url)).searchParams;
				var pa = params.get('pa');

				if (pa != null) {
					document.getElementById("_pronamic_gateway_upi_qr_payee_name").value = params.get('pn');
					document.getElementById("_pronamic_gateway_upi_qr_vpa").value = params.get('pa');
					document.getElementById("_pronamic_gateway_upi_qr_merchant_category_code").value = params.get('mc');

					alert("QR code records have been automatically retrieved. Please verify whether the fetched records are accurate or not.");
				} else {
					alert("QR Code is Invalid");
				}

				document.getElementById("upi-file-label").textContent = "Select UPI QR";
				element.disabled = false;
			})
			.catch(error => {
				alert("Could not scan the QR code!");

				document.getElementById("upi-file-label").textContent = "Select UPI QR";
				element.disabled = false;
			})
	}
	reader.readAsDataURL(element.files[0]);
}

(function () {
	var statusField = document.getElementById("_pronamic_gateway_upi_qr_payment_success_status");
	if (!statusField) {
		return;
	}

	statusField.addEventListener("change", function () {
		if (this.value !== "Success") {
			return;
		}

		var confirmed = confirm(
			"WARNING: You selected \"Success\".\n\n" +
			"Knit Pay does NOT verify UPI QR payments. The payment will be marked as paid as soon as the customer clicks Submit — before you can check your bank.\n\n" +
			"If your store auto-delivers on success (digital goods, downloads, memberships, etc.), the customer gets the product WITHOUT paying.\n\n" +
			"Safe only if you ship physical goods manually and check your bank yourself first.\n\n" +
			"Click OK to keep \"Success\", or Cancel to go back."
		);

		if (!confirmed) {
			this.value = this.dataset.previousValue || "On Hold";
		}
	});

	statusField.dataset.previousValue = statusField.value;
	statusField.addEventListener("focus", function () {
		this.dataset.previousValue = this.value;
	});
})();