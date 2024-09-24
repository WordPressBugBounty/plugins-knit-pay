<?php
namespace KnitPay\Gateways\MercadoPago;

use Pronamic\WordPress\Pay\Core\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;
use Exception;

/**
 * Title: Mercado Pago Gateway
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 8.88.0.0
 * @since 8.88.0.0
 */
class Gateway extends Core_Gateway {
	private $config;
	private $api;

	/**
	 * Initializes an Mercado Pago gateway
	 *
	 * @param Config $config Config.
	 */
	public function init( Config $config ) {
		$this->config = $config;

		$this->set_method( self::METHOD_HTTP_REDIRECT );

		// Supported features.
		$this->supports = [
			'payment_status_request',
		];
		
		$this->api_client = new Client( $this->config );
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
		
		$preference = $this->api_client->create_preference( $this->get_payment_data( $payment ) );
		
		$payment->set_action_url( $preference->init_point );
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
		
		$return_url = $payment->get_return_url();

		// @see https://www.mercadopago.com.ar/developers/en/reference/preferences/_checkout_preferences/post
		return [
			'auto_return'        => 'all',
			'back_urls'          => [
				'success' => $return_url,
				'failure' => $return_url,
				'pending' => $return_url,
			],
			'external_reference' => $payment->get_transaction_id(),
			'items'              => [
				[
					'title'       => $payment->get_description(),
					'description' => $payment->get_description(),
					'quantity'    => 1,
					'currency_id' => $payment->get_total_amount()->get_currency()->get_alphabetic_code(),
					'unit_price'  => floatval( $payment->get_total_amount()->get_value() ),
				],
			],
			// 'notification_url' => '', // TODO
			'payer'              => [
				'name'    => $customer->get_name()->get_first_name(),
				'surname' => $customer->get_name()->get_last_name(),
				'email'   => $customer->get_email(),
			],
			'payment_methods'    => [
				'installments' => 1,
			],
		];
	}

	/**
	 * Update status of the specified payment.
	 *
	 * @param Payment $payment Payment.
	 * @throws Exception If error occurs while updating payment status.
	 */
	public function update_status( Payment $payment ) {
		$mp_payments = $this->api_client->search_payments( $payment->get_transaction_id() );
		
		if ( 0 === count( $mp_payments ) ) {
			return;
		}
		
		$mp_payment = reset( $mp_payments );

		$payment_status = Statuses::transform( $mp_payment->status );
		if ( Core_Statuses::SUCCESS === $payment_status ) {
			$payment->set_transaction_id( $mp_payment->id );
		}
		$payment->set_status( $payment_status );
		
		if ( pronamic_pay_plugin()->is_debug_mode() ) {
			$payment->add_note( '<strong>MP Response:</strong><br><pre>' . print_r( $mp_payment, true ) . '</pre><br>' );
		}
	}
	
	// TODO Add Support for refund
	// @see https://www.mercadopago.com.ar/developers/en/reference/chargebacks/_payments_id_refunds/post
}
