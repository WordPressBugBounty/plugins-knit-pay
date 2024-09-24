<?php
namespace KnitPay\Gateways\Flutterwave;

use Pronamic\WordPress\Pay\Core\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Core\PaymentMethod;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;

/**
 * Title: Flutterwave Gateway
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 4.8.0
 * @since 4.8.0
 */
class Gateway extends Core_Gateway {

	/**
	 * Constructs and initializes an Flutterwave gateway
	 *
	 * @param Config $config
	 *            Config.
	 */
	public function __construct( Config $config ) {
		parent::__construct( $config );

		$this->set_method( self::METHOD_HTTP_REDIRECT );

		// Client.
		$this->api = new API( $config->secret_key );

		$this->register_payment_methods();
	}

	private function register_payment_methods() {
		$this->register_payment_method( new PaymentMethod( PaymentMethods::CREDIT_CARD ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::BANK_TRANSFER ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::PAYPAL ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::FLUTTERWAVE ) );
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

			$payment_link = $this->api->get_payment_link( $this->get_payment_data( $payment ) );

			$payment->set_action_url( $payment_link );
	}

	/**
	 * Get data json string.
	 *
	 * @param Payment $payment
	 *            Payment.
	 * @see https://developer.flutterwave.com/docs/flutterwave-standard#step-1---collect-payment-details
	 *
	 * @return string
	 */
	public function get_payment_data( Payment $payment ) {
		$total_amount    = $payment->get_total_amount();
		$customer        = $payment->get_customer();
		$billing_address = $payment->get_billing_address();

		$data = [
			'tx_ref'          => $payment->get_transaction_id(),
			'amount'          => $total_amount->number_format( null, '.', '' ),
			'currency'        => $total_amount->get_currency()->get_alphabetic_code(),
			'payment_options' => 'card',
			'redirect_url'    => $payment->get_return_url(),
			'customer'        => [
				'email'       => $customer->get_email(),
				'phonenumber' => $billing_address->get_phone(),
				'name'        => substr( trim( ( html_entity_decode( $customer->get_name(), ENT_QUOTES, 'UTF-8' ) ) ), 0, 45 ),
			],
			'meta'            => $this->get_metadata( $payment ),
		];

		return wp_json_encode( $data );
	}

	/**
	 * Update status of the specified payment.
	 *
	 * @param Payment $payment
	 *            Payment.
	 */
	public function update_status( Payment $payment ) {
		if ( ! filter_has_var( INPUT_GET, 'tx_ref' ) ) {
			return;
		}

		$transaction_id = filter_input( INPUT_GET, 'transaction_id', FILTER_SANITIZE_STRING );
		$status         = filter_input( INPUT_GET, 'status', FILTER_SANITIZE_STRING );

		if ( 'successful' !== $status ) {
			$payment->set_status( PaymentStatus::FAILURE );
			return;
		}

			$transaction_details = $this->api->get_transaction_details( $transaction_id );

			unset( $transaction_details->meta );
			unset( $transaction_details->__CheckoutInitAddress );
			$note = '<strong>Flutterwave Transaction Details:</strong><br>' . print_r( $transaction_details, true );
			$payment->add_note( $note );

		if ( ! ( $transaction_details->tx_ref === $payment->get_transaction_id()
				&& floatval( $transaction_details->charged_amount ) === floatval( $payment->get_total_amount()->number_format( null, '.', '' ) ) ) ) {
			$payment->set_status( PaymentStatus::FAILURE );
			return;
		}

		if ( 'successful' === $transaction_details->status ) {
			$payment->set_status( PaymentStatus::SUCCESS );
			$payment->set_transaction_id( $transaction_details->flw_ref );
		}

	}

	private function get_metadata( Payment $payment ) {
		$notes = [
			'knitpay_payment_id' => $payment->get_id(),
			'knitpay_extension'  => $payment->get_source(),
			'knitpay_source_id'  => $payment->get_source_id(),
			'knitpay_order_id'   => $payment->get_order_id(),
			'knitpay_version'    => KNITPAY_VERSION,
			'website_url'        => home_url( '/' ),
		];

		$customer      = $payment->get_customer();
		$customer_name = substr( trim( ( html_entity_decode( $customer->get_name(), ENT_QUOTES, 'UTF-8' ) ) ), 0, 45 );
		if ( ! empty( $customer_name ) ) {
			$notes = [
				'customer_name' => $customer_name,
			] + $notes;
		}

		return $notes;
	}
}
