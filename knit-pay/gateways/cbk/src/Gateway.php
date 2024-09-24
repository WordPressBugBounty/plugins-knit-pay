<?php
namespace KnitPay\Gateways\CBK;

use Pronamic\WordPress\Pay\Core\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;


/**
 * Title: CBK (Commercial Bank of Kuwait - Al-Tijari) Gateway
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 6.68.0.0
 * @since 6.68.0.0
 */
class Gateway extends Core_Gateway {
	private $config;
	private $api;
	private $access_token;
	/**
	 * Initializes an CBK (Commercial Bank of Kuwait - Al-Tijari) gateway
	 *
	 * @param Config $config
	 *            Config.
	 */
	public function init( Config $config ) {
		$this->set_method( self::METHOD_HTML_FORM );
		
		// Supported features.
		$this->supports = [
			'payment_status_request',
		];
		
		$this->config = $config;
		$this->api    = new API( $config );
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
		$this->access_token = $this->api->get_access_token();
		
		$payment->set_transaction_id( uniqid() . $payment->get_id() );
		$payment->save();
		
		$payment->set_action_url( API::get_endpoint( $this->config->mode ) . '/ePay/pg/epay?_v=' . $this->access_token );
	}

	/**
	 * Get output inputs.
	 *
	 * @see Core_Gateway::get_output_fields()
	 *
	 * @param Payment $payment
	 *            Payment.
	 *
	 * @return array
	 */
	public function get_output_fields( Payment $payment ) {
		$encrypt_key  = $this->config->encrypt_key;
		$access_token = $this->access_token;
		
		$customer = $payment->get_customer();
		$language = $customer->get_language();
		
		$data = [
			'tij_MerchantEncryptCode'     => $encrypt_key,
			'tij_MerchAuthKeyApi'         => $access_token,
			'tij_MerchantPaymentLang'     => $language,
			'tij_MerchantPaymentAmount'   => $payment->get_total_amount()->number_format( null, '.', '' ),
			'tij_MerchantPaymentTrack'    => $payment->get_transaction_id(),
			'tij_MerchantPaymentRef'      => $payment->get_description(),
			'tij_MerchantPaymentCurrency' => $payment->get_total_amount()->get_currency()->get_alphabetic_code(),
			'tij_MerchPayType'            => 1,
			'tij_MerchReturnUrl'          => $payment->get_return_url(),
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
		if ( PaymentStatus::SUCCESS === $payment->get_status() || PaymentStatus::EXPIRED === $payment->get_status() ) {
			return;
		}
		
		if ( filter_has_var( INPUT_GET, 'ErrorCode' ) ) {
			$payment->add_note( 'Error Code: ' . \sanitize_text_field( $_GET['ErrorCode'] ) );
			$payment->set_status( PaymentStatus::FAILURE );
			return;
		}
		
		$payment_details = $this->api->get_payment_details( $payment->get_transaction_id() );
		
		if ( isset( $payment_details->Status ) ) {
			$payment->set_status( Statuses::transform( $payment_details->Status ) );
			$payment->add_note( '<strong>CBK Response:</strong><br><pre>' . print_r( $payment_details, true ) . '</pre><br>' );
		}
	}
}
