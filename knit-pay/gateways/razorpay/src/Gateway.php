<?php
namespace KnitPay\Gateways\Razorpay;

use KnitPay\Gateways\Gateway as Core_Gateway;
use KnitPay\Utils as KnitPayUtils;
use Pronamic\WordPress\Money\Money;
use Pronamic\WordPress\Number\Number;
use Pronamic\WordPress\Pay\Address;
use Pronamic\WordPress\Pay\Core\PaymentMethod;
use Pronamic\WordPress\Pay\Core\PaymentMethodsCollection;
use Pronamic\WordPress\Pay\Payments\FailureReason;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;
use Pronamic\WordPress\Pay\Refunds\Refund;
use Pronamic\WordPress\Pay\Subscriptions\Subscription;
use Pronamic\WordPress\Pay\Subscriptions\SubscriptionStatus;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\Error;

/**
 * Title: Razorpay Gateway
 * Copyright: 2020-2026 Knit Pay
 *
 * @author Knit Pay
 * @version 1.0.0
 * @since   1.7.0
 */
class Gateway extends Core_Gateway {
	public $config;

	const NAME                  = 'razorpay';
	const STANDARD_CHECKOUT_URL = 'https://api.razorpay.com/v1/checkout/hosted';
	const HOSTED_CHECKOUT_URL   = 'https://api.razorpay.com/v1/checkout/embedded';

	/**
	 * Initializes an Razorpay gateway
	 *
	 * @param Config $config
	 *            Config.
	 */
	public function init( Config $config ) {
		$this->config = $config;

		$this->set_method( self::METHOD_HTML_FORM );

		// Supported features.
		$this->supports = [
			'payment_status_request',
			'refunds',
		];

		if ( 'in-import-flow' === $this->config->country ) {
			$this->default_currency     = 'INR';
			$this->supported_currencies = [ 'INR' ];
		}

		if ( defined( 'KNIT_PAY_RAZORPAY_SUBSCRIPTION' ) ) {
			$this->supports = wp_parse_args(
				$this->supports,
				[
					'recurring',
				]
			);
		}

		$this->payment_page_title = 'Payment Page';

		$this->register_payment_methods();
	}

	private function register_payment_methods() {
		$this->register_payment_method( new PaymentMethod( PaymentMethods::AMERICAN_EXPRESS ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::CARD ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::DEBIT_CARD ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::NET_BANKING ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::FPX ) );

		// Register Recurring Payment Methods.
		$payment_method_credit_card = new PaymentMethod( PaymentMethods::CREDIT_CARD );
		$payment_method_upi         = new PaymentMethod( PaymentMethods::UPI );
		$payment_method_razorpay    = new PaymentMethod( PaymentMethods::RAZORPAY );
		if ( defined( 'KNIT_PAY_RAZORPAY_SUBSCRIPTION' ) ) {
			$payment_method_credit_card->add_support( 'recurring' );
			$payment_method_upi->add_support( 'recurring' );
			$payment_method_razorpay->add_support( 'recurring' );
		}
		$this->register_payment_method( $payment_method_credit_card );
		$this->register_payment_method( $payment_method_upi );
		$this->register_payment_method( $payment_method_razorpay );
	}

	/**
	 * Get payment methods.
	 *
	 * @param array $args Query arguments.
	 * @return PaymentMethodsCollection
	 */
	public function get_payment_methods( array $args = [] ): PaymentMethodsCollection {
		$cache_key = 'knit_pay_razorpay_payment_methods_' . $this->config->config_id;

		$methods = \get_transient( $cache_key );

		if ( false === $methods ) {
			// Call the methods API endpoint (which doesn't exist as a direct entity in Razorpay SDK)
			// @see https://razorpay.com/docs/payments/subscriptions/supported-banks-apps/#fetch-supported-methods
			try {
				$api_client = new Api( $this->config->key_id, null );
				$methods    = $this->call_api( 'methods', 'all', [], $api_client );
				\set_transient( $cache_key, $methods, \DAY_IN_SECONDS );
			} catch ( \Exception $e ) {
				// Handle exception
				$methods = [];
			}
		}

		$this->get_payment_method( PaymentMethods::RAZORPAY )->set_status( 'active' );

		foreach ( $methods as $method_id => $method_status ) {
			if ( ! is_string( $method_id ) ) {
				continue;
			}

			switch ( $method_id ) {
				case 'amex':
					$method_id = PaymentMethods::AMERICAN_EXPRESS;
					break;
				case 'netbanking':
					$method_id = PaymentMethods::NET_BANKING;
					break;
				default:
			}

			$method = $this->get_payment_method( $method_id );
			if ( null === $method ) {
				continue;
			}
			$method->set_status( $method_status ? 'active' : 'inactive' );
		}

		return parent::get_payment_methods( $args );
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
		if ( PaymentStatus::SUCCESS === $payment->get_status() ) {
			return;
		}

		$payment_currency = $payment->get_total_amount()->get_currency()->get_alphabetic_code();

		$customer = $payment->get_customer();

		// Recurring payment method.
		$subscriptions = $payment->get_subscriptions();

		$is_subscription_payment = ( $subscriptions && $this->supports( 'recurring' ) );

		if ( $is_subscription_payment ) {
			$this->create_razorpay_subscription( $payment, $subscriptions, $customer, $payment_currency );
		} else {
			$this->create_razorpay_order( $payment, $customer, $payment_currency );
		}

		$payment->set_transaction_id( $payment->key . '_' . $payment->get_id() );
		$payment->set_action_url( $payment->get_pay_redirect_url() );
	}

