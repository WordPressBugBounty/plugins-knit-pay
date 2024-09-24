<?php

namespace KnitPay\Extensions\EventsManagerPro;

use Pronamic\WordPress\Money\Currency;
use Pronamic\WordPress\Money\Money;
use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Payments\Payment;
use EM_Booking;
use EM_Event;
use EM_Gateway;
use EM_Gateway_Paypal;
use EM_Multiple_Bookings;

/**
 * Title: Events Manager Pro Gateway
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   3.2.0
 */

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit();

class Gateway extends EM_Gateway {

	var $gateway                    = 'knit_pay';
	var $title                      = 'Knit Pay';
	var $status                     = 4;
	var $status_txt                 = 'Awaiting Online Payment';
	var $button_enabled             = true;
	var $supports_multiple_bookings = true;
	var $payment_return             = false;
	var $count_pending_spaces       = true;

	/**
	 * Bootstrap
	 *
	 * @param array $args Gateway properties.
	 */
	public function __construct() {
		parent::__construct();
		add_filter( 'em_my_bookings_booking_actions', [ &$this, 'em_my_bookings_booking_actions' ], 1, 2 );

		if ( $this->is_active() ) {
			add_action( 'em_booking_js', [ &$this, 'em_booking_js' ] );
		}
	}

	/**
	 * Outputs some JavaScript during the em_booking_js action, which is run inside a script html tag.
	 */
	function em_booking_js() {
		include dirname( __FILE__ ) . '/assets/js/gateway.knit_pay.js';
	}

	/*
	 * --------------------------------------------------
	 * Booking UI - modifications to booking pages and tables containing knit pay bookings
	 * --------------------------------------------------
	 */

	/**
	 * Instead of a simple status string, a resume payment button is added to the status message so user can resume booking from their my-bookings page.
	 *
	 * @param string     $message
	 * @param EM_Booking $EM_Booking
	 * @return string
	 */
	function em_my_bookings_booking_actions( $message, $EM_Booking ) {
		global $wpdb;
		// if in multiple booking mode, switch the booking for the main booking and treat that as our booking
		if ( get_option( 'dbem_multiple_bookings' ) ) {
			$EM_Multiple_Booking = EM_Multiple_Bookings::get_main_booking( $EM_Booking );
			if ( $EM_Multiple_Booking !== false ) {
				$EM_Booking = $EM_Multiple_Booking;
			}
		}
		if ( $this->uses_gateway( $EM_Booking ) && $EM_Booking->booking_status == $this->status ) {
			if ( empty( $EM_Booking->booking_meta['knitpay_payment_url'] ) ) {
				$this->booking_form_feedback( [ 'result' => true ], $EM_Booking );
			}

			$message .= $this->get_pay_button( $EM_Booking->booking_meta['knitpay_payment_url'] );
		}
		return $message;
	}

	/**
	 * Triggered by the em_booking_add_yourgateway action, hooked in EM_Gateway. Overrides EM_Gateway to account for non-ajax bookings (i.e. broken JS on site).
	 *
	 * @param EM_Event   $EM_Event
	 * @param EM_Booking $EM_Booking
	 * @param boolean    $post_validation
	 */
	function booking_add( $EM_Event, $EM_Booking, $post_validation = false ) {
		parent::booking_add( $EM_Event, $EM_Booking, $post_validation );
		if ( ! defined( 'DOING_AJAX' ) ) { // we aren't doing ajax here, so we should provide a way to edit the $EM_Notices ojbect.
			add_action( 'option_dbem_booking_feedback', [ &$this, 'booking_form_feedback_fallback' ] );
		}
	}

	/**
	 * Intercepts return data after a booking has been made and modifies feedback message.
	 *
	 * @param array      $return
	 * @param EM_Booking $EM_Booking
	 * @return array
	 *
	 * @see EM_Gateway_Paypal::booking_form_feedback
	 */
	function booking_form_feedback( $return = [], $EM_Booking = false ) {
		// Double check $EM_Booking is an EM_Booking object and that we have a booking awaiting payment.
		if ( is_object( $EM_Booking ) && $this->uses_gateway( $EM_Booking ) ) {
			if ( ! empty( $return['result'] ) && $EM_Booking->get_price() > 0 && $EM_Booking->booking_status == $this->status ) {
				$return['message'] = get_option( 'em_' . $this->gateway . '_booking_feedback' );
				$return            = $this->start( $EM_Booking, $return );
			} else {
				// returning a free message
				$return['message'] = get_option( 'em_' . $this->gateway . '_booking_feedback_free' );
			}
		}
		return $return;
	}

