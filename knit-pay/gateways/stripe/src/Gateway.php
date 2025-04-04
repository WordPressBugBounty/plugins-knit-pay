<?php
namespace KnitPay\Gateways\Stripe;

use Pronamic\WordPress\Pay\Core\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Core\PaymentMethod;
use Pronamic\WordPress\Pay\Payments\FailureReason;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;

/**
 * Title: Stripe Gateway
 * Copyright: 2020-2025 Knit Pay
 *
 * @author Knit Pay
 * @version 8.96.11.0
 * @since 3.1.0
 */
class Gateway extends Core_Gateway {
	protected $config;

	const NAME = 'stripe';

	/**
	 * Initializes an Stripe gateway
	 *
	 * @param Config $config Config.
	 */
	public function init( Config $config ) {
		$this->config = $config;

		$this->set_method( self::METHOD_HTTP_REDIRECT );

		// Supported features.
		$this->supports = [
			'payment_status_request',
		];

		\Stripe\Stripe::setAppInfo( 'Knit Pay', KNITPAY_VERSION, 'https://www.knitpay.org/' );

		$this->register_payment_methods();
	}

	private function register_payment_methods() {
		// ref: https://docs.stripe.com/api/payment_methods/object
		$this->register_payment_method( new PaymentMethod( PaymentMethods::ALIPAY ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::CREDIT_CARD ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::IDEAL ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::BANCONTACT ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::BLIK ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::GIROPAY ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::EPS ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::SOFORT ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::DIRECT_DEBIT ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::AFTERPAY_COM ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::STRIPE ) );
	}

	/**
	 * Get available payment methods.
	 *
	 * @return array<int, string>
	 * @see Core_Gateway::get_available_payment_methods()
	 */
	public function get_available_payment_methods() {
		// FIXME
		if ( ! empty( $this->config->enabled_payment_methods ) ) {
			$this->config->enabled_payment_methods[] = 'stripe';
		}
		return $this->config->enabled_payment_methods;
	}

	/**
	 * Start.
	 *
	 * @see Core_Gateway::start()
	 *
	 * @param Payment $payment Payment.
	 */
	public function start( Payment $payment ) {
		if ( self::MODE_LIVE === $payment->get_mode() && ! $this->config->is_live_set() ) {
			throw new \Exception( 'Stripe is not connected in Live mode.' );
		}

		if ( self::MODE_TEST === $payment->get_mode() && ! $this->config->is_test_set() ) {
			throw new \Exception( 'Stripe is not connected in Test mode.' );
		}

		$stripe = $this->get_stripe_client();
		
		try {
			$session_data     = $this->create_session_data( $payment );
			$checkout_session = $stripe->checkout->sessions->create( $session_data );
		} catch ( \Stripe\Exception\ApiErrorException $e ) {
			throw new \Exception( $e->getError()->message );
		}

		$payment->set_transaction_id( $payment->key . '_' . $payment->get_id() );
		$payment->set_meta( 'stripe_session_id', $checkout_session->id );

		$payment->set_action_url( $checkout_session->url );
	}

	protected function create_session_data( Payment $payment ) {
		$customer = $payment->get_customer();

		$payment_amount   = $this->get_payment_amount( $payment );
		$payment_currency = $this->get_payment_currency( $payment );

		$payment_method_types = PaymentMethods::transform( $payment->get_payment_method(), $this->config->enabled_payment_methods );

		$session_data = [
			'client_reference_id'  => $payment->get_id(),
			'customer_email'       => $customer->get_email(),
			'line_items'           => [
				[
					'price_data' => [
						'currency'     => $payment_currency,
						'product_data' => [
							'name' => $payment->get_description(),
						],
						'unit_amount'  => $payment_amount,
					],
					'quantity'   => 1,
				],
			],
			'metadata'             => $this->get_metadata( $payment ),
			'mode'                 => 'payment',
			'success_url'          => $payment->get_return_url(),
			'cancel_url'           => add_query_arg( 'cancelled', true, $payment->get_return_url() ),
			'payment_method_types' => $payment_method_types,
		];
		// TODO: improve  line items.

		return $session_data;
	}

	protected function get_payment_amount( Payment $payment ) {
		$stripe_payment_currency = $this->config->payment_currency;
		$exchange_rate           = $this->config->exchange_rate;

		$payment_amount = $payment->get_total_amount()->number_format( null, '.', '' ) * 100;

		if ( ! empty( $stripe_payment_currency ) ) {
			$payment_amount = $exchange_rate * $payment_amount;
		}
		return round( $payment_amount );
	}

	private function get_payment_currency( Payment $payment ) {
		$stripe_payment_currency = $this->config->payment_currency;
		$payment_currency        = $payment->get_total_amount()->get_currency()->get_alphabetic_code();

		if ( ! empty( $stripe_payment_currency ) && $stripe_payment_currency !== $payment_currency ) {
			$payment_currency = $stripe_payment_currency;
		}

		return $payment_currency;
	}

	/**
	 * Update status of the specified payment.
	 *
	 * @param Payment $payment Payment.
	 */
	public function update_status( Payment $payment ) {
		if ( PaymentStatus::SUCCESS === $payment->get_status() ) {
			return;
		}

		if ( filter_has_var( INPUT_GET, 'cancelled' ) ) {
			$payment->set_status( PaymentStatus::CANCELLED );
			return;
		}

		$stripe = $this->get_stripe_client();

		// Retrieve the Checkout Session from the API with line_items expanded
		$checkout_session = $stripe->checkout->sessions->retrieve(
			$payment->get_meta( 'stripe_session_id' )
		);

		if ( ! isset( $checkout_session->payment_intent ) ) {
			return;
		}

		$payment->set_transaction_id( $checkout_session->payment_intent );
		$stripe_payment_intents = $stripe->paymentIntents->retrieve( $checkout_session->payment_intent );

		$payment->set_status( Statuses::transform( $stripe_payment_intents->status ) );

		unset( $stripe_payment_intents->next_action );
		unset( $stripe_payment_intents->client_secret );

		if ( isset( $stripe_payment_intents->last_payment_error ) ) {
			$failure_reason = new FailureReason();
			$failure_reason->set_message( $stripe_payment_intents->last_payment_error->message );
			$failure_reason->set_code( $stripe_payment_intents->last_payment_error->code );
			$payment->set_failure_reason( $failure_reason );
			$payment->set_status( PaymentStatus::FAILURE );

			unset( $stripe_payment_intents->last_payment_error->payment_method );
		}

		$note = '<strong>Stripe Payment Intents:</strong><br><pre>' . print_r( $stripe_payment_intents, true ) . '</pre><br>';
		$payment->add_note( $note );
	}

	private function get_metadata( Payment $payment ) {
		$source = $payment->get_source();
		if ( 'woocommerce' === $source ) {
			$source = 'wc';
		}
		$notes = [
			'knitpay_payment_id' => $payment->get_id(),
			'knitpay_extension'  => $source,
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

	private function get_stripe_client() {
		$secret_key = $this->config->get_secret_key();

		return new \Stripe\StripeClient(
			[
				'api_key'        => $secret_key,
				'stripe_version' => '2025-03-31.basil', // @see https://docs.stripe.com/changelog#2025-03-31.basil
			]
		);
	}
}
