<?php
namespace KnitPay\Gateways\MyFatoorah;

use Pronamic\WordPress\Pay\Core\Gateway as Core_Gateway;
use Exception;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;
use Pronamic\WordPress\Pay\Core\PaymentMethods;
use KnitPay\Utils as KnitPayUtils;

require_once 'lib/API.php';

/**
 * Title: MyFatoorah Gateway
 * Copyright: 2020-2025 Knit Pay
 *
 * @author Knit Pay
 * @version 1.0.0
 * @since 6.63.0.0
 */
class Gateway extends Core_Gateway {
	/** @var bool */
	private $test_mode;

	/** @var API */
	private $api;

	/**
	 * Constructs and initializes an MyFatoorah gateway
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

		$this->test_mode = false;
		if ( self::MODE_TEST === $config->mode ) {
			$this->test_mode = true;
		}

		$this->api = new API( $config->api_token_key, $this->test_mode );
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
		$payment_data = $this->api->send_payment( $this->get_payment_data( $payment ) );

		$payment->set_transaction_id( $payment_data->InvoiceId );
		$payment->set_action_url( $payment_data->InvoiceURL );
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
		$customer = $payment->get_customer();

		$invoice_value        = $payment->get_total_amount()->number_format( null, '.', '' );
		$display_currency_iso = $payment->get_total_amount()->get_currency()->get_alphabetic_code();
		$customer_name        = KnitPayUtils::substr_after_trim( html_entity_decode( $customer->get_name(), ENT_QUOTES, 'UTF-8' ), 0, 20 );
		$customer_email       = $customer->get_email();
		$return_url           = $payment->get_return_url();
		
		return [
			'InvoiceValue'       => $invoice_value,
			'CallBackUrl'        => $return_url,
			'ErrorUrl'           => $return_url,
			'CustomerName'       => $customer_name,
			'DisplayCurrencyIso' => $display_currency_iso,
			'CustomerEmail'      => $customer_email,
			'CustomerReference'  => $payment->key . '_' . $payment->get_id(),
			'UserDefinedField'   => "Order ID: {$payment->get_order_id()}",
			'SourceInfo'         => 'Knit Pay: ' . KNITPAY_VERSION,
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

		$payment_status = $this->api->get_payment_status( $payment->get_transaction_id() );

		if ( isset( $payment_status->InvoiceStatus ) ) {
			$payment->set_status( Statuses::transform( $payment_status->InvoiceStatus ) );
			$payment->add_note( '<strong>MyFatoorah Payment Status:</strong><br><pre>' . print_r( $payment_status, true ) . '</pre><br>' );
		}
	}
}
