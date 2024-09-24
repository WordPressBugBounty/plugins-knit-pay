<?php
namespace KnitPay\Gateways\Fygaro;

use Pronamic\WordPress\Pay\Core\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Payments\Payment;

/**
 * Title: Fygaro Gateway
 * Copyright: 2020-2021 Knit Pay
 *
 * @author Knit Pay
 * @version 5.0.0
 * @since 5.0.0
 */
class Gateway extends Core_Gateway {

	/**
	 * Constructs and initializes an Fygaro gateway
	 *
	 * @param Config $config
	 *            Config.
	 */
	public function __construct( Config $config ) {
		parent::__construct( $config );
		
		$this->config = $config;

		$this->set_method( self::METHOD_HTTP_REDIRECT );
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
		$api_key            = $this->config->api_key;
		$api_secret         = $this->config->api_secret;
		$payment_button_url = $this->config->payment_button_url;
		
		$payment->set_transaction_id( $payment->key . '_' . $payment->get_id() );
		
		// Used to retrive payment id after successful payment redirection.
		setcookie( 'kp_fygaro_transaction_id', $payment->get_transaction_id(), time() + 1800, '/' );

		$payload = [
			'amount'           => $payment->get_total_amount()->format(),
			'custom_reference' => $payment->get_transaction_id(),
			'client_note'      => $payment->get_description(),
		];
		
		// JWT v6 conflicting with many plugings. that's why restricting it to only Faygaro.
		require __DIR__ . '/vendor/autoload.php';
		$jwt = \Firebase\JWT\JWT::encode( $payload, $api_secret, 'HS256', $api_key );
		
		$payment->set_action_url( add_query_arg( 'jwt', $jwt, $payment_button_url ) );
	}
}
