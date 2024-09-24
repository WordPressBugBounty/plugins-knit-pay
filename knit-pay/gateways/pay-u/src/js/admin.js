// Disable MID, Key and Salt.
document.getElementById("_pronamic_gateway_payu_mid").disabled = true;
document.getElementById("_pronamic_gateway_payu_merchant_key").disabled = true;
document.getElementById("_pronamic_gateway_payu_merchant_salt").disabled = true;

// Send Phone OTP
document.getElementById("payu-send-phone-otp").addEventListener("click", function(event) {
    event.preventDefault();

    let phone = document.getElementById("_pronamic_gateway_payu_phone").value;
    if (phone == "") {
        alert("Please Enter Registered Phone number");
        return;
    }

    sent_otp(phone, "sms");
});

// Send Email OTP
document.getElementById("payu-send-email-otp").addEventListener("click", function(event) {
    event.preventDefault();

    let email = document.getElementById("_pronamic_gateway_payu_email").value;
    if (email == "") {
        alert("Please Enter Registered Email");
        return;
    }

    sent_otp(email, "email");

});

// Submit OTP
document.getElementById("payu-submit-otp").addEventListener("click", function(event) {
    event.preventDefault();

    let phone = document.getElementById("_pronamic_gateway_payu_phone").value;
    let email = document.getElementById("_pronamic_gateway_payu_email").value;
    let otp = document.getElementById("_pronamic_gateway_payu_otp").value;

    if (phone == "") {
        alert("Please Enter Registered Phone number");
        return;
    }
    if (email == "") {
        alert("Please Enter Registered Email");
        return;
    }
    if (otp == "") {
        alert("Please Enter OTP");
        return;
    }

    document.getElementById("publish").click();
});

// Ajax to Send OTP
function sent_otp(identity, channel) {
    document.getElementById("payu-send-phone-otp").setAttribute("disabled", "true");
    document.getElementById("payu-send-email-otp").setAttribute("disabled", "true");
    jQuery.post(ajaxurl, {
            "action": "knitpay_payu_send_otp",
            "identity": identity,
            "channel": channel,
            "mode": document.getElementById("pronamic_ideal_mode").value,
        },
        function(msg) {
            document.getElementById("payu-send-phone-otp").removeAttribute("disabled");
            document.getElementById("payu-send-email-otp").removeAttribute("disabled");
            alert(msg);
        });

}