	private function start( EM_Booking $EM_Booking, $return ) {
		if ( ! empty( $EM_Booking->booking_meta['knitpay_payment_url'] ) ) {
			$return['redirect_url'] = $EM_Booking->booking_meta['knitpay_payment_url'];
			return $return;
		}

		$config_id      = get_option( 'em_' . $this->gateway . '_config_id' );
		$payment_method = $this->gateway;

		// Use default gateway if no configuration has been set.
		if ( empty( $config_id ) ) {
			$config_id = get_option( 'pronamic_pay_config_id' );
		}

		$gateway = Plugin::get_gateway( $config_id );

		if ( ! $gateway ) {
			return false;
		}

		/**
		 * Build payment.
		 */
		$payment = new Payment();

		$payment->source    = 'events-manager-pro';
		$payment->source_id = $EM_Booking->booking_id;
		$payment->order_id  = $EM_Booking->booking_id;

		$payment->set_description( Helper::get_description( $payment_method, $EM_Booking->booking_id ) );

		$payment->title = Helper::get_title( $EM_Booking->booking_id );

		// Customer.
		$payment->set_customer( Helper::get_customer( $EM_Booking->get_person() ) );

		// Address.
		$payment->set_billing_address( Helper::get_address( $EM_Booking ) );

		// Currency.
		$currency = Currency::get_instance( \get_option( 'dbem_bookings_currency', 'INR' ) );

		// Amount.
		$payment->set_total_amount( new Money( $EM_Booking->get_price(), $currency ) );

		// Method.
		$payment->set_payment_method( $payment_method );

		// Configuration.
		$payment->config_id = $config_id;

		try {
			$payment = Plugin::start_payment( $payment );

			$EM_Booking->booking_meta['knitpay_payment_url'] = $payment->get_pay_redirect_url();
			$EM_Booking->save( false );

			$return['redirect_url'] = $payment->get_pay_redirect_url();
		} catch ( \Exception $e ) {
			$return['result']  = false;
			$return['message'] = $e->getMessage();
			$return['error']   = '<p>' . $e->getMessage() . '</p>';
		}
		return $return;
	}

	/**
	 * Called if AJAX isn't being used, i.e. a javascript script failed and forms are being reloaded instead.
	 *
	 * @param string $feedback
	 * @return string
	 */
	function booking_form_feedback_fallback( $feedback ) {
		global $EM_Booking;
		if ( ! is_object( $EM_Booking ) ) {
			return $feedback;
		}

		$return = $this->booking_form_feedback( [ 'result' => true ], $EM_Booking );

		if ( $return['result'] ) {
			$feedback .= '<br />' . __( 'To finalize your booking, please click the following button to make the payment.', 'knit-pay-lang' ) . $this->get_pay_button( $return['redirect_url'] );

			return $feedback;
		}
		$feedback = $return['error'];

		return $feedback;
	}

	/**
	 * Outputs custom Knit Pay setting fields in the settings page
	 */
	function mysettings() {
		global $EM_options;
		?>
		<table class="form-table">
		<tbody>
		  <?php em_options_input_text( esc_html__( 'Success Message', 'knit-pay-lang' ), 'em_' . $this->gateway . '_booking_feedback', esc_html__( 'The message that is shown to a user when a booking is successful whilst being redirected for payment.', 'knit-pay-lang' ), __( 'Please wait whilst you are redirected to proceed with payment.', 'knit-pay-lang' ) ); ?>
		  <?php em_options_input_text( esc_html__( 'Success Free Message', 'knit-pay-lang' ), 'em_' . $this->gateway . '_booking_feedback_free', esc_html__( 'If some cases if you allow a free ticket (e.g. pay at gate) as well as paid tickets, this message will be shown and the user will not be redirected.', 'knit-pay-lang' ), __( 'Booking successful.', 'events-manager' ) ); ?>
		  <?php em_options_input_text( esc_html__( 'Thank You Message', 'knit-pay-lang' ), 'em_' . $this->gateway . '_booking_feedback_completed', esc_html__( 'If you choose to return users to the default Events Manager thank you page after a user has paid, you can customize the thank you message here.', 'knit-pay-lang' ), __( 'Thank you for your payment. Your transaction has been completed, and a receipt for your purchase has been emailed to you along with a separate email containing account details to access your booking information on this site.', 'knit-pay-lang' ) ); ?>
		  <?php em_options_select( esc_html__( 'Configuration', 'knit-pay-lang' ), 'em_' . $this->gateway . '_config_id', Plugin::get_config_select_options( $this->gateway ) ); // TODO default value ?>
		  <?php em_options_input_text( esc_html__( 'Payment Description', 'knit-pay-lang' ), 'em_' . $this->gateway . '_payment_description', sprintf( __( 'Available tags: %s', 'knit-pay-lang' ), sprintf( '<code>%s</code>', '{booking_id}' ) ), __( 'Events Manager Pro {booking_id}', 'knit-pay-lang' ) ); ?>
		</tbody>
		</table>
		<?php
	}

	/*
	 * Run when saving Knit Pay settings, saves the settings available in mysettings()
	 */
	function update() {
		$gateway_options[] = 'em_' . $this->gateway . '_payment_description';
		$gateway_options[] = 'em_' . $this->gateway . '_config_id';
		$gateway_options[] = 'em_' . $this->gateway . '_booking_feedback';
		$gateway_options[] = 'em_' . $this->gateway . '_booking_feedback_free';
		$gateway_options[] = 'em_' . $this->gateway . '_booking_feedback_completed';
		foreach ( $gateway_options as $option_wpkses ) {
			add_filter( 'gateway_update_' . $option_wpkses, 'wp_kses_post' );
		}
		return parent::update( $gateway_options );
	}

	function activate() {
		if ( ! get_option( 'em_' . $this->gateway . '_option_name' ) ) {
			add_option( 'em_' . $this->gateway . '_option_name', $this->title );
		}
		if ( ! get_option( 'em_' . $this->gateway . '_button' ) ) {
			add_option( 'em_' . $this->gateway . '_button', $this->title );
		}
			return parent::activate();
	}

	private function get_pay_button( $redirect_url ) {
		$form  = '<form action="' . $redirect_url . '" method="post">';
		$form .= '<input type="submit" value="' . __( 'Resume Payment', 'knit-pay-lang' ) . '">';
		$form .= '</form>';
		return $form;
	}

}
