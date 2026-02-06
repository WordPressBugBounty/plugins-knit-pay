<?php
namespace KnitPay\Gateways\ZohoPay;

use KnitPay\Gateways\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Core\PaymentMethod;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;
use Exception;
use Pronamic\WordPress\Pay\Payments\FailureReason;
use Pronamic\WordPress\Pay\Refunds\Refund;
use KnitPay\Utils as KnitPayUtils;
use KnitPay\Gateways\PaymentMethods;

/**
 * Title: Zoho Pay Gateway
 * Copyright: 2020-2026 Knit Pay
 *
 * @author Knit Pay
 * @version 9.0.0.0
 * @since 9.0.0.0
 */
class Gateway extends Core_Gateway {
	private $config;

	/**
	 * Initializes an Zoho Pay gateway
	 *
	 * @param Config $config
	 *            Config.
	 */
	public function init( Config $config ) {
		$this->set_method( self::METHOD_HTTP_REDIRECT );
		$this->default_currency     = 'INR';
		$this->supported_currencies = [ 'INR' ];

		// Supported features.
		$this->supports = [
			'payment_status_request',
		];

		$this->config = $config;

		$this->register_payment_methods();
	}

	private function register_payment_methods() {
		$this->register_payment_method( new PaymentMethod( PaymentMethods::CREDIT_CARD ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::DEBIT_CARD ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::NET_BANKING ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::UPI ) );
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
		$transaction_id = $payment->key . '_' . $payment->get_id();
		$payment->set_transaction_id( $transaction_id );

		$api_client   = new API( $this->config );
		$payment_link = $api_client->create_payment_link( $this->get_payment_data( $payment ) );

		$payment->set_transaction_id( $payment_link->payment_link_id );
		$payment->set_action_url( $payment_link->url );
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

		$reference_id        = $payment->get_transaction_id();
		$amount              = $payment->get_total_amount()->number_format( null, '.', '' );
		$currency            = $payment->get_total_amount()->get_currency()->get_alphabetic_code();
		$payment_description = KnitPayUtils::substr_after_trim( $payment->get_description(), 0, 500 );
		$customer_email      = $customer->get_email();
		$return_url          = $payment->get_return_url();

		// @see https://www.zoho.com/in/payments/api/v1/payment-links/#create-payment-link
		$data = [
			'amount'       => $amount,
			'currency'     => $currency,
			'reference_id' => $reference_id,
			'description'  => $payment_description,
			'expires_at'   => date( 'Y-m-d', strtotime( '+1 day' ) ),
			'notify_user'  => true,
			'return_url'   => $return_url,
		];

		if ( ! empty( $billing_address ) && ! empty( $billing_address->get_phone() ) ) {
			$data['phone'] = $billing_address->get_phone();
		}

		if ( ! empty( $customer_email ) ) {
			$data['email'] = $customer_email;
		}

		return $data;
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

		$api_client        = new API( $this->config );
		$payment_link_data = $api_client->retrieve_payment_link( $payment->get_transaction_id() );

		//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		$payment->add_note( '<strong>Zoho Payment Link Details:</strong><br><pre>' . print_r( $payment_link_data, true ) . '</pre><br>' );

		if ( isset( $payment_link_data->status ) ) {
			$payment->set_status( Statuses::transform( $payment_link_data->status ) );
		}
	}
}
