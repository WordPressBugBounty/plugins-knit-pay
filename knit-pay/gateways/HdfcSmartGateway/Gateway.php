<?php
namespace KnitPay\Gateways\HdfcSmartGateway;

use Pronamic\WordPress\Pay\Core\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Core\PaymentMethod;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;
use KnitPay\Utils as KnitPayUtils;

/**
 * Title: HDFC Smart Gateway Gateway
 * Copyright: 2020-2026 Knit Pay
 *
 * @author Knit Pay
 * @version 8.93.0.0
 * @since 8.93.0.0
 */
class Gateway extends Core_Gateway {
	/**
	 * @var Config
	 */
	private $config;

	/**
	 * @var API
	 */
	private $api_client;

	/**
	 * Constructs and initializes an HDFC Smart Gateway gateway
	 *
	 * @param Config $config
	 *            Config.
	 */
	public function init( Config $config ) {
		$this->set_method( self::METHOD_HTTP_REDIRECT );

		// Supported features.
		$this->supports = [
			'payment_status_request',
		];

		$this->config = $config;

		// Client.
		$this->api_client = new API( $config );
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
		$transaction_id = $payment->key . '_' . $payment->get_source_id();
		$transaction_id = KnitPayUtils::substr_after_trim( $transaction_id, -21 );
		$transaction_id = ltrim( $transaction_id, 'pay_' );
		$payment->set_transaction_id( $transaction_id );

		$payment_link = $this->api_client->create_session( $this->get_payment_data( $payment ) );

		$payment->set_action_url( $payment_link );
	}

	/**
	 * Get data json string.
	 *
	 * @param Payment $payment
	 *            Payment.
	 * @see https://smartgateway.hdfcbank.com/docs/smartgateway-api-ref-basicauth/docs/apis/session
	 *
	 * @return string
	 */
	public function get_payment_data( Payment $payment ) {
		$total_amount = $payment->get_total_amount();
		$customer     = $payment->get_customer();
		
		$first_name = '';
		$last_name  = '';
		if ( null !== $customer->get_name() ) {
			$first_name = $customer->get_name()->get_first_name();
			$last_name  = $customer->get_name()->get_last_name();
		}

		$phone           = '';
		$billing_address = $payment->get_billing_address();
		if ( null !== $billing_address && ! empty( $billing_address->get_phone() ) ) {
			$phone = $billing_address->get_phone();
		}

		$cust_id = $customer->get_email();
		if ( empty( $cust_id ) ) {
			$cust_id = 'CUST_' . $payment->get_id();
		}

		$return_url = $payment->get_return_url();
		$return_url = add_query_arg( 'kp_hdfc_smart_gateway_payment_id', $payment->get_id(), $return_url );

		$data = [
			'order_id'               => $payment->get_transaction_id(),
			'amount'                 => $total_amount->number_format( null, '.', '' ),
			'customer_id'            => $cust_id,
			'customer_email'         => $customer->get_email(),
			'customer_phone'         => $phone,
			'payment_page_client_id' => $this->config->client_id,
			'action'                 => 'paymentPage',
			'return_url'             => $return_url,
			'description'            => $payment->get_description(),
			'first_name'             => $first_name,
			'last_name'              => $last_name,
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
			$transaction_status = $this->api_client->get_order_status( $payment->get_transaction_id() );

			$note = '<strong>HDFC Transaction Status:</strong><br><pre>' . print_r( $transaction_status, true ) . '</pre>';
			$payment->add_note( $note );
		if ( isset( $transaction_status->status ) ) {
			$payment->set_status( Statuses::transform( $transaction_status->status ) );
		}
	}
}
