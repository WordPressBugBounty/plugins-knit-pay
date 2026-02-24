<?php

namespace KnitPay\Extensions\BaBookEverything;

use BABE_Order;
use BABE_Payments;
use BABE_Settings;
use Pronamic\WordPress\Pay\AbstractPluginIntegration;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: BA Book Everything extension
 * Description:
 * Copyright: 2020-2023 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   9.1.0.0
 */
class Extension extends AbstractPluginIntegration {
	/**
	 * Slug
	 *
	 * @var string
	 */
	const SLUG = 'ba-book-everything';

	/**
	 * Constructs and initialize BA Book Everything extension.
	 */
	public function __construct() {
		parent::__construct(
			[
				'name' => __( 'BA Book Everything', 'knit-pay-lang' ),
			]
		);

		// Dependencies.
		$dependencies = $this->get_dependencies();

		$dependencies->add( new BaBookEverythingDependency() );
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
		
		add_action( 'babe_init_payment_methods', [ __CLASS__, 'init_payment_method' ] );
		add_action( 'init', [ __CLASS__, 'init_session' ], 1 );
		add_action( 'init', [ __CLASS__, 'update_confirmation_messages' ], 20 );
	}

	/**
	 * Initialize PHP session if not already started
	 *
	 * @return void
	 */
	public static function init_session() {
		if ( session_status() === PHP_SESSION_NONE ) {
			session_start();
		}
	}

	public static function update_confirmation_messages() {
		if ( ! isset( $_GET['current_action'] ) || 'to_confirm' !== $_GET['current_action'] ) {
			return;
		}

		if ( ! isset( $_SESSION['knit_pay_babe_error'] ) || empty( $_SESSION['knit_pay_babe_error'] ) ) {
			return;
		}

		$error = sanitize_text_field( $_SESSION['knit_pay_babe_error'] );
		unset( $_SESSION['knit_pay_babe_error'] );

		BABE_Settings::$settings['message_draft']            = "<div class='babe_message_order babe_message_order_reject'>{$error}</div>";
		BABE_Settings::$settings['message_payment_expected'] = $error;
	}

	/**
	 * Init payment method
	 *
	 * @param array $payment_methods
	 * @return void
	 */
	public static function init_payment_method( $payment_methods ) {
		if ( isset( $payment_methods['knit_pay'] ) ) {
			return;
		}

		BABE_Payments::add_payment_method( 'knit_pay', __( 'Knit Pay - Default', 'knit-pay-lang' ) );
		new Gateway( 'knit_pay', 'knit_pay' );
		/*
		foreach ( PaymentMethods::get_active_payment_methods() as $payment_method ) {
			BABE_Payments::add_payment_method( 'knit_pay_' . $payment_method, 'Knit Pay - ' . PaymentMethods::get_name( $payment_method, ucwords( $payment_method ) ) );
		}*/
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
		$order_id = (int) $payment->get_order_id();

		return BABE_Order::get_order_confirmation_page( $order_id );
	}

	/**
	 * Update the status of the specified payment
	 *
	 * @param Payment $payment Payment.
	 */
	public static function status_update( Payment $payment ) {
		$order_id   = (int) $payment->get_order_id();
		$gateway_id = 'knit_pay' === $payment->get_payment_method() ?? 'knit_pay_' . $payment->get_payment_method();

		$amount         = $payment->get_total_amount()->number_format( null, '.', '' );
		$currency       = $payment->get_total_amount()->get_currency()->get_alphabetic_code();
		$transaction_id = $payment->get_transaction_id();

		$amount_already_received = BABE_Order::get_order_prepaid_received( $order_id );
		$amount_total            = BABE_Order::get_order_total_amount( $order_id );

		switch ( $payment->get_status() ) {
			case Core_Statuses::SUCCESS:
				// Don't do anything if the payment has already been pushed to BABE.
				if ( $payment->get_meta( 'pushed_to_babe' ) ) {
					return;
				}

				BABE_Payments::do_complete_order( $order_id, $gateway_id, $transaction_id, $amount, $currency );

				// Setting amout yet to be received, after this payment.
				$amount_pending = round( $amount_total - $amount_already_received - $amount, 2 );
				BABE_Order::update_order_prepaid_amount( $order_id, $amount_pending );

				$payment->set_meta( 'pushed_to_babe', true );
				$payment->save();

				break;

			case Core_Statuses::CANCELLED:
				$_SESSION['knit_pay_babe_error'] = 'Payment Cancelled.';
				self::update_babe_pending_order_status( $order_id, $amount_already_received );

				break;
			case Core_Statuses::EXPIRED:
			case Core_Statuses::FAILURE:
				$_SESSION['knit_pay_babe_error'] = 'Payment Failed.';
				self::update_babe_pending_order_status( $order_id, $amount_already_received );

				break;
			case Core_Statuses::OPEN:
			default:
				self::update_babe_pending_order_status( $order_id, $amount_already_received );

				break;
		}
	}

	private static function update_babe_pending_order_status( $order_id, $amount_already_received ) {
		/*
			Obeservation by Knit Pay. These might be wrong.

			The 'payment_processing' status does not allow future payment attempts. Don't use it.
			'canceled' is for admin to reject the order, it does not allow future payment attempts. Don't use it.
			'draft' keeps order and payment in pending state. We should use it only if $amount_already_received is zero. Not sure about this, draft orders might get deleted automatically after some time.
			'payment_expected' order is completed but payment is not received yet, we should use it if $amount_already_received is not zero.
		*/
		if ( $amount_already_received > 0 ) {
			BABE_Order::update_order_status( $order_id, 'payment_expected' );
		} else {
			BABE_Order::update_order_status( $order_id, 'draft' );
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
		$text = __( 'BA Book Everything', 'knit-pay-lang' ) . '<br />';

		$text .= sprintf(
			'<a href="%s">%s</a>',
			get_edit_post_link( $payment->source_id ),
			/* translators: %s: source id */
			sprintf( __( 'Order %s', 'knit-pay-lang' ), $payment->source_id )
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
		return __( 'BA Book Everything Order', 'knit-pay-lang' );
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
