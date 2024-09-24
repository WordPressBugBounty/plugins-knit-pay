document.addEventListener('wpcf7mailsent', function(event) {
    if (typeof event.detail.apiResponse != 'undefined' && event.detail.apiResponse) {
        var apiResponse = event.detail.apiResponse;
        var actionDelay = 0;

        //catch redirect action
        if (typeof apiResponse.knit_pay != 'undefined' && apiResponse.knit_pay) {
            if (typeof apiResponse.knit_pay[0].error_message != 'undefined') {
                alert(apiResponse.knit_pay[0].error_message);
                return
            }

            actionDelay = typeof apiResponse.knit_pay.delay_redirect != 'undefined' ? apiResponse.knit_pay.delay_redirect : actionDelay;
            window.setTimeout(function() {
                wpcf7_redirect.handle_redirect_action(apiResponse.knit_pay);
            }, actionDelay);
        }
    }
});
