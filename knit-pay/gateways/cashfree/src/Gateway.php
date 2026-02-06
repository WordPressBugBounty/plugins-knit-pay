<?php
namespace KnitPay\Gateways\Cashfree;

use Pronamic\WordPress\Pay\Core\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Core\PaymentMethod;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;
use Exception;
use Pronamic\WordPress\Pay\Payments\FailureReason;
use Pronamic\WordPress\Pay\Refunds\Refund;
use KnitPay\Utils as KnitPayUtils;
use Pronamic\WordPress\Pay\Core\PaymentMethodsCollection;

/**
 * Title: Cashfree Gateway
 * Copyright: 2020-2026 Knit Pay
 *
 * @author Knit Pay
 * @version 8.91.0.0
 * @since 2.4
 */
class Gateway extends Core_Gateway {
	private $test_mode;
	private $config;

	const NAME = 'cashfree';

	/**
	 * Initializes an Cashfree gateway
	 *
	 * @param Config $config
	 *            Config.
	 */
	public function init( Config $config ) {
		$this->set_method( self::METHOD_HTML_FORM );

		// Supported features.
		$this->supports = [
			'payment_status_request',
			'refunds',
		];

		$this->test_mode = 0;
		if ( self::MODE_TEST === $config->mode ) {
			$this->test_mode = 1;
		}

		$this->config = $config;

		$this->register_payment_methods();
	}

	private function register_payment_methods() {
		$this->register_payment_method( new PaymentMethod( PaymentMethods::CASHFREE ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::CREDIT_CARD ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::DEBIT_CARD ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::CARD ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::NET_BANKING ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::UPI ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::PAYPAL ) );
	}

