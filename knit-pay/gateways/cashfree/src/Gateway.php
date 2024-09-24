<?php
namespace KnitPay\Gateways\Cashfree;

use Pronamic\WordPress\Pay\Core\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Core\PaymentMethod;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;
use Exception;

/**
 * Title: Cashfree Gateway
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 1.0.0
 * @since 2.4
 */
class Gateway extends Core_Gateway {
	private $test_mode;
	private $config;
	private $api;

	const NAME = 'cashfree';

	/**
	 * Initializes an Cashfree gateway
	 *
	 * @param Config $config
	 *            Config.
	 */
	public function init( Config $config ) {
		$this->set_method( self::METHOD_HTML_FORM );

		// Supported features.
		$this->supports = [
			'payment_status_request',
		];

		$this->test_mode = 0;
		if ( self::MODE_TEST === $config->mode ) {
			$this->test_mode = 1;
		}

		$this->config = $config;
		$this->api    = new API( $config->api_id, $config->secret_key, $this->test_mode );

		$this->register_payment_methods();
	}

	private function register_payment_methods() {
		$this->register_payment_method( new PaymentMethod( PaymentMethods::CASHFREE ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::CREDIT_CARD ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::DEBIT_CARD ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::NET_BANKING ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::UPI ) );
	}

	/**
	 * Get available payment methods.
	 *
	 * @return array<int, string>
	 * @see Core_Gateway::get_available_payment_methods()
	 */
	public function get_available_payment_methods() {
		return $this->get_supported_payment_methods();
	}

	/**
	 * Start.
	 *
	 * @see Core_Gateway::start()
	 *
	 * @param Payment $payment
	 *            Payment.
	 */
	public function start( Payment $payment ) {
		if ( ! empty( $payment->get_meta( 'cashfree_payment_session_id' ) ) ) {
			return;
		}

		if ( ! defined( 'KNIT_PAY_CASHFREE' ) ) {
			$error = sprintf(
				/* translators: 1: Cashfree */
				__( 'Knit Pay supports %1$s with a Premium Addon. But you can get this premium addon for free and also you can get a special discount on transaction fees. Visit the Knit Pay website (knitpay.org) to know more.', 'knit-pay-lang' ),
				__( 'Cashfree', 'knit-pay-lang' )
			);
			throw new Exception( $error );
		}

		$cashfree_order_id = $payment->key . '_' . $payment->get_id();
		$payment->set_transaction_id( $cashfree_order_id );

		$cashfree_payment_session_id = $this->api->create_order( $this->get_payment_data( $payment ) );

		$payment->add_note( 'Cashfree payment_session_id: ' . $cashfree_payment_session_id );
		$payment->set_meta( 'cashfree_payment_session_id', $cashfree_payment_session_id );
		
		$payment->set_action_url( $payment->get_pay_redirect_url() );
	}

	/**
	 * Get Payment Data.
	 *
	 * @param Payment $payment
	 *            Payment.
	 *
	 * @return array
	 */
	private function get_payment_data( Payment $payment ) {
		$customer        = $payment->get_customer();
		$billing_address = $payment->get_billing_address();
		$customer_phone  = $this->config->default_customer_phone;
		if ( ! empty( $billing_address ) && ! empty( $billing_address->get_phone() ) ) {
			$customer_phone = $this->format_phone_number( $billing_address->get_phone() );
		}

		$order_id       = $payment->get_transaction_id();
		$order_amount   = $payment->get_total_amount()->number_format( null, '.', '' );
		$order_currency = $payment->get_total_amount()->get_currency()->get_alphabetic_code();
		$order_note     = $payment->get_description();
		$customer_name  = substr( trim( ( html_entity_decode( $customer->get_name(), ENT_QUOTES, 'UTF-8' ) ) ), 0, 20 );
		$customer_email = $customer->get_email();
		$return_url     = add_query_arg( 'order_id', '{order_id}', $payment->get_return_url() );
		$notify_url     = add_query_arg( 'kp_cashfree_webhook', '', home_url( '/' ) );
		$cust_id        = 'CUST_' . $payment->get_order_id() . '_' . $payment->get_id();

		// @see https://docs.cashfree.com/reference/createorder
		return [
			'order_id'         => $order_id,
			'order_amount'     => $order_amount,
			'order_currency'   => $order_currency,
			'customer_details' => [
				'customer_id'    => $cust_id,
				'customer_name'  => $customer_name,
				'customer_email' => $customer_email,
				'customer_phone' => $customer_phone,
			],
			'order_meta'       => [
				'return_url'      => $return_url,
				'notify_url'      => $notify_url,
				'payment_methods' => PaymentMethods::transform( $payment->get_payment_method() ),
			],
			'order_note'       => $order_note,
		];
	}
	
