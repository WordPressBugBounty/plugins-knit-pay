class LatepointPaymentsKnitPayAddon {
	// Init
	constructor() {
		this.ready();
	}
	ready() {
		jQuery(document).ready(() => {
			jQuery("body").on("latepoint:initPaymentMethod", ".latepoint-booking-form-element", (function(e, data) {
				if("knit_pay" == data.payment_method) {
					var n = jQuery(e.currentTarget);
					n.find(".latepoint-form");
					latepoint_add_action(data.callbacks_list, (function() {
						latepoint_show_next_btn(n)
					}))
				}
			}));
			jQuery('body').on('latepoint:submitBookingForm', '.latepoint-booking-form-element', (e, data) => {
				if(!latepoint_helper.demo_mode && data.is_final_submit && data.direction == 'next') {
					let payment_method = jQuery(e.currentTarget).find('input[name="booking[payment_method]"]').val();
					switch(payment_method) {
						case 'knit_pay':
							latepoint_add_action(data.callbacks_list, () => {
								return this.initPaymentWindow(jQuery(e.currentTarget), data.payment_method);
							});
							break;
					}
				}
			});
		});
	}
	initPaymentWindow($booking_form_element, payment_method) {
		let deferred = jQuery.Deferred();
		var data = {
			action: 'latepoint_route_call',
			route_name: latepoint_helper.knit_pay_payment_options_route,
			params: $booking_form_element.find('.latepoint-form').serialize(),
			layout: 'none',
			return_format: 'json'
		}
		jQuery.ajax({
			type: "post",
			dataType: "json",
			url: latepoint_helper.ajaxurl,
			data: data,
			success: (data) => {
				if(data.status === "success") {
					if(data.amount > 0) {
						$booking_form_element.find('input[name="booking[payment_token]"]').val(data.knitpay_payment_id);
						return this.openPaymentWindow(data.knitpay_payment_url, deferred);
					}
					deferred.resolve();
				} else {
					deferred.reject({
						message: data.message
					});
				}
			},
			error: function(request, status, error) {
				deferred.reject({
					message: error
				});
			}
		});
		return deferred;
	}
	openPaymentWindow(payment_url, deferred) {
		var paymentWindow = window.open(payment_url, "knitpayPaymentWindow", "width=800,height=600");
		var timer = setInterval(function() {
			if(paymentWindow.closed) {
				clearInterval(timer);
				deferred.resolve();
			}
		}, 1000);
	}
}
let latepointPaymentsKnitPayAddon = new LatepointPaymentsKnitPayAddon();