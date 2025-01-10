<?php
namespace KnitPay\Gateways\Paypal;

use Pronamic\WordPress\Pay\Core\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Core\PaymentMethod;
use Pronamic\WordPress\Pay\Payments\Payment;
use KnitPay\Gateways\PaymentMethods;

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
		$payment->set_action_url( $payment_order->links[1]->href );
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

		$data = [
			'intent'         => 'CAPTURE',
			'purchase_units' => [
				[
					'amount' => [
						'currency_code' => $total_amount->get_currency()->get_alphabetic_code(),
						'value'         => $total_amount->number_format( null, '.', '' ),
					],
				],
			],
			'payment_source' => [
				'paypal' => [
					'experience_context' => [
						'return_url' => $payment->get_return_url(),
						'cancel_url' => $payment->get_return_url(),
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
		$order_details = $this->api->get_order_details( $payment->get_transaction_id() );

		$note = '<strong>Paypal Order Details:</strong><br>' . print_r( $order_details, true );
		$payment->add_note( $note );

		$payment->set_status( Statuses::transform( $order_details->status ) );
	}
}
