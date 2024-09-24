<?php

namespace KnitPay\Extensions\MotopressHotelBooking;

use Pronamic\WordPress\Pay\AbstractPluginIntegration;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: MotoPress Hotel Booking extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   3.6.0
 */
class Extension extends AbstractPluginIntegration {
	/**
	 * Slug
	 *
	 * @var string
	 */
	const SLUG = 'motopress-hotel-booking';

	/**
	 * Constructs and initialize MotoPress Hotel Booking extension.
	 */
	public function __construct() {
		parent::__construct(
			[
				'name' => __( 'MotoPress Hotel Booking', 'knit-pay-lang' ),
			]
		);

		// Dependencies.
		$dependencies = $this->get_dependencies();

		$dependencies->add( new MotoPressHotelBookingDependency() );
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

		require_once 'Helper.php';
		require_once 'Gateway.php';
		new Gateway( 'Knit Pay', 'knit_pay' );
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
		$payment_id = (int) $payment->get_order_id();

		$hb_payment = MPHB()->getPaymentRepository()->findById( $payment_id );
		
		$hb_settings_pages = MPHB()->settings()->pages();

		switch ( $payment->get_status() ) {
			case Core_Statuses::CANCELLED:
			case Core_Statuses::EXPIRED:
			case Core_Statuses::FAILURE:
				$redirect_url = $hb_settings_pages->getPaymentFailedPageUrl( $hb_payment );
				break;

			case Core_Statuses::SUCCESS:
				if ( method_exists( $hb_settings_pages, 'getPaymentSuccessPageUrl' ) ) {
					$redirect_url = $hb_settings_pages->getPaymentSuccessPageUrl( $hb_payment );
					break;
				}
				$redirect_url = $hb_settings_pages->getReservationReceivedPageUrl( $hb_payment );
				break;

			case Core_Statuses::AUTHORIZED:
			case Core_Statuses::OPEN:
			default:
				$redirect_url = home_url( '/' );
		}

		if ( ! empty( $redirect_url ) ) {
			return $redirect_url;
		}
		return $url;
	}

	/**
	 * Update the status of the specified payment
	 *
	 * @param Payment $payment Payment.
	 */
	public static function status_update( Payment $payment ) {

		$payment_id = (int) $payment->get_order_id();

		$hb_payment = MPHB()->getPaymentRepository()->findById( $payment_id );

		$hb_payment->setTransactionId( mphb_clean( $payment->get_transaction_id() ) );

		$is_updated = false;

		switch ( $payment->get_status() ) {

			case Core_Statuses::CANCELLED:
			case Core_Statuses::EXPIRED:
				$is_updated = MPHB()->paymentManager()->failPayment( $hb_payment, 'Payment Cancelled.' );

				break;
			case Core_Statuses::FAILURE:
				$is_updated = MPHB()->paymentManager()->failPayment( $hb_payment, 'Payment Failed' );

				break;
			case Core_Statuses::SUCCESS:
				$is_updated = MPHB()->paymentManager()->completePayment( $hb_payment );

				break;
			case Core_Statuses::ON_HOLD:
				$is_updated = MPHB()->paymentManager()->holdPayment( $hb_payment );
				break;
			case Core_Statuses::REFUNDED:
				$is_updated = MPHB()->paymentManager()->refundPayment( $hb_payment );
				break;
			case Core_Statuses::OPEN:
			default:
				break;
		}
		if ( ! $is_updated ) {
			$payment->add_note( 'Failed to update status in Hotel Booking Platform' );
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
		$text = __( 'MotoPress Hotel Booking', 'knit-pay-lang' ) . '<br />';

		$booking_id = $payment->get_source_id();

		$text .= sprintf(
			'<a href="%s">%s</a>',
			get_edit_post_link( $booking_id ),
			/* translators: %s: source id */
			sprintf( __( 'ID %s', 'knit-pay-lang' ), $booking_id )
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
		return __( 'MotoPress Hotel Booking', 'knit-pay-lang' );
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
		return get_edit_post_link( $payment->get_source_id() );
	}

}
