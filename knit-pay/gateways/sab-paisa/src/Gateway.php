<?php
namespace KnitPay\Gateways\SabPaisa;

use Pronamic\WordPress\Pay\Core\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Payments\FailureReason;
use Pronamic\WordPress\Pay\Payments\Payment;
use Exception;

/**
 * Title: Sab Paisa Gateway
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 8.95.0.0
 * @since 8.95.0.0
 */
class Gateway extends Core_Gateway {
	private $config;
	private $api;

	/**
	 * Initializes an Sab Paisa gateway
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
		];

		$this->api = new API( $config, $this->get_mode() );
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

		$payment->set_action_url( $this->api->get_endpoint_url() );
	}

	/**
	 * Redirect via HTML.
	 *
	 * @see Core_Gateway::get_output_fields()
	 *
	 * @param Payment $payment
	 *            Payment.
	 */
	public function get_output_fields( Payment $payment ) {
		$client_code = $this->config->client_code;
		$username    = $this->config->username;
		$password    = $this->config->password;

		$customer        = $payment->get_customer();
		$billing_address = $payment->get_billing_address();

		$enc_data_array = [
			'clientCode'        => $client_code,
			'transUserName'     => $username,
			'transUserPassword' => $password,
			'payerName'         => substr( trim( ( html_entity_decode( $customer->get_name(), ENT_QUOTES, 'UTF-8' ) ) ), 0, 50 ),
			'payerMobile'       => $billing_address->get_phone(),
			'payerEmail'        => $customer->get_email(),
			'clientTxnId'       => $payment->get_transaction_id(),
			'amount'            => $payment->get_total_amount()->number_format( null, '.', '' ),
			'amountType'        => $payment->get_total_amount()->get_currency()->get_alphabetic_code(),
			'channelId'         => 'W',
			'callbackUrl'       => $this->get_return_url( $payment ),
		];

		if ( isset( $billing_address ) ) {
			$enc_data_array['payerMobile'] = $billing_address->get_phone();
		}
		
		return $this->api->get_encrypted_data_array( $enc_data_array );
	}

	/**
	 * Update status of the specified payment.
	 *
	 * @param Payment $payment Payment.
	 * @throws Exception If error occurs while updating payment status.
	 */
	public function update_status( Payment $payment ) {

		$transaction_status = $this->api->get_transaction_status( $payment->get_transaction_id() );

		$payment_status = Statuses::transform( $transaction_status['status'] );

		if ( Statuses::SUCCESS === $payment_status ) {
			$payment->set_transaction_id( $transaction_status['sabpaisaTxnId'] );
		} elseif ( isset( $transaction_status['bankMessage'] ) ) {
			$failure_reason = new FailureReason();
			$failure_reason->set_message( $transaction_status['bankMessage'] );
			$payment->set_failure_reason( $failure_reason );
		}
		$payment->set_status( $payment_status );
		$payment->add_note( '<strong>Sab Paisa Response:</strong><br><pre>' . print_r( $transaction_status, true ) . '</pre><br>' );
	}

	private function get_return_url( Payment $payment ) {
		$return_url = remove_query_arg( [ 'key', 'payment' ], $payment->get_return_url() );
		return add_query_arg( 'kp_sab_paisa_payment_id', $payment->get_id(), $return_url );
	}
}
