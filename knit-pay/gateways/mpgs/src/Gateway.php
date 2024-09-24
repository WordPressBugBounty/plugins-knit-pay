<?php
namespace KnitPay\Gateways\MPGS;

use Pronamic\WordPress\Pay\Core\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Core\PaymentMethod;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;
use Pronamic\WordPress\Pay\Core\PaymentMethods;
use Exception;

/**
 * Title: MPGS Gateway
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 8.81.0.0
 * @since 8.81.0.0
 */
class Gateway extends Core_Gateway {
	/**
	 * Initializes an MPGS gateway
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

		$this->config = $config;
		$this->api    = new API( $config );

		$this->register_payment_methods();
	}

	private function register_payment_methods() {
		$this->register_payment_method( new PaymentMethod( PaymentMethods::CREDIT_CARD ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::VISA ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::MASTERCARD ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::AMERICAN_EXPRESS ) );
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
		$order_id = $payment->key . '_' . $payment->get_id();
		$payment->set_transaction_id( $order_id );
		
		$session_result  = $this->api->initiate_checkout( $this->get_payment_data( $payment ) );
		$mpgs_session_id = $session_result->session->id;

		$payment->add_note( 'MPGS session_id: ' . $mpgs_session_id );
		$payment->set_meta( 'mpgs_session_id', $mpgs_session_id );

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
		$customer = $payment->get_customer();

		$order_amount   = $payment->get_total_amount()->number_format( null, '.', '' );
		$order_currency = $payment->get_total_amount()->get_currency()->get_alphabetic_code();
		
		$customer_name = substr( trim( ( html_entity_decode( $customer->get_name(), ENT_QUOTES, 'UTF-8' ) ) ), 0, 75 );

		$data = [
			'apiOperation' => 'INITIATE_CHECKOUT',
			'interaction'  => [
				'operation'      => 'PURCHASE',
				'returnUrl'      => $payment->get_return_url(),
				'cancelUrl'      => add_query_arg( 'action', Statuses::CANCELLED, $payment->get_return_url() ),
				'timeoutUrl'     => $payment->get_return_url(),
				'locale'         => $customer->get_locale(),
				'displayControl' => [
					'billingAddress' => 'HIDE',
					'customerEmail'  => 'HIDE',
					'shipping'       => 'HIDE',
				],
				'merchant'       => [
					/*
					 'address' => [
						'line1' => $this->getMerchantAddressLine1(),
						'line2' => $this->getMerchantAddressLine2(),
					], */
					'name' => get_bloginfo(),
					/* 'email' => $this->getMerchantEmail(), */
					'url'  => home_url( '/' ),
					// 'logo' => get_site_icon_url(140),
				],
			],
			'order'        => [
				'id'          => $payment->get_transaction_id(),
				'amount'      => $order_amount,
				'currency'    => $order_currency,
				'description' => $payment->get_description(),
				'reference'   => $payment->get_order_id(),
				// 'notificationUrl' => $payment->get_return_url(), TODO
			],
		];
		
		if ( ! empty( $customer_name ) ) {
			$data['order']['requestorName'] = $customer_name;
		}
		
		return $data;
	}
	
	/**
	 * Output form.
	 *
	 * @param Payment $payment Payment.
	 * @return void
	 * @throws \Exception When payment action URL is empty.
	 */
	public function output_form(
		Payment $payment
		) {
		if ( PaymentStatus::SUCCESS === $payment->get_status() || PaymentStatus::EXPIRED === $payment->get_status() ) {
			wp_safe_redirect( $payment->get_return_redirect_url() );
			exit;
		}

		$html = "<script src='{$this->config->mpgs_url}/static/checkout/checkout.min.js' data-error='errorCallback' data-cancel='cancelCallback'></script>";

		$html .= '<script>';
		$html .= "Checkout.configure({session: {id: '{$payment->get_meta('mpgs_session_id')}'}});";

		if ( ! ( defined( '\PRONAMIC_PAY_DEBUG' ) && \PRONAMIC_PAY_DEBUG ) ) {
			$html .= 'Checkout.showPaymentPage();';
		}

		$html .= 'document.getElementById("pronamic_ideal_form").onsubmit = function(e){e.preventDefault();Checkout.showPaymentPage();}</script>';

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
		
		if ( isset( $_GET['action'] ) && Statuses::CANCELLED === $_GET['action'] ) {
			$payment->set_status( Statuses::transform( $_GET['action'] ) );
			return;
		}

		$order_details = $this->api->get_order( $payment->get_transaction_id() );
		if ( pronamic_pay_plugin()->is_debug_mode() ) {
			$payment->add_note( '<strong>MPGS Order Details:</strong><br><pre>' . print_r( $order_details, true ) . '</pre><br>' );
		}
		
		$payment->set_status( Statuses::transform( $order_details->status ) );
	}
}
