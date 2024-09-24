<?php

namespace KnitPay\Extensions\RegistrationsForTheEventsCalendarPro;

use Pronamic\WordPress\Money\Currency;
use Pronamic\WordPress\Money\Money;
use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Payments\Payment;
use RTEC_Payment;
use Tribe__Notices;

/**
 * Title: Registrations For The Events Calendar Pro Gateway
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   4.2.0
 */

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit();

class Gateway extends RTEC_Payment {

	/**
	 * @var RTEC_Payment
	 * @since 1.0
	 */
	protected static $instance;

	/**
	 * Get the one true instance of RTEC_Payment.
	 *
	 * @since  2.0
	 * @return object $instance
	 */
	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new Gateway();
		}
		return self::$instance;
	}

	public function pp_redirect( $settings = [] ) {
		$item_number = isset( $settings['item_number'] ) ? (int) $settings['item_number'] : '';
		$entry_id    = isset( $settings['entry_id'] ) ? $settings['entry_id'] : 0;

		$event_title = htmlentities( get_the_title( $item_number ) );
		$event_name  = $event_title ? apply_filters( 'rtec_event_item_name', $event_title ) : 'Event Registration';

		$rtec       = RTEC();
		$args       = [
			'fields' => [ 'event_id', 'venue', 'confirmation_code', 'action_key', 'email', 'first', 'last', 'phone' ],
			'where'  => [
				[ 'id', $entry_id, '=', 'int' ],
			],
		];
		$entry_data = $rtec->db_frontend->retrieve_entries( $args, false, 1 );
		if ( ! is_array( $entry_data ) ) {
			return;
		}
		$entry_data = reset( $entry_data );

		$entry_data['entry_data_cache'] = maybe_unserialize( $entry_data['entry_data_cache'] );

		$amount        = $this->get_total_cost();
		$currency_code = rtec_get_currency_code( $item_number, $entry_id );

		// Setup Payment arguments
		$payment_args = [
			// 'business'      => '',
			'item_number'   => $item_number,
			'amount'        => $amount,
			'currency_code' => $currency_code,
		];
		if ( isset( $settings['invoice'] ) && $settings['invoice'] !== false ) {
			$invoice = $settings['invoice'];
		} else {
			$invoice = $this->insert_pending_payment_in_db( $payment_args, $entry_id, 'other' );
		}

		$config_id      = get_option( 'pronamic_pay_config_id' );// TODO
		$payment_method = 'knit_pay';// TODO

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

		$payment->source    = 'rtec-pro';
		$payment->source_id = $invoice;
		$payment->order_id  = $invoice;

		$payment->set_description( Helper::get_description( $event_name, $entry_id ) );

		$payment->title = Helper::get_title( $event_name );

		// Customer.
		$payment->set_customer( Helper::get_customer( $entry_data ) );

		// Address.
		$payment->set_billing_address( Helper::get_address( $entry_data ) );

		// Currency.
		$currency = Currency::get_instance( $currency_code );

		// Amount.
		$payment->set_total_amount( new Money( $amount, $currency ) );

		// Method.
		$payment->set_payment_method( $payment_method );

		// Configuration.
		$payment->config_id = $config_id;

		try {
			$payment = Plugin::start_payment( $payment );

			$gateway->redirect( $payment );
			exit;
		} catch ( \Exception $e ) {
			$permalink = get_the_permalink( $item_number );
			echo $e->getMessage();
			echo '<br><br><a href="' . $permalink . '">Go Back</a>';
			exit;
		}
	}

	public function payment_success_add_notice() {
		global $rtec_options;

		if ( ! isset( $_GET['action'] ) ) {
			return;
		}

		$message   = isset( $rtec_options['payment_success_message'] ) ? $rtec_options['payment_success_message'] : __( 'You have completed your payment. Check your inbox for a receipt.', 'registrations-for-the-events-calendar' );
		$o_message = rtec_get_text( $message, __( 'You have completed your payment. Check your inbox for a receipt.', 'registrations-for-the-events-calendar' ) );

		if ( method_exists( 'Tribe__Notices', 'set_notice' ) ) {
			Tribe__Notices::set_notice( 'payment_status', $o_message );
		}

	}

	public function get_form_action_url() {
		if ( ! $this->is_free() ) {
			$form_action = add_query_arg( 'rtec-listener', 'knit_pay_payment', home_url( 'index.php' ) );
			return apply_filters( 'rtec_knit_pay_form_action', $form_action );
		} else {
			return add_query_arg(
				[
					'action' => 'payment_success',
					'free'   => 'true',
				],
				get_the_permalink( $this->payment_data['transaction']['item_number'] )
			);
		}
	}

	public function knit_pay_form_inputs( $checkout_options ) {
		$checkout_options['button_text'] = 'Pay Online';// TODO make it changable.

		$quantity = isset( $this->payment_data['transaction']['quantity'] ) ? $this->payment_data['transaction']['quantity'] : 1;
		?>
		<input type="hidden" name="item_number" value="<?php echo esc_attr( $this->payment_data['transaction']['item_number'] ); ?>">
		<input type="hidden" name="quantity" value="<?php echo esc_attr( $quantity ); ?>">
		<input type="hidden" name="entry_id" value="<?php echo esc_attr( $this->payment_data['transaction']['entry_id'] ); ?>">
		<?php if ( ! $this->is_free() ) : ?>
			<button type="submit" name="knit_pay_submit" value="<?php esc_attr_e( $checkout_options['button_text'] ); ?>" class="rtec-payment-button"><?php echo esc_html( $checkout_options['button_text'] ); ?></button>
		<?php endif; ?>
		<?php
	}
}
RTEC_Payment::instance();
