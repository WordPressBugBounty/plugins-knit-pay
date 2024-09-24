<?php
namespace KnitPay\Gateways\Slydepay;

use Pronamic\WordPress\Pay\Core\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;

/**
 * Title: Slydepay Gateway
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 6.67.0.0
 * @since 6.67.0.0
 */
class Gateway extends Core_Gateway {    
	/**
	 * Initializes an Slydepay gateway
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
		
		$this->api = new API( $config->merchant_email, $config->api_key );
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
		$this->order_id = $payment->key . '_' . $payment->get_id();
		
		$pay_token = $this->api->create_invoice( $this->get_payment_data( $payment ) );

		$payment->set_meta( 'slydepay_pay_token', $pay_token );
		
		$payment->set_transaction_id( $this->order_id );
		$payment->set_action_url( 'https://app.slydepay.com/paylive/detailsnew.aspx?pay_token=' . $pay_token );
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
		$order_amount = $payment->get_total_amount()->number_format( null, '.', '' );
		$descritpion  = $payment->get_description();
		
		return [
			'orderCode'   => $this->order_id,
			'amount'      => $order_amount,
			'descritpion' => $descritpion,
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
		
		$payment_status = $this->api->check_payment_status(
			[
				'orderCode' => $payment->get_transaction_id(),
				'payToken'  => $payment->get_meta( 'slydepay_pay_token' ),
			] 
		);
		
		if ( isset( $payment_status->result ) ) {           
			$payment->set_status( Statuses::transform( $payment_status->result ) );
			$payment->add_note( '<strong>Slydepay Response:</strong><br><pre>' . print_r( $payment_status, true ) . '</pre><br>' );
		}
	}
}
