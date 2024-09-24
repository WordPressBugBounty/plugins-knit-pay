//add Knit Pay redirection
$(document).on('em_booking_gateway_add_knit_pay', function(event, response){console.log(response);
	// called by EM if return JSON contains gateway key, notifications messages are shown by now.
	if(response.result && typeof response.redirect_url != 'undefined' ){
		var kpForm = $('<form action="'+response.redirect_url+'" method="post" id="em-knit-pay-redirect-form"></form>');
		kpForm.append('<input id="em-knit-pay-submit" type="submit" style="display:none" />');
		kpForm.appendTo('body').trigger('submit');
	}
});