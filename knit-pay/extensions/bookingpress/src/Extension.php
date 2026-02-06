<?php

namespace KnitPay\Extensions\Bookingpress;

use Pronamic\WordPress\Pay\AbstractPluginIntegration;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;
use Pronamic\WordPress\Pay\Plugin;
use bookingpress_payment_gateways;


/**
 * Title: BookingPress extension
 * Description:
 * Copyright: 2020-2026 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   8.90.0.0
 */
class Extension extends AbstractPluginIntegration {
	/**
	 * Slug
	 *
	 * @var string
	 */
	const SLUG = 'bookingpress';

	/**
	 * Constructs and initialize BookingPress extension.
	 */
	public function __construct() {
		parent::__construct(
			[
				'name' => __( 'BookingPress', 'knit-pay-lang' ),
			]
		);

		// Dependencies.
		$dependencies = $this->get_dependencies();

		$dependencies->add( new BookingpressDependency() );
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

		new Gateway();

		add_action( 'bookingpress_gateway_listing_field', [ $this, 'gateway_listing' ] );
		add_action( 'bpa_front_add_payment_gateway', [ $this, 'bpa_front_add_payment_gateway' ] );
		add_filter( 'bookingpress_add_setting_dynamic_data_fields', [ $this, 'setting_dynamic_data_fields' ] );
		add_filter( 'bookingpress_frontend_apointment_form_add_dynamic_data', [ $this, 'bookingpress_frontend_apointment_form_add_dynamic_data' ], 10 );
		
		// Filter for add payment gateway to revenue filter list
		add_filter( 'bookingpress_revenue_filter_payment_gateway_list_add', [ $this, 'bookingpress_revenue_filter_payment_gateway_list_add' ] );
	}

	function bookingpress_revenue_filter_payment_gateway_list_add( $bookingpress_revenue_filter_payment_gateway_list ) {
		$bookingpress_revenue_filter_payment_gateway_list[] = [
			'value' => 'knit_pay',
			'text'  => 'Knit Pay',
		];

		return $bookingpress_revenue_filter_payment_gateway_list;
	}

	/**
	 * Function for package booking
	 *
	 * @param  mixed $bookingpress_front_vue_data_fields
	 * @return void
	 */
	function bookingpress_frontend_apointment_form_add_dynamic_data( $bookingpress_front_vue_data_fields ) {
		$bookingpress_front_vue_data_fields['knit_pay_payment'] = false;

		global $BookingPress;
		$bookingpress_is_gateway_enable = $BookingPress->bookingpress_get_settings( 'knit_pay_payment', 'payment_setting' );
		if ( $bookingpress_is_gateway_enable == 'true' ) {
			$bookingpress_front_vue_data_fields['is_only_onsite_enabled']                                = 0;
			$bookingpress_front_vue_data_fields['bookingpress_activate_payment_gateway_counter']         = $bookingpress_front_vue_data_fields['bookingpress_activate_payment_gateway_counter'] + 1;
			$bookingpress_front_vue_data_fields['appointment_step_form_data']['selected_payment_method'] = 'knit_pay';
			$bookingpress_front_vue_data_fields['knit_pay_payment']                                      = true;

			if ( $this->is_free_and_gateway_disabled() ) {
				$bookingpress_front_vue_data_fields['paypal_payment'] = true;
			}
		}

		$bookingpress_front_vue_data_fields['knit_pay_text'] = $BookingPress->bookingpress_get_customize_settings( 'knit_pay_text', 'booking_form' );
		return $bookingpress_front_vue_data_fields;
	}

	private function is_free_and_gateway_disabled() {
		global $BookingPress;
		$on_site_payment = $BookingPress->bookingpress_get_settings( 'on_site_payment', 'payment_setting' );
		$paypal_payment  = $BookingPress->bookingpress_get_settings( 'paypal_payment', 'payment_setting' );

		return ! defined( 'BOOKINGPRESS_DIR_PRO_NAME_PRO' ) && 'true' != $on_site_payment && 'true' != $paypal_payment;
	}
	
