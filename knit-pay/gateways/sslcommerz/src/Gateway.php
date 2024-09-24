<?php
namespace KnitPay\Gateways\SSLCommerz;

use Pronamic\WordPress\Pay\Core\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Payments\FailureReason;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;
use Exception;

/**
 * Title: SSLCommerz Gateway
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 8.80.0.0
 * @since 8.80.0.0
 */
class Gateway extends Core_Gateway {
	private $config;
	private $api;

	/**
	 * Initializes an SSLCommerz gateway
	 *
	 * @param Config $config
	 *            Config.
	 */
	public function init( Config $config ) {
		$this->config = $config;

		$this->set_method( self::METHOD_HTTP_REDIRECT );

		// Supported features.
		$this->supports = [
			'payment_status_request',
		];

		$test_mode = false;
		if ( self::MODE_TEST === $config->mode ) {
			$test_mode = true;
		}

		$this->api = new API( $config, $test_mode );
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

		$gateway_page_url = $this->api->create_session( $this->get_payment_data( $payment ) );

		$payment->set_action_url( $gateway_page_url );
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
		$customer_phone  = '01711111111';
		if ( ! empty( $billing_address ) && ! empty( $billing_address->get_phone() ) ) {
			$customer_phone = $billing_address->get_phone();
		}

		$customer_name  = substr( trim( ( html_entity_decode( $customer->get_name(), ENT_QUOTES, 'UTF-8' ) ) ), 0, 40 );
		$customer_email = $customer->get_email();
		$return_url     = $payment->get_return_url();

		$post_data = [
			'total_amount'     => $payment->get_total_amount()->number_format( null, '.', '' ),
			'currency'         => $payment->get_total_amount()->get_currency()->get_alphabetic_code(),
			'tran_id'          => $payment->get_transaction_id(),
			'product_category' => 'ecommerce',
			'success_url'      => $return_url,
			'fail_url'         => $return_url,
			'cancel_url'       => $return_url,
			'ipn_url'          => $return_url,
			'cus_name'         => $customer_name,
			'cus_email'        => $customer_email,
			'cus_add1'         => $billing_address->get_line_1() ?? '',
			'cus_add2'         => $billing_address->get_line_2() ?? '',
			'cus_city'         => $billing_address->get_city() ?? '',
			'cus_state'        => $billing_address->get_region() ?? '',
			'cus_postcode'     => $billing_address->get_postal_code() ?? '',
			'cus_country'      => $billing_address->get_country_code() ?? '',
			'cus_phone'        => $customer_phone,
			'shipping_method'  => 'NO',
			'product_name'     => $payment->get_description(),
			'product_profile'  => 'general',
		];

		return $post_data;
	}

	/**
	 * Update status of the specified payment.
	 *
	 * @param Payment $payment
	 *            Payment.
	 */
	public function update_status( Payment $payment ) {
		try {
			$transaction_status = $this->api->get_transaction_status( $payment->get_transaction_id() );
		} catch ( Exception $e ) {
			throw new Exception( $e->getMessage() );
		}

		$payment_status = Statuses::transform( $transaction_status->status );
		if ( PaymentStatus::SUCCESS !== $payment_status ) {
			$failure_reason = new FailureReason();
			$failure_reason->set_message( $transaction_status->error );
			$payment->set_failure_reason( $failure_reason );
		}
		$payment->set_status( $payment_status );
		$payment->add_note( '<strong>SSLCommerz Response:</strong><br><pre>' . print_r( $transaction_status, true ) . '</pre><br>' );
	}
}