	/**
	 * Get payment methods.
	 *
	 * @param array $args Query arguments.
	 * @return PaymentMethodsCollection
	 */
	public function get_payment_methods( array $args = [] ): PaymentMethodsCollection {
		$cache_key = 'knit_pay_cashfree_payment_methods_' . $this->config->config_id;

		$methods = \get_transient( $cache_key );

		if ( false === $methods ) {
			// @see https://www.cashfree.com/docs/api-reference/payments/latest/eligibility/get-eligible-payment-methods
			try {
				$api_client = new API( $this->config, $this->test_mode );
				$methods    = $api_client->get_eligible_payment_methods();
				\set_transient( $cache_key, $methods, \DAY_IN_SECONDS );
			} catch ( \Exception $e ) {
				// Handle exception
				$methods = [];
			}
		}

		$this->get_payment_method( PaymentMethods::CASHFREE )->set_status( 'active' );

		foreach ( $methods as  $cashfree_method ) {
			if ( ! is_object( $cashfree_method ) ) {
				continue;
			}

			$method_id = $cashfree_method->entity_value;

			switch ( $method_id ) {
				case 'netbanking':
					$method_id = PaymentMethods::NET_BANKING;
					break;
				default:
			}

			$method = $this->get_payment_method( $method_id );
			if ( null === $method ) {
				continue;
			}
			$method->set_status( $cashfree_method->eligibility ? 'active' : 'inactive' );
		}

		if ( 'active' === $this->get_payment_method( PaymentMethods::DEBIT_CARD )->get_status() || 'active' === $this->get_payment_method( PaymentMethods::CREDIT_CARD )->get_status() ) {
			$method = $this->get_payment_method( PaymentMethods::CARD );
			$method->set_status( 'active' );
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
		if ( ! empty( $payment->get_meta( 'cashfree_payment_session_id' ) ) ) {
			return;
		}

		$cashfree_order_id = $payment->key . '_' . $payment->get_id();
		$payment->set_transaction_id( $cashfree_order_id );

		$api_client                  = new API( $this->config, $this->test_mode );
		$cashfree_payment_session_id = $api_client->create_order( $this->get_payment_data( $payment ) );

		$payment->add_note( 'Cashfree payment_session_id: ' . $cashfree_payment_session_id );
		$payment->set_meta( 'cashfree_payment_session_id', $cashfree_payment_session_id );
		
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
		$customer        = $payment->get_customer();
		$billing_address = $payment->get_billing_address();
		$customer_phone  = $this->config->default_customer_phone;
		if ( ! empty( $billing_address ) && ! empty( $billing_address->get_phone() ) ) {
			$customer_phone = $this->format_phone_number( $billing_address->get_phone() );
		}
		if ( empty( $customer_phone ) ) {
			$customer_phone = '9999999999'; // Cashfree support suggested to pass 9999999999 as default phone.
		}

		$order_id       = $payment->get_transaction_id();
		$order_amount   = $payment->get_total_amount()->number_format( null, '.', '' );
		$order_currency = $payment->get_total_amount()->get_currency()->get_alphabetic_code();
		$order_note     = KnitPayUtils::substr_after_trim( $payment->get_description(), 0, 250 );
		$customer_name  = KnitPayUtils::substr_after_trim( $customer->get_name(), 0, 20 );
		$customer_email = $customer->get_email();
		$return_url     = add_query_arg( 'order_id', '{order_id}', $payment->get_return_url() );
		$notify_url     = add_query_arg( 'kp_cashfree_webhook', '', home_url( '/' ) );
		$cust_id        = 'CUST_' . $payment->get_order_id() . '_' . $payment->get_id();

		// @see https://www.cashfree.com/docs/api-reference/payments/previous/v2022-09-01/orders/create-order
		return [
			'order_id'         => $order_id,
			'order_amount'     => $order_amount,
			'order_currency'   => $order_currency,
			'customer_details' => [
				'customer_id'    => $cust_id,
				'customer_name'  => $customer_name,
				'customer_email' => $customer_email,
				'customer_phone' => $customer_phone,
			],
			'order_meta'       => [
				'return_url'      => $return_url,
				'notify_url'      => $notify_url,
				'payment_methods' => PaymentMethods::transform( $payment->get_payment_method() ),
			],
			'order_note'       => $order_note,
			'order_tags'       => $this->get_order_tags( $payment ),
		];
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

		$html = '';
		if ( $this->test_mode ) {
			$html .= '<script src="https://sdk.cashfree.com/js/ui/2.0.0/cashfree.sandbox.js"></script>';
		} else {
			$html .= '<script src="https://sdk.cashfree.com/js/ui/2.0.0/cashfree.prod.js"></script>';
		}

		$html .= '<script>';
		$html .= "const cashfree = new Cashfree('{$payment->get_meta('cashfree_payment_session_id')}');";

		if ( ! ( defined( '\KNIT_PAY_DEBUG' ) && \KNIT_PAY_DEBUG ) ) {
			$html .= 'cashfree.redirect();';
		}

		$html .= 'document.getElementById("pronamic_ideal_form").onsubmit = function(e){e.preventDefault();cashfree.redirect();}</script>';

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

		$api_client    = new API( $this->config, $this->test_mode );
		$order_details = $api_client->get_order_details( $payment->get_transaction_id() );
		if ( knit_pay_plugin()->is_debug_mode() ) {
			//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			$payment->add_note( '<strong>Cashfree Order Details:</strong><br><pre>' . print_r( $order_details, true ) . '</pre><br>' );
		}
		
		// @see https://docs.cashfree.com/reference/getpaymentsfororder
		$order_payments = $api_client->get_order_data( $order_details->payments );
		if ( knit_pay_plugin()->is_debug_mode() ) {
			//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			$payment->add_note( '<strong>Cashfree Order Payments:</strong><br><pre>' . print_r( $order_payments, true ) . '</pre><br>' );
		}
		
		if ( empty( $order_payments ) ) {
			if ( filter_has_var( INPUT_GET, 'order_id' ) ) {
				$payment->set_status( PaymentStatus::CANCELLED );
				return;
			}

			// Check Status from Order Details if Payments not available.
			$payment->set_status( Statuses::transform( $order_details->order_status ) );
			return;
		}
		
		$current_payment = reset( $order_payments );
		//phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		$payment->add_note( '<strong>Cashfree Current Payment:</strong><br><pre>' . print_r( $current_payment, true ) . '</pre><br>' );

		if ( isset( $current_payment->error_details ) ) {
			$failure_reason = new FailureReason();
			$failure_reason->set_message( $current_payment->error_details->error_description_raw );
			$failure_reason->set_code( $current_payment->error_details->error_code_raw );
			$payment->set_failure_reason( $failure_reason );
		}

		if ( isset( $current_payment->payment_status ) ) {
			$payment_status = $current_payment->payment_status;

			$payment->set_status( Statuses::transform( $payment_status ) );
		}
	}

	private function format_phone_number( $customer_phone ) {
		// Remove - or whitespace.
		$customer_phone = preg_replace( '/[\s\-]+/', '', $customer_phone );

		// Remove 0 from beginning of phone number.
		$customer_phone = 10 < strlen( $customer_phone ) ? ltrim( $customer_phone, '0' ) : $customer_phone;

		return $customer_phone;
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

		// @see https://docs.cashfree.com/reference/pgordercreaterefund
		$refund_data = [
			'order_id'      => $transaction_id,
			'refund_amount' => $amount->number_format( null, '.', '' ),
			'refund_id'     => uniqid( 'refund_' ),
			'refund_note'   => $description,
		];

		$api_client         = new API( $this->config, $this->test_mode );
		$cashfree_refund_id = $api_client->create_refund( $refund_data );

		$refund->psp_id = $cashfree_refund_id;
	}

	private function get_order_tags( Payment $payment ) {
		$source = $payment->get_source();
		if ( 'woocommerce' === $source ) {
			$source = 'wc';
		}

		$notes = [
			'1_knitpay_payment_id' => strval( $payment->get_id() ),
			'2_knitpay_extension'  => $source,
			'3_knitpay_source_id'  => strval( $payment->get_source_id() ),
			'4_knitpay_order_id'   => strval( $payment->get_order_id() ),
			'5_knitpay_version'    => KNITPAY_VERSION,
			'6_php_version'        => PHP_VERSION,
			'7_website_url'        => home_url( '/' ),
		];

		$notes['8_auth_type'] = 'Bearer';
		if ( empty( $this->config->access_token ) ) {
			$notes['8_auth_type'] = 'Basic';
		}

		return $notes;
	}
}
