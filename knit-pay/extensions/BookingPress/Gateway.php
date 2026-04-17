<?php

namespace KnitPay\Extensions\BookingPress;

use Pronamic\WordPress\Money\Currency;
use Pronamic\WordPress\Money\Money;
use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Payments\Payment;

/**
 * Title: BookingPress Gateway
 * Description:
 * Copyright: 2020-2026 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   8.90.0.0
 */

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit();

class Gateway {

	protected $config_id;
	protected $payment_description;

	/**
	 * @var string
	 */
	public $id = 'knit_pay';

	/**
	 * Constructor
	 */
	public function __construct() {

		// Add filters for both regular appointments and package bookings
		add_filter( 'bookingpress_knit_pay_submit_form_data', [ $this, 'bookingpress_knit_pay_submit_form_data_func' ], 10, 2 );
		add_filter( 'bookingpress_package_order_knit_pay_submit_form_data', [ $this, 'bookingpress_knit_pay_submit_form_data_func' ], 10, 2 );

		// Redirect to Knit Pay from BookingPress Free.
		add_filter( 'bookingpress_validate_submitted_form', [ $this, 'bookingpress_redirect_to_knit_pay' ], 10000, 2 );
	}

	function bookingpress_redirect_to_knit_pay( $bookingpress_return_data, $posted_data ) {
		if ( 'knit_pay' !== $posted_data['selected_payment_method'] ) {
			return $bookingpress_return_data;
		}

		$bookingpress_return_data['currency_code'] = $bookingpress_return_data['currency'];

		$response = $this->bookingpress_knit_pay_submit_form_data_func( [], $bookingpress_return_data );
		wp_send_json( $response );
	}

	/**
	 * Handle payment submission for both appointments and packages
	 */
	public function bookingpress_knit_pay_submit_form_data_func( $response, $bookingpress_return_data ) {
		global $BookingPress, $bookingpress_debug_payment_log_id;

		if ( empty( $bookingpress_return_data ) ) {
			return $this->get_error_response( $response, __( 'Invalid payment data', 'knit-pay-lang' ) );
		}

		// Get basic payment details
		$entry_id         = $bookingpress_return_data['entry_id'];
		$currency_code    = strtoupper( $bookingpress_return_data['currency_code'] );
		$payable_amount   = isset( $bookingpress_return_data['payable_amount'] ) ? $bookingpress_return_data['payable_amount'] : 0;
		$customer_details = $bookingpress_return_data['customer_details'];

		do_action( 'bookingpress_payment_log_entry', 'knit_pay', 'Knit Pay submitted form data', 'bookingpress pro', $bookingpress_return_data, $bookingpress_debug_payment_log_id );

		$config_id      = $BookingPress->bookingpress_get_settings( 'knit_pay_config_id', 'payment_setting' );
		$payment_method = $this->id;
	
		// Use default gateway if no configuration has been set.
		if ( empty( $config_id ) ) {
			$config_id = get_option( 'pronamic_pay_config_id' );
		}
	
		$gateway = Plugin::get_gateway( $config_id );
	
		if ( ! $gateway ) {
			return false;
		}
			
		// Create payment
		$payment = new Payment();
			
		$payment->source    = 'bookingpress';
		$payment->source_id = $entry_id;
		$payment->order_id  = $entry_id;
			
		// Set description
		$payment->set_description( Helper::get_description( $bookingpress_return_data ) );
			
		// Set title
		$payment->title = Helper::get_title( $entry_id );

		// Customer.
		$payment->set_customer( Helper::get_customer_from_customer_details( $customer_details ) );
			
		// Address.
		$payment->set_billing_address( Helper::get_address_from_customer_details( $customer_details ) );
			
		// Currency.
		$currency = Currency::get_instance( $currency_code );
	
		// Set amount
		$payment->set_total_amount( new Money( $payable_amount, $currency ) );
			
		// Set payment method
		$payment->set_payment_method( $payment_method );
			
		// Set configuration
		$payment->config_id = $config_id;

		try {
			// Start payment
			$payment = Plugin::start_payment( $payment );

						// Store URLs as payment meta data
			$payment->set_meta( 'approved_appointment_url', $bookingpress_return_data['approved_appointment_url'] );
			$payment->set_meta( 'canceled_appointment_url', $bookingpress_return_data['canceled_appointment_url'] );
			$payment->set_meta( 'pending_appointment_url', $bookingpress_return_data['pending_appointment_url'] );

			$payment->save();
				

			$response['variant']       = 'redirect_url';
			$response['title']         = '';
			$response['msg']           = '';
			$response['is_redirect']   = 1;
			$response['redirect_data'] = $payment->get_pay_redirect_url();
			$response['entry_id']      = $entry_id;

		} catch ( \Exception $e ) {
			$response = $this->get_error_response( $response, $e->getMessage() );
		}
		return $response;
	}

	private function get_error_response( $response, $message ) {
		$response['variant']       = 'error';
		$response['title']         = esc_html__( 'Error', 'knit-pay-lang' );
		$response['msg']           = $message;
		$response['is_redirect']   = 0;
		$response['redirect_data'] = '';
		$response['is_spam']       = 0;
		return $response;
	}
}
