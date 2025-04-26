document.addEventListener('wpcf7mailsent', function(event) {
    if (typeof event.detail.apiResponse != 'undefined' && event.detail.apiResponse) {
        const apiResponse = event.detail.apiResponse;
        let actionDelay = 0;

        //catch redirect action
        if (typeof apiResponse.knit_pay != 'undefined' && apiResponse.knit_pay) {
            if (typeof apiResponse.knit_pay[0].error_message != 'undefined') {
                alert(apiResponse.knit_pay[0].error_message);
                return
            }

            actionDelay = typeof apiResponse.knit_pay.delay_redirect != 'undefined' ? apiResponse.knit_pay.delay_redirect : actionDelay;
            window.setTimeout(function () {
                // Code taken from wpcf7r-fe.js of Redirection for Contact Form 7 plugin. `handle_redirect_action` function
                // This is a workaround to trigger the redirect action in the same way as the original plugin
                let redirect = apiResponse.knit_pay;

                jQuery(document.body).trigger('wpcf7r-handle_redirect_action', [redirect]);

                jQuery.each(redirect, function (k, v) {
                    const delay = (v.delay || 0) * 1000;

                    window.setTimeout(function (v) {
                        const redirect_url = v.redirect_url || '';
                        const type = v.type || '';

                        if (typeof v.form != 'undefined' && v.form) {
                            jQuery('body').append(v.form);
                            jQuery('#cf7r-result-form').submit();
                        } else {
                            if (redirect_url && type == 'redirect') {
                                window.location = redirect_url;
                            } else if (redirect_url && type == 'new_tab') {
                                window.open(redirect_url);
                            }
                        }
                    }, delay, v);
                });
            }, actionDelay);
        }
    }
});
