<?php
namespace KnitPay\Gateways\GetEpay;

use KnitPay\Utils as KnitPayUtils;
use KnitPay\Gateways\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;

/**
 * Title: Get ePay Gateway
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 8.87.0.0
 * @since   8.87.0.0
 */
class Gateway extends Core_Gateway {
	private $config;

	private $env;
	
	private $client;

	/**
	 * Initializes an Get ePay gateway
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
		
		$test_mode = self::MODE_TEST === $config->mode;
		
		$this->client = new Client( $config, $test_mode );
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
		$payment_data = $this->get_payment_data( $payment );
		$invoice      = $this->client->generate_invoice( $payment_data );
		
		$payment->set_action_url( $invoice->paymentUrl );
		$payment->set_transaction_id( $invoice->paymentId );
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
		
		$amount           = $payment->get_total_amount()->number_format( null, '.', '' );
		$payment_currency = $payment->get_total_amount()->get_currency()->get_alphabetic_code();
		
		$phone = '';
		if ( null !== $billing_address && ! empty( $billing_address->get_phone() ) ) {
			$phone = $billing_address->get_phone();
		}
		
		$email          = $customer->get_email();
		$transaction_id = $payment->key . '_' . $payment->get_id();

		return [
			'amount'                => $amount,
			'merchantTransactionId' => $transaction_id,
			'transactionDate'       => $payment->get_date()->format_i18n(),
			'udf1'                  => $phone,
			'udf2'                  => $email,
			'udf3'                  => KnitPayUtils::substr_after_trim( $customer->get_name(), 0, 75 ),
			'udf4'                  => '',
			'udf5'                  => '',
			'udf6'                  => '',
			'udf7'                  => '',
			'udf8'                  => '',
			'udf9'                  => '',
			'udf10'                 => '',
			'ru'                    => $payment->get_return_url(),
			'callbackUrl'           => '',
			'currency'              => $payment_currency,
			'paymentMode'           => 'ALL',
			'bankId'                => '',
			'txnType'               => 'single',
			'productType'           => 'IPG',
			'txnNote'               => $payment->get_description(),
		];
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
		
		if ( filter_has_var( INPUT_POST, 'response' ) ) {
			$invoice_status = \sanitize_text_field( $_POST['response'] );
			$invoice_status = $this->client->decrypt( $invoice_status );
			$invoice_status = json_decode( $invoice_status );
		} else {
			$invoice_status = $this->client->get_invoice_status( [ 'paymentId' => $payment->get_transaction_id() ] );
		}

		if ( $invoice_status->getepayTxnId !== $payment->get_transaction_id() ) {
			return;
		}
		
		$payment->set_status( Statuses::transform( $invoice_status->txnStatus ) );
		$payment->add_note( '<strong>Get ePay Response:</strong><br><pre>' . print_r( $invoice_status, true ) . '</pre><br>' );
	}
}
