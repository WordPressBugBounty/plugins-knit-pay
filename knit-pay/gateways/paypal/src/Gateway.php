<?php
namespace KnitPay\Gateways\Paypal;

use Pronamic\WordPress\Pay\Core\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Core\PaymentMethod;
use Pronamic\WordPress\Pay\Payments\Payment;
use KnitPay\Gateways\PaymentMethods;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;
use Pronamic\WordPress\Pay\Refunds\Refund;
use Exception;
use Pronamic\WordPress\Pay\Payments\FailureReason;

/**
 * Title: PayPal Gateway
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
	 * Constructs and initializes an PayPal gateway
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
		$total_amount     = $payment->get_total_amount();
		$customer         = $payment->get_customer();
		$shipping_address = $payment->get_shipping_address();

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
						'shipping_preference'       => 'NO_SHIPPING',
						'user_action'               => 'PAY_NOW',
						'locale'                    => str_replace( '_', '-', $customer->get_locale() ),
						'return_url'                => $payment->get_return_url(),
						'cancel_url'                => add_query_arg( 'cancelled', true, $payment->get_return_url() ),
						'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED', // TODO add support for cheque payment.
					],
				],
			],
		];

		if ( isset( $shipping_address ) ) {
			$data['purchase_units'][0]['shipping'] = [
				'name'          => [
					'full_name' => substr( trim( ( html_entity_decode( $shipping_address->get_name(), ENT_QUOTES, 'UTF-8' ) ) ), 0, 50 ),
				],
				'email_address' => $shipping_address->get_email(),
				'address'       => [
					'address_line_1' => $shipping_address->get_line_1(),
					'address_line_2' => $shipping_address->get_line_2(),
					'admin_area_2'   => $shipping_address->get_city(),
					'admin_area_1'   => is_null( $shipping_address->get_region() ) ? '' : $shipping_address->get_region()->get_value(),
					'postal_code'    => $shipping_address->get_postal_code(),
					'country_code'   => $shipping_address->get_country_code(),
				],
			];

			$data['payment_source']['paypal']['experience_context']['shipping_preference'] = 'SET_PROVIDED_ADDRESS';
		} else {
			$data['payment_source']['paypal']['experience_context']['shipping_preference'] = 'NO_SHIPPING';
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
		if ( PaymentStatus::SUCCESS === $payment->get_status() ) {
			return;
		}

		if ( filter_has_var( INPUT_GET, 'cancelled' ) ) {
			$payment->set_status( PaymentStatus::CANCELLED );
			return;
		}

		$order_details = $this->api->get_order_details( $payment->get_transaction_id() );

		$note = '<strong>PayPal Order Details:</strong><br><pre>' . print_r( $order_details, true ) . '</pre><br>';
		$payment->add_note( $note );

		// If order status is not approved, update payment status else capture the payment.
		if ( $order_details->status !== Statuses::APPROVED ) {
			$payment->set_status( Statuses::transform( $order_details->status ) );
			return;
		}

		// capture order if order status is approved.
		$capture_status = $this->api->capture_payment( $order_details->id );
		$note           = '<strong>PayPal Capture Status:</strong><br><pre>' . print_r( $capture_status, true ) . '</pre><br>';
		$payment->add_note( $note );

		if ( isset( $capture_status->details ) ) {
			return $this->mark_payment_failed( $payment, $capture_status->details[0]->description, $capture_status->details[0]->issue );
		} elseif ( isset( $capture_status->message ) ) {
			return $this->mark_payment_failed( $payment, $capture_status->message, $capture_status->name );
		}

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

	private function mark_payment_failed( $payment, $message, $code ) {
		$failure_reason = new FailureReason();
		$failure_reason->set_message( $message );
		$failure_reason->set_code( $code );
		$payment->set_failure_reason( $failure_reason );
		$payment->set_status( PaymentStatus::FAILURE );
	}
}
