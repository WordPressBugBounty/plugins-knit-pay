<?php
namespace KnitPay\Gateways\NMI;

use Pronamic\WordPress\Pay\Core\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Payments\FailureReason;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;
use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: NMI Gateway
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 8.83.0.0
 * @since 8.83.0.0
 */
class Gateway extends Core_Gateway {
	/**
	 * Initializes an NMI gateway
	 *
	 * @param Config $config
	 *            Config.
	 */
	public function init( Config $config ) {
		$this->set_method( self::METHOD_HTML_FORM );

		$this->config = $config;

		$this->api = new API();
		$this->api->setLogin( $this->config->private_key );
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
		$payment->set_transaction_id( $payment->key . '_' . $payment->get_id() );
		$payment->set_action_url( $payment->get_return_url() );
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
		if ( PaymentStatus::SUCCESS === $payment->get_status() ) {
			wp_safe_redirect( $payment->get_return_redirect_url() );
			exit;
		}

		$form_inner = sprintf(
			'<input id="payButton" class="pronamic-pay-btn" type="submit" name="pay" value="%s" disabled/>',
			__( 'Pay', 'knit-pay-lang' )
		);

		$action_url = $payment->get_action_url();

		$html = sprintf(
			'<form id="pronamic_ideal_form" name="pronamic_ideal_form" method="post" action="%s">%s</form>',
			esc_attr( $action_url ),
			$form_inner
		);

		require_once 'views/checkout.php';

		return $html;
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

		if ( isset( $_GET['action'] ) && Core_Statuses::CANCELLED === $_GET['action'] ) {
			$payment->set_status( Core_Statuses::CANCELLED );
			return;
		}

		if ( ! isset( $_POST['payment_token'] ) ) {
			$payment->set_status( Core_Statuses::FAILURE );
			return;
		}

		$this->api->setBilling( $payment );
		$this->api->setOrder( $payment );

		$response = $this->api->doSale( $payment, $_POST['payment_token'] );

		if ( $response === Statuses::ERROR ) {
			$failure_reason = new FailureReason();
			$failure_reason->set_message( $this->api->responses['responsetext'] );
			$failure_reason->set_code( $this->api->responses['response_code'] );
			$payment->set_failure_reason( $failure_reason );
		} elseif ( $response === Statuses::APPROVED ) {
			$payment->set_transaction_id( $this->api->responses['transactionid'] );
		}

		$payment->set_status( Statuses::transform( $response ) );
		$payment->add_note( '<strong>NMI Transaction:</strong><br><pre>' . print_r( $this->api->responses, true ) . '</pre>' );
	}

	private function format_phone_number( $customer_phone ) {
		// Remove - or whitespace.
		$customer_phone = preg_replace( '/[\s\-]+/', '', $customer_phone );

		// Remove 0 from beginning of phone number.
		$customer_phone = 10 < strlen( $customer_phone ) ? ltrim( $customer_phone, '0' ) : $customer_phone;

		return $customer_phone;
	}
}