	/**
	 * Update status of the specified payment.
	 *
	 * @param Payment $payment
	 *            Payment.
	 */
	public function update_status( Payment $payment ) {
		if ( PaymentStatus::SUCCESS === $payment->get_status() ) {
			// Schedule invoice upload at Razorpay SFTP Server.
			( InvoiceUploader::get_instance() )->upload_invoice_scheduler( $payment );
			return;
		}

		if ( empty( $payment->get_transaction_id() ) ) {
			return;
		}

		$razorpay_order_id        = $payment->get_meta( 'razorpay_order_id' );
		$razorpay_subscription_id = $payment->get_meta( 'razorpay_subscription_id' );
		if ( empty( $razorpay_order_id ) ) {
			if ( ! empty( $razorpay_subscription_id ) ) {
				$razorpay_subscription = $this->call_api( 'subscription', 'fetch', $razorpay_subscription_id );

				//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				$payment->add_note( '<strong>Razorpay Subscription Response:</strong><br><pre>' . print_r( $razorpay_subscription, true ) . '</pre><br>' );
				$payment->set_status( Statuses::transform_subscription_status( $razorpay_subscription->status ) );
			} else {
				$payment->add_note( 'razorpay_order_id is not set.' );
				$payment->set_status( PaymentStatus::FAILURE );
			}
			return;
		}

		// Fetch payments for this order.
		$razorpay_payments = $this->call_api( 'order', 'fetch', $razorpay_order_id )->payments();

		// No further execution if payment is not attemped yet.
		if ( empty( $razorpay_payments->count ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$action = array_key_exists( 'action', $_GET ) ? \sanitize_text_field( \wp_unslash( $_GET['action'] ) ) : null;
			if ( isset( $action ) && Statuses::CANCELLED === $action ) {
				$payment->set_status( Statuses::transform( Statuses::CANCELLED ) );
				return;
			}

			$payment->add_note( 'Payment not found for order_id: ' . $razorpay_order_id );

			$this->expire_old_payment( $payment );
			return;
		}

		// Get Last payment from array of payments as default Razorpay payment.
		$razorpay_payment = $razorpay_payments->items[0];

		// If order is paid, get the payment which is authorized/captured/refunded.
		foreach ( $razorpay_payments->items as $razorpay_payment_item ) {
			if ( Statuses::CREATED !== $razorpay_payment_item->status && Statuses::FAILED !== $razorpay_payment_item->status ) {
				$razorpay_payment = $razorpay_payment_item;
			}
		}

		$this->update_payment_status( $payment, $razorpay_payment );
	}

	private function update_payment_status( $payment, $razorpay_payment, $razorpay_subscription_id = null ) {
		$razorpay_subscription_id = $payment->get_meta( 'razorpay_subscription_id' );

		$note = '<strong>Razorpay Parameters:</strong>';
		if ( ! empty( $razorpay_subscription_id ) ) {
			$note .= '<br>subscription_id: ' . $razorpay_subscription_id;
		}
		$note .= '<br>payment_id: ' . $razorpay_payment->id;
		$note .= '<br>order_id: ' . $razorpay_payment->order_id;
		if ( ! empty( $razorpay_payment->invoice_id ) ) {
			$note .= '<br>invoice_id: ' . $razorpay_payment->invoice_id;
		}
		$note .= '<br>Status: ' . $razorpay_payment->status;
		if ( ! empty( $razorpay_payment->error_description ) ) {
			$note .= '<br>error_description: ' . $razorpay_payment->error_description;

			$failure_reason = new FailureReason();
			$failure_reason->set_message( $razorpay_payment->error_description );
			$failure_reason->set_code( $razorpay_payment->error_code );
			$payment->set_failure_reason( $failure_reason );
		}

		$payment->add_note( $note );
		$payment->set_status( Statuses::transform( $razorpay_payment->status ) );

		if ( PaymentStatus::SUCCESS === $payment->get_status() ) {
			$this->update_missing_payment_details( $payment, $razorpay_payment );
			$payment->set_transaction_id( $razorpay_payment->id );

			// Schedule invoice upload at Razorpay SFTP Server.
			( InvoiceUploader::get_instance() )->upload_invoice_scheduler( $payment );
		}
	}

	private function create_razorpay_order( Payment $payment, $customer, $payment_currency ) {
		$razorpay_order_id = $payment->get_meta( 'razorpay_order_id' );

		// Return if order id already exists for this payments.
		if ( $razorpay_order_id ) {
			return;
		}

		$amount = $this->get_amount_with_transaction_fees( $payment->get_total_amount(), $this->config->transaction_fees_percentage, $this->config->transaction_fees_fix );

		if ( ! isset( $amount ) ) {
			return;
		}

		// @see: https://razorpay.com/docs/api/orders/create
		$razorpay_order_data = [
			'amount'          => $amount->get_minor_units()->format( 0, '.', '' ),
			'currency'        => $payment_currency,
			'receipt'         => $payment->key . '_' . $payment->get_id(),
			'notes'           => $this->get_notes( $payment ),
			'payment_capture' => 1, // TODO: 1 for auto capture. give admin option to set auto capture. do re-search to see if razorpay has deprecate it or not.
		];

		if ( 'in-import-flow' === $this->config->country ) {
			$customer_address = ( null === $payment->get_shipping_address() ) ? $payment->get_billing_address() : $payment->get_shipping_address();
			$customer_name    = KnitPayUtils::substr_after_trim( $customer->get_name(), 0, 45 );
			$customer_email   = $customer->get_email();
			$customer_phone   = $customer_address->get_phone();

			if ( null === $customer_address ) {
				throw new \Exception( 'Customer address is required.' );
			}

			// Create Customer.
			$razorpay_customer = $this->call_api(
				'customer',
				'create',
				[
					'name'          => $customer_name,
					'email'         => $customer_email,
					'contact'       => $customer_phone,
					'fail_existing' => false,
				]
			);

			$payment->set_meta( 'razorpay_customer_id', $razorpay_customer->id );
			$razorpay_order_data['customer_id'] = $razorpay_customer->id;

			$razorpay_order_data['customer_details'] = [
				'name'             => $customer_name,
				'email'            => $customer_email,
				'contact'          => $customer_phone,
				'shipping_address' => [
					'line1'   => $customer_address->get_line_1(),
					'line2'   => $customer_address->get_line_2(),
					'city'    => $customer_address->get_city(),
					'country' => 'IN' === $customer_address->get_country_code() ? 'IND' : '',
					'state'   => KnitPayUtils::get_state_name( $customer_address->get_region(), $customer_address->get_country() ),
					'zipcode' => (string) $customer_address->get_postal_code(),
				],
			];
		}

		$razorpay_order    = $this->call_api( 'order', 'create', $razorpay_order_data );
		$razorpay_order_id = $razorpay_order['id'];
		self::set_order_id_meta( $payment, $razorpay_order_id );
	}

	private function create_razorpay_subscription( Payment $payment, array $subscriptions, $customer, $payment_currency ) {
		$subscription             = \reset( $subscriptions );
		$razorpay_subscription_id = $subscription->get_meta( 'razorpay_subscription_id' );

		// Return if subscription already exists for this payments.
		if ( $razorpay_subscription_id ) {
			$razorpay_invoices = $this->call_api( 'invoice', 'all', [ 'subscription_id' => $razorpay_subscription_id ] );

			if ( 0 === $razorpay_invoices->count ) {
				return;
			}

			// TODO: Loop all the orders and don't pick order if not success.
			$razorpay_last_order_id = $razorpay_invoices->items[0]->order_id;
			if ( ! is_null( get_pronamic_payment_by_meta( '_pronamic_payment_razorpay_order_id', $razorpay_last_order_id ) ) ) {
				$error_message = 'Payment with this order_id already exsits: ' . $razorpay_last_order_id;
				$payment->add_note( $error_message );
				$payment->set_status( PaymentStatus::FAILURE );
				$subscription->add_note( $error_message );
				return;
			}

			// Set Last Order ID in Payment.
			self::set_order_id_meta( $payment, $razorpay_last_order_id );
			$payment->set_meta( 'razorpay_subscription_id', $razorpay_subscription_id );
			return;
		}

		// Don't create new Razorpay subscription if this subscription has more than 1 payment.
		if ( 1 < count( $subscription->get_payments() ) ) {
			return;
		}

		$payment_periods = $payment->get_periods();
		if ( is_null( $payment_periods ) ) {
			throw new \Exception( 'Periods is not set.' );
		}
		$subscription_period = \reset( $payment_periods );

		$subscription_phase = $subscription_period->get_phase();

		switch ( substr( $subscription_phase->get_interval()->get_specification(), -1, 1 ) ) {
			case 'D':
				$period = 'daily';
				break;
			case 'W':
				$period = 'weekly';
				break;
			case 'M':
				$period = 'monthly';
				break;
			case 'Y':
				$period = 'yearly';
				break;
			default:
				return;
		}

		// @link https://razorpay.com/docs/api/payments/subscriptions/#create-a-plan
		$recuring_amount                                = $this->get_amount_with_transaction_fees( $subscription_phase->get_amount(), $this->config->transaction_fees_percentage, $this->config->transaction_fees_fix );
		$plan_data                                      = [
			'period'   => $period,
			'interval' => substr( $subscription_phase->get_interval()->get_specification(), -2, 1 ),
			'item'     => [
				'name'     => $subscription->get_description(),
				'amount'   => $recuring_amount->get_minor_units()->format( 0, '.', '' ), // FIXME transacion charge
				'currency' => $payment_currency,
			],
			'notes'    => $this->get_notes( $payment ),
		];
		$plan_data['notes']['knitpay_subscription_id']  = $subscription->get_id();
		$plan_data['notes']['knitpay_subscription_key'] = $subscription->get_key();
		$razorpay_plan                                  = $this->call_api( 'plan', 'create', $plan_data );

		$total_count = $this->get_max_count_for_period( $period, $subscription_phase->get_total_periods() );

		// TODO: Bug, total periods not updated in subscription
		// $subscription_phase->set_total_periods($total_count);

		// @link https://razorpay.com/docs/api/payments/subscriptions/#create-a-subscription
		$subscription_data                                      = [
			'plan_id'         => $razorpay_plan->id,
			'total_count'     => $total_count,
			'customer_notify' => 1,
			'notes'           => $this->get_notes( $payment ),
			'notify_info'     => [
				'notify_phone' => $payment->get_billing_address()->get_phone(),
				'notify_email' => $customer->get_email(),
			],
		];
		$subscription_data['notes']['knitpay_subscription_id']  = $subscription->get_id();
		$subscription_data['notes']['knitpay_subscription_key'] = $subscription->get_key();

		$first_payment_amount = $this->get_amount_with_transaction_fees( $payment->get_total_amount(), $this->config->transaction_fees_percentage, $this->config->transaction_fees_fix );
		if ( $subscription_phase->get_start_date()->getTimestamp() > time() ) {
			$upfront_amount                = $first_payment_amount;
			$subscription_data['start_at'] = $subscription_phase->get_start_date()->getTimestamp();
		} else {
			$upfront_amount = $first_payment_amount->subtract( $recuring_amount );
		}
		if ( ! empty( $upfront_amount->get_value() ) && $upfront_amount->get_value() > 0 ) {
			$subscription_data['addons'] = [
				[
					'item' => [
						'name'     => 'Upfront Amount',
						'amount'   => $upfront_amount->get_minor_units()->format( 0, '.', '' ),
						'currency' => $payment_currency,
					],
				],
			];
		}
		$razorpay_subscription = $this->call_api( 'subscription', 'create', $subscription_data );
		$razorpay_invoices     = $this->call_api( 'invoice', 'all', [ 'subscription_id' => $razorpay_subscription->id ] );

		// Save Subscription and Plan ID.
		$subscription->set_meta( 'razorpay_subscription_id', $razorpay_subscription->id );
		$subscription->set_meta( 'razorpay_plan_id,', $razorpay_plan->id );
		$subscription->add_note( 'Razorpay subscription_id: ' . $razorpay_subscription->id );
		$subscription->save();

		self::set_order_id_meta( $payment, $razorpay_invoices->items[0]->order_id );
		$payment->set_meta( 'razorpay_subscription_id', $razorpay_subscription->id );
	}

	private function get_max_count_for_period( $period, $total_count ) {
		switch ( $period ) {
			case 'daily':
				return min( $total_count, 36500 );
			case 'weekly':
				return min( $total_count, 5200 );
			case 'monthly':
				return min( $total_count, 1200 );
			case 'yearly':
				return min( $total_count, 100 );
			default:
				return;
		}
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
		
		$auto_submit = true;
		
		if ( defined( '\KNIT_PAY_DEBUG' ) && \KNIT_PAY_DEBUG ) {
			$auto_submit = false;
		}

		switch ( $this->config->checkout_mode ) {
			case Config::CHECKOUT_STANDARD_MODE:
				$data_json = wp_json_encode( $this->get_output_fields_base( $payment ) );

				require_once 'views/checkout.php';

				$script = '';
				if ( $auto_submit ) {
					$script .= '<script type="text/javascript">document.getElementById("rzp-button1").click();</script>';
				}

				echo $script;
				return;
			case Config::CHECKOUT_HOSTED_MODE:
				$payment->set_action_url( self::HOSTED_CHECKOUT_URL );
				parent::output_form( $payment );
				return;
			default:
				parent::output_form( $payment );
		}
	}

	/**
	 * Get output inputs.
	 *
	 * @param Payment $payment Payment.
	 *
	 * @see Core_Gateway::get_output_fields()
	 *
	 * @return array
	 * @since 2.8.1
	 */
	public function get_output_fields( Payment $payment ) {
		$data = $this->get_output_fields_base( $payment );

		switch ( $this->config->checkout_mode ) {
			/*
			 * Standard hosted checkout not working with subscription.
			case Config::CHECKOUT_STANDARD_MODE:
				$fields = [
					'checkout' => $data,
					'url' => [
						'callback' => $data['callback_url'],
						'cancel' => $data['cancel_url'],
					]
				];

				unset($fields['checkout']['callback_url']);
				unset($fields['checkout']['cancel_url']);

				break;
			 */

			case Config::CHECKOUT_HOSTED_MODE:
				$fields           = $data;
				$fields['key_id'] = $this->config->key_id;

				unset( $fields['key'] );
				unset( $fields['subscription_id'] );

				break;
		}

		return $fields;
	}

	public function get_output_fields_base( Payment $payment ) {
		$razorpay_order_id        = $payment->get_meta( 'razorpay_order_id' );
		$razorpay_subscription_id = $payment->get_meta( 'razorpay_subscription_id' );
		$razorpay_customer_id     = $payment->get_meta( 'razorpay_customer_id' );

		$customer        = $payment->get_customer();
		$billing_address = $payment->get_billing_address();

		$box_title = $this->config->company_name;
		if ( empty( $box_title ) ) {
			$box_title = get_bloginfo( 'name' );
		}
		if ( empty( $box_title ) ) {
			$box_title = 'Pay via Razorpay';
		}

		// @see https://razorpay.com/docs/payments/payment-gateway/web-integration/standard/integration-steps/#123-checkout-options
		// @see https://razorpay.com/docs/payment-gateway/web-integration/hosted/checkout-options/
		$data = [
			'key'          => $this->config->key_id,
			'name'         => $box_title,
			'description'  => $payment->get_description(),
			'prefill'      => [
				'name'   => KnitPayUtils::substr_after_trim( $customer->get_name(), 0, 45 ),
				'email'  => $customer->get_email(),
				'method' => PaymentMethods::prefill_method( $payment->get_payment_method() ),
			],
			'theme'        => [
				// 'color' => '#F37254', // TODO: add option to add custom color.
				'backdrop_color' => 'rgba(0, 0, 0, 0.8)',
			],
			'callback_url' => $payment->get_return_url(),
			'cancel_url'   => add_query_arg( 'action', 'cancelled', $payment->get_return_url() ),
			'timeout'      => 900, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
			'config'       => [
				'display' => [
					'language' => $customer->get_language(),
				],
			],
			'_'            => [
				'integration'         => 'knit-pay',
				'integration_version' => KNITPAY_VERSION,
			],
			'method'       => PaymentMethods::get_methods( $payment->get_payment_method() ),
			// TODO: Add card saving feature if possible.
		];

		if ( 'in-import-flow' === $this->config->country ) {
			if ( ! empty( $razorpay_customer_id ) ) {
				$data['customer_id'] = $razorpay_customer_id;
			}

			$data['notes'] = [
				'invoice_number' => $razorpay_order_id, // Razorpay suggests to pass order id in invoice number on meet.
			];

			// These methods are not supported in Import flow, still visible in checkout.
			$data['method']['wallet']   = false;
			$data['method']['paylater'] = false;
			$data['method']['emi']      = false;
		}

		if ( ! empty( $razorpay_subscription_id ) ) {
			$data['subscription_id'] = $razorpay_subscription_id;
		} else {
			$data['order_id'] = $razorpay_order_id;
		}

		if ( ! empty( $this->config->checkout_image ) ) {
			$data['image'] = $this->config->checkout_image;
		}

		if ( isset( $billing_address ) && ! empty( $billing_address->get_phone() ) ) {
			$data['prefill']['contact'] = $billing_address->get_phone();
		}

		return $data;
	}

	/*
	 * @return $api Api
	 */
	protected function get_razorpay_api() {
		$api = new Api( $this->config->key_id, $this->config->key_secret );
		$api->setAppDetails( 'Knit Pay', KNITPAY_VERSION );

		if ( ! empty( $this->config->access_token ) ) {
			// Refresh Access Token if already expired.
			if ( time() >= $this->config->expires_at ) {
				$integration = new Integration();
				$integration->refresh_access_token( $this->config->config_id );
				$this->config = $integration->get_config( $this->config->config_id );
			}

			$api->setHeader( 'Authorization', 'Bearer ' . $this->config->access_token );
		}

		return $api;
	}

	private function call_api( $entity, $method = '', $args = [], $api_client = null, $allow_retry = true ) {
		try {
			if ( null === $api_client ) {
				$api_client = $this->get_razorpay_api();
			}

			$className = 'Razorpay\\Api\\' . ucwords( $entity );
			if ( class_exists( $className ) ) {
				$entity_obj = new $className();
				$response   = $entity_obj->$method( $args );
			} else {
				// For custom endpoints not available in SDK, use Request class directly.
				$request  = new \Razorpay\Api\Request();
				$response = $request->request( 'GET', $entity, $args );
			}

			return $response;
		} catch ( Error $e ) {
			if ( 401 === $e->getHttpStatusCode() && ! empty( $this->config->access_token ) && $allow_retry ) {
				// Refresh access token.
				$integration = new Integration();
				$integration->refresh_access_token( $this->config->config_id );
				$this->config = $integration->get_config( $this->config->config_id );

				// Retry request.
				return $this->call_api( $entity, $method, $args, null, false );
			}

			throw $e;
		}
	}

	private function get_notes( Payment $payment ) {
		$source = $payment->get_source();
		if ( 'woocommerce' === $source ) {
			$source = 'wc';
		}
		$notes = [
			'knitpay_payment_id'     => $payment->get_id(),
			'knitpay_extension'      => $source,
			'knitpay_source_id'      => $payment->get_source_id(),
			'knitpay_order_id'       => $payment->get_order_id(),
			'knitpay_version'        => KNITPAY_VERSION,
			'php_version'            => PHP_VERSION,
			'website_url'            => home_url( '/' ),
			'razorpay_checkout_mode' => $this->config->checkout_mode,
		];

		$customer      = $payment->get_customer();
		$customer_name = KnitPayUtils::substr_after_trim( $customer->get_name(), 0, 45 );
		if ( ! empty( $customer_name ) ) {
			$notes = [
				'customer_name' => $customer_name,
			] + $notes;
		}

		return $notes;
	}

	/**
	 * Create refund.
	 *
	 * @param Refund $refund Refund.
	 * @return void
	 * @throws \Exception Throws exception on unknown resource type.
	 */
	public function create_refund( Refund $refund ) {
		$amount         = $refund->get_amount();
		$transaction_id = $refund->get_payment()->get_transaction_id();
		$description    = $refund->get_description();

		$razorpay_payment = $this->call_api( 'payment', 'fetch', $transaction_id );
		$refund           = $razorpay_payment->refund(
			[
				'amount' => $amount->get_minor_units()->format( 0, '.', '' ),
				'notes'  => [
					'Comment'         => $description,
					'knitpay_version' => KNITPAY_VERSION,
					'website_url'     => home_url( '/' ),
				],
			]
		);
		$refund->psp_id   = $refund['id'];
	}

	private function expire_old_payment( $payment ) {
		// Make payment status as expired for payment older than 1 day.
		if ( DAY_IN_SECONDS < time() - $payment->get_date()->getTimestamp() && $this->config->expire_old_payments ) {
			$payment->set_status( PaymentStatus::EXPIRED );
		}
	}

	private function update_missing_payment_details( Payment $payment, $razorpay_payment ) {
		$customer = $payment->get_customer();
		$address  = $payment->get_billing_address();
		if ( ! isset( $address ) ) {
			$address = new Address();
		}

		if ( empty( $customer->get_email() ) ) {
			$address->set_email( $razorpay_payment->email );
			$customer->set_email( $razorpay_payment->email );
			$payment->email = $razorpay_payment->email;

			$user = get_user_by( 'email', $razorpay_payment->email );
			if ( false !== $user ) {
				$customer->set_user_id( $user->ID );
			}
		}

		if ( empty( $customer->get_phone() ) ) {
			$address->set_phone( $razorpay_payment->contact );
			$customer->set_phone( $razorpay_payment->contact );
		}

		$payment->set_customer( $customer );
		$payment->set_billing_address( $address );
	}

	public function get_balance() {
		try {
			return $this->call_api( 'balance' );
		} catch ( \Exception $e ) {
			return [];
		}
	}

	private function get_amount_with_transaction_fees( Money $amount, $transaction_fees_percentage, $transaction_fees_fix ) {
		if ( empty( $transaction_fees_percentage ) && empty( $transaction_fees_fix ) ) {
			return $amount;
		}

		try {
			$transaction_fees_percentage = Number::from_string( $transaction_fees_percentage );
			if ( 59 < $transaction_fees_percentage->get_value() ) {
				throw new \Exception( 'The maximum allowed Transaction Fees Percentage is 59.' );
			}
			$transaction_fees_fix_amount = new Money( $transaction_fees_fix, $amount->get_currency() );
		} catch ( \Exception $e ) {
			throw new \Exception( 'Invalid Transaction Fees. ' . $e->getMessage() );
		}

		$transaction_fees_percentage_divide = ( new Number( 100 ) )->subtract( $transaction_fees_percentage )->divide( new Number( 100 ) );
		$amount                             = $amount->divide( $transaction_fees_percentage_divide ); // Amount after addition Transaction Fees Percentage.

		$amount = $amount->add( $transaction_fees_fix_amount ); // Amount after addition of Fix Transaction Fees.

		return $amount;
	}

	private static function set_order_id_meta( $payment, $order_id ) {
		$payment->add_note( 'Razorpay order_id: ' . $order_id );
		update_post_meta( $payment->get_id(), '_pronamic_payment_razorpay_order_id', $order_id );
	}

	/**
	 * Called by integration class when subscription status changes. Updates subscription status at Razorpay.
	 *
	 * @param Subscription $subscription    Subscription.
	 * @param bool         $can_redirect    Flag to indicate if redirect is allowed after the subscription update.
	 * @param null|string  $previous_status Previous [subscription status](https://github.com/pronamic/wp-pronamic-pay/wiki#subscription-status).
	 * @param null|string  $updated_status  Updated [subscription status](https://github.com/pronamic/wp-pronamic-pay/wiki#subscription-status).
	 */
	public function subscription_status_update( $subscription, $can_redirect, $previous_status, $updated_status ) {
		$razorpay_subscription_id = $subscription->get_meta( 'razorpay_subscription_id' );
		
		if ( empty( $razorpay_subscription_id ) ) {
			return;
		}

		$razorpay_subscription = $this->call_api( 'subscription', 'fetch', $razorpay_subscription_id );

		switch ( $updated_status ) {
			case SubscriptionStatus::CANCELLED:
				$razorpay_subscription->pause( [ 'pause_at' => 'now' ] );
				break;
			case SubscriptionStatus::ACTIVE:
				$razorpay_subscription->resume( [ 'resume_at' => 'now' ] );
				break;
		}
	}
}
