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