	/**
	 * Output form.
	 *
	 * @param Payment $payment Payment.
	 * @return void
	 * @throws \Exception When payment action URL is empty.
	 */
	public function output_form( Payment $payment ) {
		if ( PaymentStatus::SUCCESS === $payment->get_status() || PaymentStatus::EXPIRED === $payment->get_status() ) {
			wp_safe_redirect( $payment->get_return_redirect_url() );
			exit;
		}

		$html = '';
		if ( $this->test_mode ) {
			$html .= '<script src="https://sdk.cashfree.com/js/ui/2.0.0/cashfree.sandbox.js"></script>';
		} else {
			$html .= '<script src="https://sdk.cashfree.com/js/ui/2.0.0/cashfree.prod.js"></script>';
		}

		$html .= '<script>';
		$html .= "const cashfree = new Cashfree('{$payment->get_meta('cashfree_payment_session_id')}');";

		if ( ! ( defined( '\PRONAMIC_PAY_DEBUG' ) && \PRONAMIC_PAY_DEBUG ) ) {
			$html .= 'cashfree.redirect();';
		}

		$html .= 'document.getElementById("pronamic_ideal_form").onsubmit = function(e){e.preventDefault();cashfree.redirect();}</script>';

		echo $html;
	}

	/**
	 * Update status of the specified payment.
	 *
	 * @param Payment $payment
	 *            Payment.
	 */
	public function update_status( Payment $payment ) {
		if ( PaymentStatus::SUCCESS === $payment->get_status() || PaymentStatus::EXPIRED === $payment->get_status() ) {
			return;
		}

		$order_details = $this->api->get_order_details( $payment->get_transaction_id() );
		if ( pronamic_pay_plugin()->is_debug_mode() ) {
			$payment->add_note( '<strong>Cashfree Order Details:</strong><br><pre>' . print_r( $order_details, true ) . '</pre><br>' );
		}
		
		// @see https://docs.cashfree.com/reference/getpaymentsfororder
		$order_payments = $this->api->get_order_data( $order_details->payments );
		if ( pronamic_pay_plugin()->is_debug_mode() ) {
			$payment->add_note( '<strong>Cashfree Order Payments:</strong><br><pre>' . print_r( $order_payments, true ) . '</pre><br>' );
		}
		
		if ( empty( $order_payments ) ) {
			if ( filter_has_var( INPUT_GET, 'order_id' ) ) {
				$payment->set_status( PaymentStatus::CANCELLED );
				return;
			}

			// Check Status from Order Details if Payments not available.
			$payment->set_status( Statuses::transform( $order_details->order_status ) );
			return;
		}
		
		$current_payment = reset( $order_payments );
		$payment->add_note( '<strong>Cashfree Current Payment:</strong><br><pre>' . print_r( $current_payment, true ) . '</pre><br>' );

		if ( isset( $current_payment->payment_status ) ) {
			$payment_status = $current_payment->payment_status;

			$payment->set_status( Statuses::transform( $payment_status ) );
		}
	}

	private function format_phone_number( $customer_phone ) {
		// Remove - or whitespace.
		$customer_phone = preg_replace( '/[\s\-]+/', '', $customer_phone );

		// Remove 0 from beginning of phone number.
		$customer_phone = 10 < strlen( $customer_phone ) ? ltrim( $customer_phone, '0' ) : $customer_phone;

		return $customer_phone;
	}
}
