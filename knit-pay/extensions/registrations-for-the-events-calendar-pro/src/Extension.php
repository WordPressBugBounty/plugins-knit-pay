<?php

namespace KnitPay\Extensions\RegistrationsForTheEventsCalendarPro;

use Pronamic\WordPress\Pay\AbstractPluginIntegration;
use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;
use Pronamic\WordPress\Pay\Payments\Payment;

/**
 * Title: Registrations For The Events Calendar Pro extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   4.2.0
 */
class Extension extends AbstractPluginIntegration {
	/**
	 * Slug
	 *
	 * @var string
	 */
	const SLUG = 'rtec-pro';

	/**
	 * Constructs and initialize Registrations For The Events Calendar Pro extension.
	 */
	public function __construct() {
		parent::__construct(
			[
				'name' => __( 'Registrations for The Events Calendar', 'knit-pay-lang' ),
			]
		);

		// Dependencies.
		$dependencies = $this->get_dependencies();

		$dependencies->add( new Dependency() );
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

		$gateway = new Gateway();

		$rtec = RTEC();
		$rtec->set_gateway( $gateway );

		$payment = $rtec->payment->instance();

		add_action( 'rtec_payment_cancel_after_post', [ $payment, 'payment_process_cancellation' ] );
		add_action( 'rtec_payment_success_before_post', [ $payment, 'payment_success_add_notice' ] );
		add_action( 'rtec_payment_cancel_before_post', [ $payment, 'payment_cancel_add_notice' ] );
		add_action( 'rtec_payment_test_before_post', [ $payment, 'payment_test_add_notice' ] );

		add_action( 'rtec_payment_listeners', [ $this, 'rtec_payment_listeners' ] );
		add_filter( 'rtec_currency_code', [ $this, 'rtec_currency_code' ] );
		add_filter( 'rtec_after_payment_table', [ $this, 'knit_pay_payment_button' ] );
		add_filter( 'rtec_the_submission_status', [ $this, 'rtec_check_if_doing_payments' ], 11, 3 );
	}

	/**
	 * include and initiate payment class
	 */
	public function rtec_check_if_doing_payments( $status, $submission, $user_obj ) {

		if ( $status === 'filled' || $status === 'form' ) {
			return $status;
		}

		if ( rtec_user_needs_to_pay( $submission, $user_obj ) &&
			$user_obj->get_user_event_status() !== 'waiting' &&
			$submission->event_meta['accepting_payments'] ) {
				$status = 'payment';
		}

		return $status;
	}

	public function knit_pay_payment_button( Gateway $gateway ) {
		$checkout_options['button_text'] = 'Pay Online';// TODO make it changable.
		?>
		<form id="rtec-payment-form" action="<?php echo $gateway->get_form_action_url(); ?>" method="post">
			<?php $gateway->knit_pay_form_inputs( $checkout_options ); ?>
		</form>
		<?php
	}

	public function rtec_currency_code( $currency_code ) {
		return 'INR';
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
		self::status_update( $payment );

		$gateway  = new Gateway();
		$invoice  = (int) $payment->get_source_id();
		$entry_id = $gateway->get_entry_id_by_invoice( $invoice );

		$payment_data = $gateway->get_payment_data_by_entry( $entry_id );
		$item_number  = $payment_data['item_number'];

		$permalink = get_the_permalink( $item_number );

		switch ( $payment->get_status() ) {
			case Core_Statuses::CANCELLED:
			case Core_Statuses::EXPIRED:
			case Core_Statuses::FAILURE:
				$cancel_return = $permalink ? $permalink : trailingslashit( home_url() ) . 'cancel/';
				$cancel_return = add_query_arg(
					[
						'action'         => 'payment_cancel',
						'gateway'        => $payment->get_payment_method(),
						'transaction_id' => $invoice,
						'event'          => $item_number,
					],
					$cancel_return
				);
				return $cancel_return;

				break;

			case Core_Statuses::SUCCESS:
				// Get the success url
				$return_url = $permalink ? add_query_arg( 'action', 'payment_success', $permalink ) : trailingslashit( home_url() ) . 'success/';
				return $return_url;
				break;

			case Core_Statuses::AUTHORIZED:
			case Core_Statuses::OPEN:
			default:
				return $permalink;
		}

		return $url;
	}

	/**
	 * Update the status of the specified payment
	 *
	 * @param Payment $payment Payment.
	 */
	public static function status_update( Payment $payment ) {
		$gateway  = new Gateway();
		$invoice  = (int) $payment->get_source_id();
		$entry_id = $gateway->get_entry_id_by_invoice( $invoice );

		$payment_data                 = $gateway->get_payment_data_by_entry( $entry_id );
		$payment_data['payment_id']   = $payment->get_transaction_id();
		$payment_data['gateway']      = 'other';
		$payment_data['payment_date'] = date( 'Y-m-d H:i:s' );

		$rtec = RTEC();

		switch ( $payment->get_status() ) {
			case Core_Statuses::CANCELLED:
			case Core_Statuses::EXPIRED:
			case Core_Statuses::FAILURE:
				$payment_data['payment_status'] = 'canceled';
				$updated                        = $gateway->update_completed_payment_in_db( $payment_data );
				$rtec->db_frontend->update_entry_status( $entry_id, 'unregistered' );

				break;
			case Core_Statuses::SUCCESS:
				$payment_data['payment_status'] = 'complete';
				$updated                        = $gateway->update_completed_payment_in_db( $payment_data );
				$rtec->db_frontend->update_entry_status( $entry_id, 'confirmed' );

				break;
			case Core_Statuses::OPEN:
			default:
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
		$text = __( 'Registrations for The Events Calendar', 'knit-pay-lang' ) . '<br />';

		$text .= sprintf(
			'<a href="%s">%s</a>',
			get_edit_post_link( $payment->source_id ),
			/* translators: %s: source id */
			sprintf( __( 'Invoice %s', 'knit-pay-lang' ), $payment->source_id )
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
		return __( 'Registrations for The Events Calendar', 'knit-pay-lang' );
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

	public static function rtec_payment_listeners( $payment ) {
		if ( ! isset( $_GET['rtec-listener'] ) || $_GET['rtec-listener'] !== 'knit_pay_payment' ) {
			return;
		}

		// Only send to Knit Pay if the pending payment is created successfully
		$settings = array_map( 'sanitize_text_field', $_POST );

		$args = rtec_get_payment_data( (int) $settings['item_number'], (int) $settings['entry_id'], false, true );
		$payment->build_payment( $args );

		$payment->pp_redirect( $settings );
	}

}
