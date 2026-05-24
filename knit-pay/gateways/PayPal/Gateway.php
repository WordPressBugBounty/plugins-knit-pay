<?php
namespace KnitPay\Gateways\PayPal;

use KnitPay\Gateways\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Core\PaymentMethod;
use Pronamic\WordPress\Pay\Payments\Payment;
use KnitPay\Gateways\PaymentMethods;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;
use Pronamic\WordPress\Pay\Refunds\Refund;
use Exception;
use Pronamic\WordPress\Pay\Payments\FailureReason;
use KnitPay\Utils as KnitPayUtils;

/**
 * Title: PayPal Gateway
 * Copyright: 2020-2026 Knit Pay
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

	private $config;

	/**
	 * Constructs and initializes a PayPal gateway
	 *
	 * @param Config $config
	 *            Config.
	 */
	public function init( Config $config ) {
		$this->default_currency     = 'USD';
		$this->supported_currencies = [ 'AUD', 'BRL', 'CAD', 'CNY', 'CZK', 'DKK', 'EUR', 'HKD', 'HUF', 'ILS', 'JPY', 'MYR', 'MXN', 'NZD', 'NOK', 'PHP', 'PLN', 'GBP', 'SGD', 'SEK', 'CHF', 'THB', 'TWD', 'USD' ];

		// Supported features.
		$this->supports = [
			'payment_status_request',
			'refunds',
		];

		$this->is_iframe_checkout_method = true;

		// Client.
		$this->config = $config;
		$this->api    = new API( $config );

		$this->register_payment_methods();
	}

	private function register_payment_methods() {
		$this->register_payment_method( new PaymentMethod( PaymentMethods::CREDIT_CARD ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::CARD ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::PAYPAL ) );
	}

	// AJAX handler to create a PayPal order before the buyer approves it.
	public static function ajax_create_order() {
		if ( ! check_ajax_referer( 'knitpay_paypal_checkout', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => 'Security check failed. Please refresh the page and try again.' ], 403 );
		}

		$payment_id = isset( $_POST['payment_id'] ) ? absint( $_POST['payment_id'] ) : 0;
		if ( empty( $payment_id ) ) {
			wp_send_json_error( [ 'message' => 'Invalid payment ID.' ], 400 );
		}

		$payment = \get_pronamic_payment( $payment_id );
		if ( null === $payment ) {
			wp_send_json_error( [ 'message' => 'Payment not found.' ], 404 );
		}

		// Prevent double payment if already completed in another tab or via webhook.
		if ( PaymentStatus::SUCCESS === $payment->get_status() ) {
			wp_send_json_error(
				[
					'message'      => 'This payment has already been completed.',
					'already_paid' => true,
				],
				409
			);
		}

		try {
			$integration = new Integration();

			$gateway = $integration->get_gateway( $payment->get_config_id() );

			// Check for an existing PayPal order and reuse/recycle it.
			// We use payment meta (not transaction_id) because capture replaces
			// transaction_id with the capture ID, breaking order lookups.
			$existing_order_id = $payment->get_meta( 'paypal_order_id' );
			if ( ! empty( $existing_order_id ) ) {
				try {
					$order_details = $gateway->api->get_order_details( $existing_order_id );
					$order_status  = $order_details->status ?? null;

					// Order completed or approved — payment is done or being captured.
					// Redirect the buyer to the return URL so webhook/capture finishes it.
					if ( \in_array( $order_status, [ 'COMPLETED', 'APPROVED' ], true ) ) {
						wp_send_json_error(
							[
								'message'      => 'This payment has already been completed.',
								'already_paid' => true,
							],
							409
						);
					}

					// Reusable order (CREATED, SAVED, or PAYER_ACTION_REQUIRED).
					// CREATED/SAVED: fresh order, no action taken yet.
					// PAYER_ACTION_REQUIRED: buyer started a payment flow (e.g. opened
					// a popup) but hasn't completed it.  The order is still reusable —
					// the same orderId works across payment methods and tabs.  Reusing
					// it prevents duplicate orders and protects against double-payment
					// when the same payment page is opened in multiple tabs.
					if ( \in_array( $order_status, [ 'CREATED', 'SAVED', 'PAYER_ACTION_REQUIRED' ], true ) ) {
						wp_send_json_success( [ 'order_id' => $existing_order_id ] );
					}
					// Any other terminal state (VOIDED, RESOURCE_NOT_FOUND, etc.) falls
					// through to create a brand-new order below.
				} catch ( \Exception $e ) {
					// Order not found on PayPal or network error → fall through to create new.
					$order_lookup_failed = true;
				}
			}

			$payment_order = $gateway->api->create_order( $gateway->get_payment_data( $payment ) );

			if ( isset( $payment_order->id ) ) {
				$payment->set_meta( 'paypal_order_id', $payment_order->id );
				$payment->set_transaction_id( $payment_order->id );
				$payment->save();

				wp_send_json_success( [ 'order_id' => $payment_order->id ] );
			}

			wp_send_json_error( [ 'message' => 'Failed to create PayPal order.' ], 500 );
		} catch ( Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ], 500 );
		}
	}

	// AJAX handler to capture an approved PayPal order and finalise payment.
	public static function ajax_capture_order() {
		if ( ! check_ajax_referer( 'knitpay_paypal_checkout', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => 'Security check failed. Please refresh the page and try again.' ], 403 );
		}

		$order_id   = isset( $_POST['order_id'] ) ? sanitize_text_field( wp_unslash( $_POST['order_id'] ) ) : '';
		$payment_id = isset( $_POST['payment_id'] ) ? absint( $_POST['payment_id'] ) : 0;
		if ( empty( $order_id ) || empty( $payment_id ) ) {
			wp_send_json_error( [ 'message' => 'Invalid request parameters.' ], 400 );
		}

		$payment = \get_pronamic_payment( $payment_id );
		if ( null === $payment ) {
			wp_send_json_error( [ 'message' => 'Payment not found.' ], 404 );
		}

		try {
			$integration = new Integration();
			$gateway     = $integration->get_gateway( $payment->get_config_id() );

			$payment->set_transaction_id( $order_id );
			$gateway->update_status( $payment );
			$payment->save();

			if ( PaymentStatus::SUCCESS === $payment->get_status() ) {
				wp_send_json_success( [ 'status' => $payment->get_status() ] );
			}

			wp_send_json_error( [ 'message' => 'Failed to capture PayPal order.' ], 500 );
		} catch ( Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ], 500 );
		}
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
		$payment->set_action_url( $payment->get_pay_redirect_url() );
	}

	/**
	 * Build purchase_unit payload for PayPal order creation including shipping.
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
			'purchase_units'         => [
				[
					'description' => $payment->get_description(),
					'custom_id'   => $payment->get_id(),
					'invoice_id'  => $this->config->invoice_prefix . $payment->get_source_id(),
					'amount'      => [
						'currency_code' => $total_amount->get_currency()->get_alphabetic_code(),
						'value'         => $total_amount->number_format( null, '.', '' ),
					],
				],
			],
			'intent'                 => 'CAPTURE',
			'processing_instruction' => 'ORDER_COMPLETE_ON_PAYMENT_APPROVAL',
		];

		if ( isset( $shipping_address ) && null !== $shipping_address->get_country_code() ) {
			$shipping_address_name = '';
			if ( null !== $shipping_address->get_name() ) {
				$shipping_address_name = KnitPayUtils::substr_after_trim( $shipping_address->get_name(), 0, 50 );
			}

			$data['purchase_units'][0]['shipping'] = [
				'name'          => [
					'full_name' => $shipping_address_name,
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
		} elseif ( empty( $payment->get_transaction_id() ) ) {
			return $this->expire_old_payment( $payment );
		}

		if ( isset( $_GET['cancelled'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only checking parameter existence for redirect flow, value not used.
			$payment->set_status( PaymentStatus::CANCELLED );
			return;
		}

		// Don't check payment status again if already checking.
		if ( get_transient( 'knit_pay_checking_status_' . $payment->get_id() ) ) {
			return;
		}
		set_transient( 'knit_pay_checking_status_' . $payment->get_id(), true, 15 );

		$order_details = $this->api->get_order_details( $payment->get_transaction_id() );

		$note = '<strong>PayPal Order Details:</strong><br><pre>' . esc_html( wp_json_encode( $order_details, JSON_PRETTY_PRINT ) ) . '</pre><br>';
		$payment->add_note( $note );

		// If order status is not approved, update payment status else capture the payment.
		if ( Statuses::APPROVED !== $order_details->status ) {
			$payment->set_status( Statuses::transform( $order_details->status ) );
			return $this->expire_old_payment( $payment );
		}

		// capture order if order status is approved.
		$capture_response = $this->api->capture_payment( $order_details->id );
		$note             = '<strong>PayPal Capture Status:</strong><br><pre>' . esc_html( wp_json_encode( $capture_response, JSON_PRETTY_PRINT ) ) . '</pre><br>';
		$payment->add_note( $note );

		if ( isset( $capture_response->details ) ) {
			if ( 'ORDER_ALREADY_CAPTURED' === $capture_response->details[0]->issue ) {
				$capture_response = $this->api->get_order_details( $payment->get_transaction_id() );
			} else {
				return $this->mark_payment_failed( $payment, $capture_response->details[0]->description, $capture_response->details[0]->issue );
			}
		} elseif ( isset( $capture_response->message ) ) {
			return $this->mark_payment_failed( $payment, $capture_response->message, $capture_response->name );
		}

		// If Status is Complete, further investigate the payment status is required.
		if ( Statuses::COMPLETED !== $capture_response->status ) {
			$payment->set_status( Statuses::transform( $capture_response->status ) );
			return;
		}

		$order_payments = reset( $capture_response->purchase_units )->payments;
		$captures       = $order_payments->captures;
		$capture        = reset( $captures );

		$payment->set_status( Statuses::transform( $capture->status ) );
		$payment->set_transaction_id( $capture->id );
	}

	// Render the standalone PayPal v6 payment page and pass data to it.
	public function init_iframe_checkout( Payment $payment ) {
		if ( PaymentStatus::SUCCESS === $payment->get_status() || PaymentStatus::EXPIRED === $payment->get_status() ) {
			wp_safe_redirect( $payment->get_return_redirect_url() );
			exit;
		}

		$sdk_url = 'https://www.paypal.com/web-sdk/v6/core';
		if ( 'test' === $this->config->mode ) {
			$sdk_url = 'https://www.sandbox.paypal.com/web-sdk/v6/core';
		}

		$total_amount     = $payment->get_total_amount();
		$customer         = $payment->get_customer();
		$shipping_address = $payment->get_shipping_address();
		$billing_address  = $payment->get_billing_address();

		$customer_name  = (string) $customer->get_name();
		$customer_email = $customer->get_email();

		$paypal_page_data = [
			'payment_id'        => $payment->get_id(),
			'order_description' => $payment->get_description(),
			'amount'            => $total_amount->number_format( null, '.', '' ),
			'currency_code'     => $total_amount->get_currency()->get_alphabetic_code(),
			'currency_symbol'   => $total_amount->get_currency()->get_symbol(),
			'formatted_amount'  => $payment->get_total_amount()->format_i18n(),
			'customer_name'     => $customer_name,
			'customer_email'    => $customer_email,
			'customer_locale'   => empty( $customer->get_locale() ) ? 'en-US' : str_replace( '_', '-', $customer->get_locale() ),
			'return_url'        => $payment->get_return_url(),
			'cancel_url'        => add_query_arg( 'cancelled', true, $payment->get_return_url() ),
			'ajax_url'          => admin_url( 'admin-ajax.php' ),
			'nonce'             => wp_create_nonce( 'knitpay_paypal_checkout' ),
			'client_id'         => $this->config->client_id,
			'sdk_url'           => $sdk_url,
			'sandbox'           => 'test' === $this->config->mode,
			'merchant_name'     => empty( get_bloginfo( 'name' ) ) ? 'Pay with PayPal' : get_bloginfo( 'name' ),
			'payment_date'      => $payment->get_date()->format_i18n(),
			'source'            => $payment->get_source(),
			'source_id'         => $payment->get_source_id(),
			'shipping_address'  => null,
			'debug'             => knit_pay_plugin()->is_debug_mode(),
		];

		if ( isset( $shipping_address ) && null !== $shipping_address->get_country_code() ) {
			$paypal_page_data['shipping_address'] = [
				'name'         => $shipping_address->get_name(),
				'line_1'       => $shipping_address->get_line_1(),
				'line_2'       => $shipping_address->get_line_2(),
				'city'         => $shipping_address->get_city(),
				'state'        => is_null( $shipping_address->get_region() ) ? '' : $shipping_address->get_region()->get_value(),
				'postal_code'  => $shipping_address->get_postal_code(),
				'country_code' => $shipping_address->get_country_code(),
			];
		}

		if ( 'test' === $this->config->mode ) {
			$test_buyer_country = $this->config->test_buyer_country;

			if ( empty( $test_buyer_country ) ) {
				if ( null !== $billing_address && ! empty( $billing_address->get_country_code() ) ) {
					$test_buyer_country = $billing_address->get_country_code();
				} elseif ( null !== $shipping_address && ! empty( $shipping_address->get_country_code() ) ) {
					$test_buyer_country = $shipping_address->get_country_code();
				}
			}

			if ( ! empty( $test_buyer_country ) ) {
				$paypal_page_data['test_buyer_country'] = $test_buyer_country;
			}
		}

		require_once __DIR__ . '/views/payment-page.php';
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
		if ( ! isset( $refund_response->status ) || Statuses::COMPLETED !== $refund_response->status ) {
			$reason = isset( $refund_response->status_details->reason ) ? $refund_response->status_details->reason : 'Unknown refund failure';
			throw new Exception( esc_html( $reason ) );
		}

		$refund->psp_id = $refund_response->id;
	}

	private function expire_old_payment( $payment ) {
		// Make payment status as expired for payment older than 1 day.
		if ( DAY_IN_SECONDS < time() - $payment->get_date()->getTimestamp() ) {
			$payment->set_status( PaymentStatus::EXPIRED );
		}
	}

	// Record failure reason and mark payment as failed.
	private function mark_payment_failed( $payment, $message, $code ) {
		$failure_reason = new FailureReason();
		$failure_reason->set_message( $message );
		$failure_reason->set_code( $code );
		$payment->set_failure_reason( $failure_reason );
		$payment->set_status( PaymentStatus::FAILURE );
	}
}
