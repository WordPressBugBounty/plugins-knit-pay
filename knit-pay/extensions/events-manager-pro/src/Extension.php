<?php

namespace KnitPay\Extensions\EventsManagerPro;

use Pronamic\WordPress\Pay\AbstractPluginIntegration;
use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;
use EM_Gateways;
use Pronamic\WordPress\Pay\Payments\Payment;

/**
 * Title: Events Manager Pro extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   3.2.0
 */
class Extension extends AbstractPluginIntegration {
	/**
	 * Slug
	 *
	 * @var string
	 */
	const SLUG = 'events-manager-pro';

	/**
	 * Constructs and initialize Lifter LMS extension.
	 */
	public function __construct() {
		parent::__construct(
			[
				'name' => __( 'Events Manager Pro', 'knit-pay-lang' ),
			]
		);

		// Dependencies.
		$dependencies = $this->get_dependencies();

		$dependencies->add( new EventsManagerProDependency() );
	}

	/**
	 * Setup plugin integration.
	 *
	 * @return void
	 */
	public function setup() {
		add_filter( 'pronamic_payment_source_text_' . self::SLUG, [ $this, 'source_text' ], 10, 2 );
		add_filter( 'pronamic_payment_source_description_' . self::SLUG, [ $this, 'source_description' ], 10, 2 );
		add_filter( 'pronamic_payment_source_url_' . self::SLUG, [ $this, 'source_url' ], 10, 2 );

		// Check if dependencies are met and integration is active.
		if ( ! $this->is_active() ) {
			return;
		}

		add_filter( 'pronamic_payment_redirect_url_' . self::SLUG, [ $this, 'redirect_url' ], 10, 2 );
		add_action( 'pronamic_payment_status_update_' . self::SLUG, [ $this, 'status_update' ], 10 );

		add_action( 'em_gateways_init', [ $this, 'em_gateways_init' ] );
		add_filter( 'em_get_currencies', [ $this, 'em_get_currencies' ], 10, 1 );
	}

	public static function em_gateways_init( $gateways ) {
		require_once 'Gateway.php';
		require_once 'Helper.php';

		EM_Gateways::register_gateway( 'knit_pay', __NAMESPACE__ . '\Gateway' );
	}

	public function em_get_currencies( $currencies ) {
		$currencies->names        = [ 'INR' => __( 'INR - Indian Rupee', 'knit-pay-lang' ) ] + $currencies->names;
		$currencies->symbols      = [ 'INR' => __( 'Rs.', 'knit-pay-lang' ) ] + $currencies->symbols;
		$currencies->true_symbols = [ 'INR' => '₹' ] + $currencies->true_symbols;

		return $currencies;
	}

	/**
	 * Payment redirect URL filter.
	 *
	 * @param string  $url     Redirect URL.
	 * @param Payment $payment Payment.
	 *
	 * @return string
	 */
	public static function redirect_url( $url, $payment ) {
		$booking_id = (int) $payment->get_source_id();
		$EM_Booking = em_get_booking( $booking_id );
		$gateway    = new Gateway();

		$url = $gateway->get_return_url( $EM_Booking );

		switch ( $payment->get_status() ) {
			case Core_Statuses::CANCELLED:
			case Core_Statuses::EXPIRED:
			case Core_Statuses::FAILURE:
				$url = $gateway->get_cancel_url( $EM_Booking );
				break;

			case Core_Statuses::SUCCESS:
				$url = $gateway->get_return_url( $EM_Booking );
				break;

			case Core_Statuses::AUTHORIZED:
			case Core_Statuses::OPEN:
				$url = $EM_Booking->get_event()->get_permalink();
		}

		return $url;
	}

	/**
	 * Update the status of the specified payment
	 *
	 * @param Payment $payment Payment.
	 */
	public static function status_update( Payment $payment ) {

		$booking_id     = (int) $payment->get_source_id();
		$EM_Booking     = em_get_booking( $booking_id );
		$amount         = $payment->get_total_amount()->number_format( null, '.', '' );
		$currency       = get_option( 'dbem_bookings_currency', 'INR' );
		$timestamp      = $payment->get_date()->getTimestamp();
		$txn_id         = $payment->get_transaction_id();
		$payment_status = $payment->get_status_label();

		$gateway = new Gateway();

		switch ( $payment->get_status() ) {
			case Core_Statuses::CANCELLED:
			case Core_Statuses::EXPIRED:
				$note = 'Last transaction has been reversed. Reason: Payment Cancelled';
				$gateway->record_transaction( $EM_Booking, $amount, $currency, $timestamp, $txn_id, $payment_status, $note );

				$EM_Booking->cancel();
				do_action( 'em_payment_denied', $EM_Booking, $gateway );

				break;
			case Core_Statuses::FAILURE:
				$note = 'Last transaction has been reversed. Reason: Payment Failed';
				$gateway->record_transaction( $EM_Booking, $amount, $currency, $timestamp, $txn_id, $payment_status, $note );

				$EM_Booking->cancel();
				do_action( 'em_payment_denied', $EM_Booking, $gateway );

				break;
			case Core_Statuses::SUCCESS:
				$note = 'Payment Successful';
				$gateway->record_transaction( $EM_Booking, $amount, $currency, $timestamp, $txn_id, $payment_status, $note );

				$EM_Booking->approve( true, true );
				do_action( 'em_payment_processed', $EM_Booking, $gateway );
				break;
			case Core_Statuses::OPEN:
			default:
				break;
		}
	}

	/**
	 * Source column
	 *
	 * @param string  $text    Source text.
	 * @param Payment $payment Payment.
	 *
	 * @return string $text
	 */
	public function source_text( $text, Payment $payment ) {
		$text = __( 'Events Manager Pro', 'knit-pay-lang' ) . '<br />';

		$text .= sprintf(
			'<a href="%s">%s</a>',
			get_edit_post_link( $payment->source_id ),
			/* translators: %s: source id */
			sprintf( __( 'Booking %s', 'knit-pay-lang' ), $payment->source_id )
		);

		return $text;
	}

	/**
	 * Source description.
	 *
	 * @param string  $description Description.
	 * @param Payment $payment     Payment.
	 *
	 * @return string
	 */
	public function source_description( $description, Payment $payment ) {
		return __( 'Events Manager Pro Booking', 'knit-pay-lang' );
	}

	/**
	 * Source URL.
	 *
	 * @param string  $url     URL.
	 * @param Payment $payment Payment.
	 *
	 * @return string
	 */
	public function source_url( $url, Payment $payment ) {
		return get_edit_post_link( $payment->source_id );
	}

}