	public function bpa_front_add_payment_gateway() {
		if ( $this->is_free_and_gateway_disabled() ) {
			?>
			<script>jQuery('.bpa-front-module--pm-body__item[aria-label="PayPal"]').remove();</script>
			<?php
		}
		?>
		<div class="bpa-front--pm-body-items">
		<div style="width: 190px;" class="bpa-front-module--pm-body__item" :class="(appointment_step_form_data.selected_payment_method == 'knit_pay') ? '__bpa-is-selected' : ''" @click="select_payment_method('knit_pay')" v-if="knit_pay_payment != 'false' && knit_pay_payment != ''">
			<svg class="bpa-front-pm-pay-local-icon" xmlns="http://www.w3.org/2000/svg" enable-background="new 0 0 24 24" viewBox="0 0 24 24"><g><g><rect fill="none" height="24" width="24"/><rect fill="none" height="24" width="24"/></g></g><g><path d="M21.9,7.89l-1.05-3.37c-0.22-0.9-1-1.52-1.91-1.52H5.05c-0.9,0-1.69,0.63-1.9,1.52L2.1,7.89C1.64,9.86,2.95,11,3,11.06V19 c0,1.1,0.9,2,2,2h14c1.1,0,2-0.9,2-2v-7.94C22.12,9.94,22.09,8.65,21.9,7.89z M13,5h1.96l0.54,3.52C15.59,9.23,15.11,10,14.22,10 C13.55,10,13,9.41,13,8.69V5z M6.44,8.86C6.36,9.51,5.84,10,5.23,10C4.3,10,3.88,9.03,4.04,8.36L5.05,5h1.97L6.44,8.86z M11,8.69 C11,9.41,10.45,10,9.71,10c-0.75,0-1.3-0.7-1.22-1.48L9.04,5H11V8.69z M18.77,10c-0.61,0-1.14-0.49-1.21-1.14L16.98,5l1.93-0.01 l1.05,3.37C20.12,9.03,19.71,10,18.77,10z"/></g></svg>
			<p><?php echo 'Online Payment'; ?></p>
			<div class="bpa-front-si-card--checkmark-icon" v-if="appointment_step_form_data.selected_payment_method == 'knit_pay'">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M0 0h24v24H0V0z" fill="none"/><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zM9.29 16.29 5.7 12.7c-.39-.39-.39-1.02 0-1.41.39-.39 1.02-.39 1.41 0L10 14.17l6.88-6.88c.39-.39 1.02-.39 1.41 0 .39.39.39 1.02 0 1.41l-7.59 7.59c-.38.39-1.02.39-1.41 0z"/></svg>
				</div>
			</div>
		</div>
		<?php 
	}
	
	public function gateway_listing() {
		require_once 'setting-form.php';
	}
	
	public function setting_dynamic_data_fields( $fields ) {
		$payment_configurations = Plugin::get_config_select_options( 'knit_pay' );
		foreach ( $payment_configurations as $key => $payment_config ) {
			$fields['knit_pay_configurations'][] = [
				'value' => $key,
				'text'  => $payment_config,
			];
		}

		return $fields;
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
		// Get stored URLs from payment meta
		$approved_url = $payment->get_meta( 'approved_appointment_url' );
		$canceled_url = $payment->get_meta( 'canceled_appointment_url' );
		$pending_url  = $payment->get_meta( 'pending_appointment_url' );
	
		switch ( $payment->get_status() ) {
			case Core_Statuses::CANCELLED:
			case Core_Statuses::EXPIRED:
			case Core_Statuses::FAILURE:
				return $canceled_url ?: $url;

			case Core_Statuses::SUCCESS:
				return $approved_url ?: $url;

			case Core_Statuses::AUTHORIZED:
			case Core_Statuses::OPEN:
				return $pending_url ?: $url;
		}
	}

	/**
	 * Update the status of the specified payment
	 *
	 * @param Payment $payment Payment.
	 */
	public static function status_update( Payment $payment ) {
		$entry_id = $payment->get_source_id();

		switch ( $payment->get_status() ) {
			case Core_Statuses::CANCELLED:
			case Core_Statuses::EXPIRED:
			case Core_Statuses::FAILURE:
				$bookingpress_payment_status = '3'; // Failed/Cancelled

				break;
			case Core_Statuses::SUCCESS:
				$bookingpress_payment_status = '1'; // Success

				break;
			case Core_Statuses::OPEN:
			default:
				return;
				$bookingpress_payment_status = '2'; // Pending

				break;
		}

		global $bookingpress_pro_payment_gateways;

		if ( isset( $bookingpress_pro_payment_gateways ) ) {
			// Process the booking with determined status
			$payment_log_id = $bookingpress_pro_payment_gateways->bookingpress_confirm_booking(
				$entry_id,
				[
					'transaction_id'      => $payment->get_transaction_id(),
					'knit_pay_payment_id' => $payment->get_id(),
					'amount'              => $payment->get_total_amount()->number_format( null, '.', '' ),
					'currency'            => $payment->get_total_amount()->get_currency()->get_alphabetic_code(),
				],
				$bookingpress_payment_status,
				'transaction_id',
				'amount',
				1,
				0,
				'currency'
			);
		} else {
			// Initialize bookingpress_pro_payment_gateways if not already set, for BookingPress Free.
			$bookingpress_pro_payment_gateways = new bookingpress_payment_gateways();
			
			$payment_log_id = $bookingpress_pro_payment_gateways->bookingpress_confirm_booking(
				$entry_id,
				[
					'transaction_id'      => $payment->get_transaction_id(),
					'knit_pay_payment_id' => $payment->get_id(),
					'mc_gross'            => $payment->get_total_amount()->number_format( null, '.', '' ),
					'mc_currency'         => $payment->get_total_amount()->get_currency()->get_alphabetic_code(),
				],
				$bookingpress_payment_status,
				'transaction_id',
				'mc_gross',
				1
			);
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
		$text = __( 'BookingPress', 'knit-pay-lang' ) . '<br />';

		$text .= sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'admin.php?page=bookingpress_appointments' ),
			/* translators: %s: source id */
			sprintf( __( 'Entry %s', 'knit-pay-lang' ), $payment->source_id )
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
		return __( 'BookingPress Entry', 'knit-pay-lang' );
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
		return admin_url( 'admin.php?page=bookingpress_appointments' );
	}
}
