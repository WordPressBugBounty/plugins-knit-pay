<?php

namespace KnitPay\Extensions\BookingPress;

use Pronamic\WordPress\Money\Currency;
use Pronamic\WordPress\Money\Money;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Plugin;

/**
 * Title: BookingPress Vue 3 Gateway
 * Description: Integrates Knit Pay with the BookingPress Vue 3 booking form
 *              (1.5.6+/1.6.x) via the bookingpress_form_v3_* filters:
 *              payment_methods registers the card, submit_envelope converts
 *              the pending_payment envelope into a redirect_url envelope.
 *              The status callback (Extension::status_update) is shared with
 *              the legacy flow.
 * Copyright: 2020-2026 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   8.90.0.0
 */
class Vue3Gateway {
	/**
	 * Knit Pay payment method id as used in the Vue 3 payment methods list.
	 *
	 * @var string
	 */
	const PAYMENT_METHOD_ID = 'knit_pay';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'bookingpress_form_v3_payment_methods', [ $this, 'add_payment_method' ], 10, 2 );
		add_filter( 'bookingpress_form_v3_submit_envelope', [ $this, 'handle_submit_envelope' ], 10, 2 );
	}

	/**
	 * Whether the Knit Pay gateway is enabled in the BookingPress payment
	 * settings.
	 *
	 * @return bool
	 */
	private function is_enabled() {
		global $BookingPress;

		if ( ! isset( $BookingPress ) || ! method_exists( $BookingPress, 'bookingpress_get_settings' ) ) {
			return false;
		}

		$setting = $BookingPress->bookingpress_get_settings( 'knit_pay_payment', 'payment_setting' );

		return ( 'true' === strtolower( (string) $setting ) || '1' === (string) $setting );
	}

	/**
	 * Register the Knit Pay payment method card on the Vue 3 booking form.
	 *
	 * @param array $methods Enabled payment methods.
	 * @param array $context Context (contains `context` key, e.g. booking_form).
	 *
	 * @return array
	 */
	public function add_payment_method( $methods, $context = [] ) {
		if ( ! $this->is_enabled() ) {
			return $methods;
		}

		// Skip the Pro "Complete Payment" flow: it resolves the remaining
		// balance through its own intercept seam, not the submit envelope
		// handled below.
		$form_context = isset( $context['context'] ) ? (string) $context['context'] : 'booking_form';
		if ( 'complete_payment' === $form_context ) {
			return $methods;
		}

		$methods[] = [
			'id'    => self::PAYMENT_METHOD_ID,
			'label' => $this->get_front_label(),
			'mode'  => 'redirect',
			'icon'  => $this->get_icon_svg(),
			'extra' => [],
		];

		return $methods;
	}

	/**
	 * Front-end card label. Mirrors the legacy card's "Online Payment"
	 * heading and honours the customized gateway text when set.
	 *
	 * @return string
	 */
	private function get_front_label() {
		global $BookingPress;

		$label = '';

		if ( isset( $BookingPress ) && method_exists( $BookingPress, 'bookingpress_get_customize_settings' ) ) {
			$label = (string) $BookingPress->bookingpress_get_customize_settings( 'knit_pay_text', 'booking_form' );
		}

		if ( '' === $label ) {
			$label = __( 'Online Payment', 'knit-pay-lang' );
		}

		return $label;
	}

	/**
	 * Inline SVG icon for the payment card (safe for the frontend's v-html
	 * render: static string, no external references, no scripts).
	 *
	 * @return string
	 */
	private function get_icon_svg() {
		return '<svg xmlns="http://www.w3.org/2000/svg" enable-background="new 0 0 24 24" viewBox="0 0 24 24" width="24" height="24"><g><rect fill="none" height="24" width="24"/></g><g><path d="M21.9,7.89l-1.05-3.37c-0.22-0.9-1-1.52-1.91-1.52H5.05c-0.9,0-1.69,0.63-1.9,1.52L2.1,7.89C1.64,9.86,2.95,11,3,11.06V19 c0,1.1,0.9,2,2,2h14c1.1,0,2-0.9,2-2v-7.94C22.12,9.94,22.09,8.65,21.9,7.89z M13,5h1.96l0.54,3.52C15.59,9.23,15.11,10,14.22,10 C13.55,10,13,9.41,13,8.69V5z M6.44,8.86C6.36,9.51,5.84,10,5.23,10C4.3,10,3.88,9.03,4.04,8.36L5.05,5h1.97L6.44,8.86z M11,8.69 C11,9.41,10.45,10,9.71,10c-0.75,0-1.3-0.7-1.22-1.48L9.04,5H11V8.69z M18.77,10c-0.61,0-1.14-0.49-1.21-1.14L16.98,5l1.93-0.01 l1.05,3.37C20.12,9.03,19.71,10,18.77,10z"/></g></svg>';
	}

	/**
	 * Convert the pending_payment submit envelope for Knit Pay bookings into
	 * a redirect_url envelope carrying the Knit Pay payment page URL. The
	 * Vue 3 client redirects generically on variant: redirect_url.
	 *
	 * Security: runs inside the nonce-gated REST controller; all amount,
	 * currency and customer data is read from the server-authoritative
	 * staged entry, never from client-supplied values.
	 *
	 * @param array $envelope Submit response envelope.
	 * @param array $payload  Raw submit payload (already sanitized upstream).
	 *
	 * @return array
	 */
	public function handle_submit_envelope( $envelope, $payload = [] ) {
		// Only intercept the staged pending_payment envelope.
		if ( ! isset( $envelope['variant'] ) || 'pending_payment' !== (string) $envelope['variant'] ) {
			return $envelope;
		}

		// Only for the Knit Pay gateway selection.
		$gateway = isset( $envelope['gateway'] ) ? (string) $envelope['gateway'] : ( isset( $payload['selected_payment_method'] ) ? (string) $payload['selected_payment_method'] : '' );
		if ( self::PAYMENT_METHOD_ID !== $gateway ) {
			return $envelope;
		}

		if ( ! $this->is_enabled() ) {
			return $envelope;
		}

		$entry_id = isset( $envelope['entry_id'] ) ? (int) $envelope['entry_id'] : 0;
		if ( $entry_id <= 0 ) {
			return $this->envelope_error( $envelope, __( 'Could not stage the booking for Knit Pay.', 'knit-pay-lang' ) );
		}

		// Load the staged entry (server-authoritative amount / currency / customer).
		if ( ! class_exists( '\BookingPress\Vue3\Repositories\EntryRepository' ) ) {
			return $this->envelope_error( $envelope, __( 'BookingPress entry repository is not available.', 'knit-pay-lang' ) );
		}

		$entry = ( new \BookingPress\Vue3\Repositories\EntryRepository() )->find( $entry_id );
		if ( ! is_array( $entry ) || empty( $entry ) ) {
			return $this->envelope_error( $envelope, __( 'Could not load the staged booking for Knit Pay.', 'knit-pay-lang' ) );
		}

		try {
			$pay_url = $this->create_payment_and_get_pay_url( $entry_id, $entry );
		} catch ( \Exception $e ) {
			return $this->envelope_error( $envelope, $e->getMessage() );
		}

		if ( '' === $pay_url ) {
			return $this->envelope_error( $envelope, __( 'Could not start the Knit Pay payment. Please check the gateway configuration.', 'knit-pay-lang' ) );
		}

		return [
			'variant'       => 'redirect_url',
			'is_redirect'   => 1,
			'redirect_data' => $pay_url,
			'entry_id'      => $entry_id,
		];
	}

	/**
	 * Create the Knit Pay payment for the staged entry and return its pay URL.
	 *
	 * @param int   $entry_id BookingPress entry id.
	 * @param array $entry     Staged entry row (server-authoritative data).
	 *
	 * @return string Pay redirect URL.
	 * @throws \Exception When no gateway configuration is available or the
	 *                    payment cannot be started.
	 */
	private function create_payment_and_get_pay_url( $entry_id, array $entry ) {
		$config_id = $this->get_config_id();
		if ( empty( $config_id ) ) {
			throw new \Exception( __( 'No Knit Pay payment configuration is available. Please select a configuration in the BookingPress settings.', 'knit-pay-lang' ) );
		}

		$gateway = Plugin::get_gateway( $config_id );
		if ( ! $gateway ) {
			throw new \Exception( __( 'The selected Knit Pay payment configuration is not available.', 'knit-pay-lang' ) );
		}

		$currency_code = isset( $entry['bookingpress_service_currency'] ) && '' !== $entry['bookingpress_service_currency']
			? strtoupper( (string) $entry['bookingpress_service_currency'] )
			: 'USD';

		$payable_amount = isset( $entry['bookingpress_paid_amount'] ) ? (float) $entry['bookingpress_paid_amount'] : 0.0;
		if ( $payable_amount <= 0 ) {
			$payable_amount = isset( $entry['bookingpress_service_price'] ) ? (float) $entry['bookingpress_service_price'] : 0.0;
		}

		$customer_details = [
			'customer_firstname' => isset( $entry['bookingpress_customer_firstname'] ) ? (string) $entry['bookingpress_customer_firstname'] : '',
			'customer_lastname'  => isset( $entry['bookingpress_customer_lastname'] ) ? (string) $entry['bookingpress_customer_lastname'] : '',
			'customer_email'     => isset( $entry['bookingpress_customer_email'] ) ? (string) $entry['bookingpress_customer_email'] : '',
			'customer_phone'     => isset( $entry['bookingpress_customer_phone'] ) ? (string) $entry['bookingpress_customer_phone'] : '',
		];

		$bookingpress_return_data = [
			'entry_id'     => $entry_id,
			'service_data' => [
				'bookingpress_service_name' => isset( $entry['bookingpress_service_name'] ) ? (string) $entry['bookingpress_service_name'] : '',
			],
		];

		// Create payment.
		$payment = new Payment();

		$payment->source    = 'bookingpress';
		$payment->source_id = $entry_id;
		$payment->order_id  = $entry_id;

		$payment->title = Helper::get_title( $entry_id );

		// Set description.
		$payment->set_description( Helper::get_description( $bookingpress_return_data ) );

		// Customer.
		$payment->set_customer( Helper::get_customer_from_customer_details( $customer_details ) );

		// Address.
		$payment->set_billing_address( Helper::get_address_from_customer_details( $customer_details ) );

		// Set amount (server-authoritative staged amount).
		$payment->set_total_amount( new Money( $payable_amount, Currency::get_instance( $currency_code ) ) );

		// Set payment method.
		$payment->set_payment_method( Gateway::get_id() );

		// Set configuration.
		$payment->config_id = $config_id;

		$payment = Plugin::start_payment( $payment );

		// Store thank-you / cancel URLs as payment meta for Extension::redirect_url.
		$payment->set_meta( 'approved_appointment_url', $this->build_redirect_url( $entry_id ) );
		$payment->set_meta( 'canceled_appointment_url', $this->build_cancel_url() );
		$payment->set_meta( 'pending_appointment_url', $this->build_redirect_url( $entry_id ) );
		$payment->save();

		$pay_url = $payment->get_pay_redirect_url();
		if ( empty( $pay_url ) ) {
			return '';
		}

		return $pay_url;
	}

	/**
	 * Knit Pay configuration id selected in the BookingPress settings, with
	 * fallback to the Knit Pay default configuration.
	 *
	 * @return int|string
	 */
	private function get_config_id() {
		global $BookingPress;

		$config_id = 0;

		if ( isset( $BookingPress ) && method_exists( $BookingPress, 'bookingpress_get_settings' ) ) {
			$config_id = $BookingPress->bookingpress_get_settings( 'knit_pay_config_id', 'payment_setting' );
		}

		// Use default gateway if no configuration has been set.
		if ( empty( $config_id ) ) {
			$config_id = get_option( 'pronamic_pay_config_id' );
		}

		return $config_id;
	}

	/**
	 * Thank-you URL for a staged entry (same base64(entry_id) + nonce
	 * convention BookingPress core uses).
	 *
	 * @param int $entry_id Entry id.
	 *
	 * @return string
	 */
	private function build_redirect_url( $entry_id ) {
		$service = null;

		if ( class_exists( '\BookingPress\Vue3\Services\ServiceLocator' )
			&& class_exists( '\BookingPress\Vue3\Contracts\SubmissionServiceInterface' ) ) {
			try {
				$service = \BookingPress\Vue3\Services\ServiceLocator::get( \BookingPress\Vue3\Contracts\SubmissionServiceInterface::class );
			} catch ( \Throwable $e ) {
				$service = null;
			}
		}

		// Fallback: construct the service directly (e.g. during REST requests).
		if ( ( ! $service || ! method_exists( $service, 'build_redirect_url_for_entry' ) )
			&& class_exists( '\BookingPress\Vue3\Services\SubmissionService' ) ) {
			try {
				$service = new \BookingPress\Vue3\Services\SubmissionService();
			} catch ( \Throwable $e ) {
				$service = null;
			}
		}

		if ( $service && method_exists( $service, 'build_redirect_url_for_entry' ) ) {
			return (string) $service->build_redirect_url_for_entry( $entry_id );
		}

		// Final fallback: the configured after-booking page (or home).
		$page_id = 0;
		if ( class_exists( '\BookingPress\Vue3\Repositories\CustomizeRepository' ) ) {
			$page_id = (int) ( new \BookingPress\Vue3\Repositories\CustomizeRepository() )->get(
				'after_booking_redirection',
				\BookingPress\Vue3\Repositories\CustomizeRepository::GROUP_BOOKING_FORM,
				0
			);
		}

		$base = ( $page_id > 0 ) ? get_permalink( $page_id ) : home_url( '/' );

		return '' !== (string) $base ? (string) $base : home_url( '/' );
	}

	/**
	 * Cancel URL from the "after failed payment" customize setting.
	 *
	 * @return string
	 */
	private function build_cancel_url() {
		$page_id = 0;
		if ( class_exists( '\BookingPress\Vue3\Repositories\CustomizeRepository' ) ) {
			$page_id = (int) ( new \BookingPress\Vue3\Repositories\CustomizeRepository() )->get(
				'after_failed_payment_redirection',
				\BookingPress\Vue3\Repositories\CustomizeRepository::GROUP_BOOKING_FORM,
				0
			);
		}

		$cancel_url = ( $page_id > 0 ) ? get_permalink( $page_id ) : home_url( '/' );
		if ( empty( $cancel_url ) ) {
			$cancel_url = home_url( '/' );
		}

		return add_query_arg( 'is_cancel', 1, $cancel_url );
	}

	/**
	 * Error envelope the client surfaces as a toast; the staged entry stays
	 * pending so the customer can retry.
	 *
	 * @param array  $envelope Current envelope.
	 * @param string $message  Error message.
	 *
	 * @return array
	 */
	private function envelope_error( array $envelope, $message ) {
		return [
			'variant'       => 'error',
			'error_code'    => 'knit_pay_payment_failed',
			'error_message' => $message,
			'entry_id'      => isset( $envelope['entry_id'] ) ? (int) $envelope['entry_id'] : 0,
		];
	}
}