class LatepointPaymentsKnitPayAddon {
	constructor() {
		this.payment_url = null;
		this.ready();
	}

	ready() {
		jQuery(document).ready(() => {

			/**
			 * Payment step loaded – pre-create the Knit Pay payment via AJAX so the
			 * popup URL is ready BEFORE the user clicks "Pay Now".  Opening a popup
			 * inside a synchronous click handler avoids browser popup-blockers.
			 */
			jQuery('body').on('latepoint:initPaymentMethod', '.latepoint-booking-form-element', (e, data) => {
				if ('knit_pay' !== data.payment_method) return;
				const $formElement = jQuery(e.currentTarget);
				latepoint_add_action(data.callbacks_list, async () => {
					// Normal path: show loading UI, set Pay Now label on success.
					return await this.preparePayment($formElement, false);
				});
			});

			/**
			 * "Pay Now" clicked – open the pre-stored popup URL synchronously so
			 * browsers don't block it.  If the URL was already consumed (retry after a
			 * failed payment and initPaymentMethod did not re-fire), re-create the
			 * payment silently before opening the window.
			 */
			jQuery('body').on('latepoint:submitBookingForm', '.latepoint-booking-form-element', (e, data) => {
				if (latepoint_helper.demo_mode || !data.is_final_submit || data.direction !== 'next') return;

				const payment_method = jQuery(e.currentTarget).find('input[name="cart[payment_method]"]').val();
				if ('knit_pay' !== payment_method) return;

				const $formElement = jQuery(e.currentTarget);

				latepoint_add_action(data.callbacks_list, async () => {
					// Retry path: silent=true keeps the button and UI panels untouched
					// while the AJAX call runs (avoids flicker inside an active submission).
					if (!this.payment_url) {
						try {
							await this.preparePayment($formElement, true);
						} catch (err) {
							return jQuery.Deferred().reject({ message: err.message }).promise();
						}
					}
					return this.openPaymentWindowAndWait($formElement);
				});
			});

		});
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Core methods
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * AJAX – ask the server to create a Knit Pay payment.
	 * Stores the URL, sets the payment token on the hidden form field, and
	 * (unless silent) updates the panel messages and the Next button label.
	 *
	 * @param {jQuery}  $formElement  .latepoint-booking-form-element wrapper
	 * @param {boolean} silent        true on retry – skip button/panel changes
	 * @returns {Promise}
	 */
	async preparePayment($formElement, silent) {
		this.payment_url = null;

		if (!silent) {
			$formElement.find('.lp-knitpay-loading').show();
			$formElement.find('.lp-knitpay-pay-notice, .lp-knitpay-waiting, .lp-knitpay-blocked').hide();
			latepoint_hide_next_btn($formElement);
		}

		const formData    = new FormData($formElement.find('.latepoint-form')[0]);
		const requestData = {
			action:        'latepoint_route_call',
			route_name:    latepoint_helper.knit_pay_payment_options_route,
			params:        latepoint_formdata_to_url_encoded_string(formData),
			layout:        'none',
			return_format: 'json',
		};

		let response;
		try {
			response = await jQuery.ajax({
				type:     'post',
				dataType: 'json',
				url:      latepoint_timestamped_ajaxurl(),
				data:     requestData,
			});
		} catch (err) {
			if (!silent) $formElement.find('.lp-knitpay-loading').hide();
			throw new Error(err.statusText || 'Network error while preparing payment.');
		}

		if (!silent) $formElement.find('.lp-knitpay-loading').hide();

		if (response.status === 'success') {
			if (response.amount > 0) {
				$formElement.find('input[name="cart[payment_token]"]').val(response.knitpay_payment_id);
				this.payment_url = response.knitpay_payment_url;
				if (!silent) $formElement.find('.lp-knitpay-pay-notice').show();
			}

			if (!silent) {
				latepoint_show_next_btn($formElement);
				// Only relabel the button when there is an actual charge.
				// For free/zero bookings keep the original "Next" / "Confirm" label.
				if (response.amount > 0) {
					this._applyPayNowLabel($formElement);
				}
			}
		} else {
			throw new Error(response.message || 'Unknown error while preparing payment.');
		}
	}

	/**
	 * Opens the payment popup and returns a deferred that resolves when it
	 * closes.  Three scenarios are handled:
	 *
	 *   a) Normal popup  – waits for window.closed, then resolves.
	 *   b) Popup blocked – displays a fallback panel with a direct link and
	 *                      "I've Completed Payment" / "Cancel" buttons.
	 *   c) Free booking  – payment_url is null, resolves immediately.
	 *
	 * @param {jQuery} $formElement
	 * @returns {jQuery.Deferred}
	 */
	openPaymentWindowAndWait($formElement) {
		const deferred = jQuery.Deferred();

		if (!this.payment_url) {
			// Free booking – nothing to pay in the payment window.
			deferred.resolve();
			return deferred;
		}

		const url        = this.payment_url;
		this.payment_url = null; // mark as consumed

		$formElement.find('.lp-knitpay-pay-notice').hide();
		$formElement.find('.lp-knitpay-waiting').show();

		const paymentWindow = window.open(
			url,
			'knitpayPaymentWindow',
			'width=820,height=640,scrollbars=yes,resizable=yes'
		);

		if (!paymentWindow || paymentWindow.closed || typeof paymentWindow.closed === 'undefined') {
			// Popup was blocked by the browser – show the graceful fallback.
			this._showBlockedFallback($formElement, url, deferred);
			return deferred;
		}

		// Poll until the popup window is closed.
		const timer = setInterval(() => {
			if (paymentWindow.closed) {
				clearInterval(timer);
				$formElement.find('.lp-knitpay-waiting').hide();
				deferred.resolve();
			}
		}, 800);

		return deferred;
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Private helpers
	// ─────────────────────────────────────────────────────────────────────────

	/** Replace the Next button label with the configured "Pay Now" text. */
	_applyPayNowLabel($formElement) {
		const label = latepoint_helper.knit_pay_pay_btn_label || 'Pay Now';
		$formElement.find('.latepoint-next-btn span').text(label);
	}

	/**
	 * Show the popup-blocked fallback panel with:
	 *   • A direct link (<a target="_blank">) – always opens successfully.
	 *   • "I've Completed Payment" button → resolves the deferred, allowing
	 *     the form to submit; the server validates payment status.
	 *   • "Cancel" button → restores payment_url so the user can retry.
	 */
	_showBlockedFallback($formElement, url, deferred) {
		$formElement.find('.lp-knitpay-waiting').hide();

		const $blocked = $formElement.find('.lp-knitpay-blocked');
		$blocked.find('.lp-knitpay-blocked-link').attr('href', url);
		$blocked.show();

		$blocked.find('.lp-knitpay-done-btn').off('click.knitpay').on('click.knitpay', (evt) => {
			evt.preventDefault();
			$blocked.hide();
			deferred.resolve();
		});

		$blocked.find('.lp-knitpay-cancel-btn').off('click.knitpay').on('click.knitpay', (evt) => {
			evt.preventDefault();
			$blocked.hide();
			this.payment_url = url; // restore so the next attempt reuses the same payment
			// Reject with an empty message – LatePoint will not surface an error
			// notification for an empty string, so the user simply stays on the
			// payment step, ready to try again.
			deferred.reject({ message: '' });
		});
	}
}

let latepointPaymentsKnitPayAddon = new LatepointPaymentsKnitPayAddon();
