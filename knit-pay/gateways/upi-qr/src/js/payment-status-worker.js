let payment_status_counter = 0;
let checking_payment_status = false;
let skip_payment_status_check_counter = 0;
let payment_status_checker;

function checkPaymentStatus(ajax_data) {
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

    const body = new URLSearchParams();
    body.append('action', 'knit_pay_upi_qr_payment_status_check');
    body.append('knit_pay_transaction_id', ajax_data.transaction_id);
    body.append('knit_pay_payment_id', ajax_data.payment_id);
    body.append('check_status_count', payment_status_counter);
    body.append('knit_pay_nonce', ajax_data.nonce);
    body.append('knit_pay_utr', '');

    fetch(ajax_data.ajaxurl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: body,
    })
    .then(response => response.json())
    .then(msg => {
        checking_payment_status = false;
        skip_payment_status_check_counter = 0;

        if (msg.data === 'Success' || msg.data === 'Failure' || msg.data === 'Expired') {
            postMessage(msg.data); // Send status back to the main thread
            clearInterval(payment_status_checker); // Stop polling
            close(); // Terminate the worker
        }
    })
    .catch(error => {
        checking_payment_status = false;
        skip_payment_status_check_counter = 0;
        console.error('Error checking payment status:', error);
    });
}

self.onmessage = function(e) {
    if (e.data.command === 'start') {
        payment_status_checker = setInterval(() => checkPaymentStatus(e.data.ajax_data), 4000);
    } else if (e.data.command === 'stop') {
        clearInterval(payment_status_checker);
        close();
    }
};