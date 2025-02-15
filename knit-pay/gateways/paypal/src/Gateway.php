<?php
namespace KnitPay\Gateways\Paypal;

use Pronamic\WordPress\Pay\Core\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Core\PaymentMethod;
use Pronamic\WordPress\Pay\Payments\Payment;
use KnitPay\Gateways\PaymentMethods;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;
use Pronamic\WordPress\Pay\Refunds\Refund;
use Exception;

/**
 * Title: Paypal Gateway
 * Copyright: 2020-2025 Knit Pay
 *
 * @author Knit Pay
 * @version 8.94.0.0
 * @since 8.94.0.0
 */
class Gateway extends Core_Gateway {
	/**
	 * API instance.
	 *
	 * @var API
	 */
	private $api;

	/**
	 * Constructs and initializes an Paypal gateway
	 *
	 * @param Config $config
	 *            Config.
	 */
	public function init( Config $config ) {
		$this->set_method( self::METHOD_HTTP_REDIRECT );

		// Supported features.
		$this->supports = [
			'payment_status_request',
			'refunds',
		];

		// Client.
		$this->api = new API( $config );

		$this->register_payment_methods();
	}

	private function register_payment_methods() {
		$this->register_payment_method( new PaymentMethod( PaymentMethods::CREDIT_CARD ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::PAYPAL ) );
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
		$payment_order = $this->api->create_order( $this->get_payment_data( $payment ) );

		$payment->set_transaction_id( $payment_order->id );

		$payment->set_action_url( $this->get_action_link( $payment_order, 'payer-action' ) );

		// TODO
		// Review https://developer.paypal.com/studio/checkout/standard/integrate again.
	}

	/**
	 * Get data json string.
	 *
	 * @param Payment $payment
	 *            Payment.
	 * @see https://developer.paypal.com/docs/api/orders/v2/#orders_create
	 *
	 * @return string
	 */
	public function get_payment_data( Payment $payment ) {
		$total_amount = $payment->get_total_amount();
		$customer     = $payment->get_customer();

		$data = [
			'purchase_units' => [
				[
					'description' => $payment->get_description(),
					'custom_id'   => $payment->get_id(),
					'invoice_id'  => $payment->get_source_id(),
					'amount'      => [
						'currency_code' => $total_amount->get_currency()->get_alphabetic_code(),
						'value'         => $total_amount->number_format( null, '.', '' ),
					],
				],
			],
			'intent'         => 'CAPTURE',
			'payment_source' => [
				'paypal' => [
					'experience_context' => [
						'user_action' => 'PAY_NOW',
						'locale'      => str_replace( '_', '-', $customer->get_locale() ),
						'return_url'  => $payment->get_return_url(),
						'cancel_url'  => $payment->get_return_url(),
					],
				],
			],

		];

		return $data;
	}

	/**
	 * Update status of the specified payment.
	 *
	 * @param Payment $payment
	 *            Payment.
	 */
	public function update_status( Payment $payment ) {
		if ( PaymentStatus::SUCCESS === $payment->get_status() ) {
			return;
		}

		$order_details = $this->api->get_order_details( $payment->get_transaction_id() );

		$note = '<strong>Paypal Order Details:</strong><br><pre>' . print_r( $order_details, true ) . '</pre><br>';
		$payment->add_note( $note );

		// If order status is not approved, update payment status else capture the payment.
		if ( $order_details->status !== Statuses::APPROVED ) {
			$payment->set_status( Statuses::transform( $order_details->status ) );
			return;
		}

		// capture order if order status is approved.
		$capture_status = $this->api->capture_payment( $payment->get_transaction_id() );
		$note           = '<strong>Paypal Capture Status:</strong><br><pre>' . print_r( $capture_status, true ) . '</pre><br>';
		$payment->add_note( $note );

		// If Status is Complete, further investigate the payment status is required.
		if ( $capture_status->status !== Statuses::COMPLETED ) {
			$payment->set_status( Statuses::transform( $capture_status->status ) );
			return;
		}

		$order_payments = reset( $capture_status->purchase_units )->payments;
		$captures       = $order_payments->captures;
		$capture        = reset( $captures );

		$payment->set_status( Statuses::transform( $capture->status ) );
		$payment->set_transaction_id( $capture->id );
	}

	private function get_action_link( $payment_order, $action ) {
		foreach ( $payment_order->links as $link ) {
			if ( $action === $link->rel ) {
				return $link->href;
			}
		}

		return null;
	}

	/**
	 * Create refund.
	 *
	 * @param Refund $refund Refund.
	 * @return void
	 * @throws Exception Throws exception on unknown resource type.
	 */
	public function create_refund( Refund $refund ) {
		$amount         = $refund->get_amount();
		$transaction_id = $refund->get_payment()->get_transaction_id();
		$description    = $refund->get_description();

		$refund_data = [
			'amount' => [
				'currency_code' => $amount->get_currency()->get_alphabetic_code(),
				'value'         => $amount->number_format( null, '.', '' ),
			],
		];

		if ( '' !== $description ) {
			$refund_data['note_to_payer'] = $description;
		}

		$refund_response = $this->api->refund_payment( $transaction_id, $refund_data );
		if ( Statuses::COMPLETED !== $refund_response->status ) {
			new Exception( $refund_response->status_details->reason );
		}

		$refund->psp_id = $refund_response->id;
	}
}
