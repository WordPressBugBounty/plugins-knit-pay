<?php

namespace KnitPay\Extensions\TeamBooking;

use Pronamic\WordPress\Pay\AbstractPluginIntegration;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;
use TeamBooking\Functions as TeamBookingFunctions;
use TeamBooking_Reservation;
use TeamBooking_Error;

/**
 * Title: Team Booking extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   5.1.0
 */
class Extension extends AbstractPluginIntegration {
	/**
	 * Slug
	 *
	 * @var string
	 */
	const SLUG = 'team-booking';

	/**
	 * Constructs and initialize Team Booking extension.
	 */
	public function __construct() {
		parent::__construct(
			[
				'name' => __( 'Team Booking', 'knit-pay-lang' ),
			]
		);

		// Dependencies.
		$dependencies = $this->get_dependencies();

		$dependencies->add( new TeamBookingDependency() );
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

		$team_booking_settings         = TeamBookingFunctions\getSettings();
		$team_booking_payment_gateways = $team_booking_settings->getPaymentGatewaySettingObjects();
		if ( ! isset( $team_booking_payment_gateways['knit_pay'] ) ) {
			$team_booking_settings->addPaymentGatewaySettingObject( new Gateway() );
		}
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
		return $url;
	}

	/**
	 * Update the status of the specified payment
	 *
	 * @param Payment $payment Payment.
	 */
	public static function status_update( Payment $payment ) {
		$reservations = self::get_reservations( $payment );
		if ( empty( $reservations ) ) {
			return;
		}

		$payment_details_array                            = [];
		$payment_details_array['transaction id']          = $payment->get_transaction_id();
		$payment_details_array['paid amount']             = $payment->get_total_amount()->get_value();
		$payment_details_array['Knit Pay Payment Status'] = $payment->get_status();

		foreach ( $reservations as $reservation_data ) {
			$reservation_data->setPaymentGateway( $payment->get_payment_method() );
			$reservation_data->setPaymentDetails( $payment_details_array );

			$reservation = new TeamBooking_Reservation( $reservation_data );
			switch ( $payment->get_status() ) {
				case Core_Statuses::CANCELLED:
					$reservation_data->setPaid( false );
					$reservation_data->setToBePaid( true );
					$reservation_data->setStatusCancelled();
					$reservation->cancelReservation( $reservation_data->getDatabaseId() );

					break;
				case Core_Statuses::EXPIRED:
				case Core_Statuses::FAILURE:
					$reservation_data->setPaid( false );
					$reservation_data->setToBePaid( true );
					$reservation_data->setStatusPending();
					$reservation_data->setPendingReason( 'Payment Failed.' );
					$reservation->cancelReservation( $reservation_data->getDatabaseId() );

					break;
				case Core_Statuses::SUCCESS:
					$reservation_data->setPaid( true );
					$attempted = $reservation->doReservation();
					// Check for errors
					if ( $attempted instanceof TeamBooking_Error ) {
						// At this point, payment is made but
						// the reservation attempt throws errors.
						$reservation_data->setStatusPending();
						$errmsg = $attempted->getMessage();
						$reservation_data->setPendingReason( $errmsg );
						error_log( 'Reservation error after payment is done' );
					} else {
						// No errors
						$reservation_data->setStatusConfirmed();
						// Send e-mail messages
						if ( $reservation->getServiceObj()->getEmailToAdmin( 'send' ) ) {
							$reservation->sendNotificationEmail();
						}
						if ( TeamBookingFunctions\getSettings()->getCoworkerData( $reservation_data->getCoworker() )->getCustomEventSettings( $reservation_data->getServiceId() )->getGetDetailsByEmail() ) {
							$reservation->sendNotificationEmailToCoworker();
						}
						if ( $reservation_data->getCustomerEmail() && $reservation->getServiceObj()->getEmailToCustomer( 'send' ) ) {
							$reservation->sendConfirmationEmail();
						}
					}

					break;
				case Core_Statuses::OPEN:
				default:
					$reservation_data->setPaid( false );
					$reservation_data->setToBePaid( true );
					$reservation_data->setStatusPending();
					$reservation->cancelReservation( $reservation_data->getDatabaseId() );

					break;
			}
			// Update Reservation Data.
			\TeamBooking\Database\Reservations::update( $reservation_data );
		}
	}

	private static function get_reservations( $payment ) {
		$order_id = $payment->get_meta( 'team_booking_reservation_order_id' );
		if ( empty( $order_id ) ) {
			return [];
		}

		$reservation_data   = \TeamBooking\Database\Reservations::getByToken( $order_id );
		$reservations_order = \TeamBooking\Database\Reservations::getByOrderId( $order_id );
		$order              = new \TeamBooking\Order();
		$order->setItems( $reservations_order );
		$order->setId( $order_id );

		if ( ! $reservation_data && empty( $reservations_order ) ) { // Reservation is not found!
			error_log( 'Reservation is not found: ' . $order_id );
			return [];
		}

		$reservations = empty( $reservations_order ) ? [ $reservation_data ] : $reservations_order;
		return $reservations;
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
		$text = __( 'Team Booking', 'knit-pay-lang' ) . '<br />';

		$text .= sprintf(
			'<a href="%s">%s</a>',
			admin_url( '/admin.php?page=team-booking' ),
			/* translators: %s: source id */
			sprintf( __( 'Reservation %s', 'knit-pay-lang' ), $payment->source_id )
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
		return __( 'Team Booking Reservation', 'knit-pay-lang' );
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
		return admin_url( '/admin.php?page=team-booking' );
	}

}
