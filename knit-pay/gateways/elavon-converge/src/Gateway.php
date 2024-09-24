<?php
namespace KnitPay\Gateways\ElavonConverge;

use Pronamic\WordPress\Pay\Core\Gateway as Core_Gateway;
use Exception;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;
use WP_Error;

require_once 'lib/API.php';


/**
 * Title: Elavon Converge Gateway
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 1.0.0
 * @since 4.3.0
 */
class Gateway extends Core_Gateway {
	private $config;
	private $test_mode;

	const NAME = 'elavon-converge';

	/**
	 * Initializes an Elavon Converge gateway
	 *
	 * @param Config $config
	 *            Config.
	 */
	public function init( Config $config ) {
		$this->config = $config;

		$this->set_method( self::METHOD_HTTP_REDIRECT );

		$this->test_mode = 0;
		if ( self::MODE_TEST === $config->mode ) {
			$this->test_mode = 1;
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
		$api = new API( $this->config, $this->test_mode );

		$payment->set_transaction_id( $payment->key . '_' . $payment->get_id() );
		$session_token = $api->get_session_token( $this->get_payment_data( $payment ) );

		$payment->set_action_url( $api->api_endpoint . '?ssl_txn_auth_token=' . $session_token );
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
		$txn_id   = $payment->get_transaction_id();
		$customer = $payment->get_customer();

		$data = [
			'ssl_amount'          => $payment->get_total_amount()->number_format( null, '.', '' ),
			'ssl_merchant_txn_id' => $payment->get_id(),
			'ssl_invoice_number'  => $txn_id,
			'ssl_description'     => $payment->get_description(),
		];

		if ( $this->config->multi_currency_enabled ) {
			$data['ssl_transaction_currency'] = $payment->get_total_amount()->get_currency()->get_alphabetic_code();
		}

		if ( isset( $customer ) && null !== $customer->get_name() ) {
			$data['ssl_first_name'] = $customer->get_name()->get_first_name();
			$data['ssl_last_name']  = $customer->get_name()->get_last_name();
		}
		// TODO: pass remaining parameters. refer https://developer.elavon.com/docs/converge/1.0.0/integration-guide/api-reference/supported-transaction-input-fields

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

		$ssl_txn_id = filter_input( INPUT_GET, 'ssl_txn_id', FILTER_SANITIZE_STRING );
		if ( empty( $ssl_txn_id ) ) {
			return;
		}

		$api = new API( $this->config, $this->test_mode );


		$transaction_details = $api->get_transaction_details( $ssl_txn_id );

		if ( isset( $transaction_details['ssl_trans_status'] ) ) {
			$payment->set_status( Statuses::transform( $transaction_details['ssl_trans_status'] ) );
			$payment->set_transaction_id( $ssl_txn_id );

			$note = '<strong>Elavon Converge Transaction Details:</strong><br>' . print_r( $transaction_details, true );

			$payment->add_note( $note );
		}
	}
}
