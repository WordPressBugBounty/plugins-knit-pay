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