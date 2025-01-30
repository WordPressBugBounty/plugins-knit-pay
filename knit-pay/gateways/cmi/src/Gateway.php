<?php
namespace KnitPay\Gateways\CMI;

use Pronamic\WordPress\Pay\Core\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;

/**
 * Title: CMI Gateway
 * Copyright: 2020-2025 Knit Pay
 *
 * @author Knit Pay
 * @version 7.71.0.0
 * @since 7.71.0.0
 */
class Gateway extends Core_Gateway {
	private $config;

	/**
	 * CMI Client.
	 *
	 * @var CmiClient
	 */
	private $cmi_client;

	/**
	 * Initializes an CMI gateway
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
		
		$this->cmi_client = new Client( $config );
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

		$payment->set_action_url( $this->cmi_client->get_endpoint_url() . '/fim/est3Dgate' );
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
		$customer        = $payment->get_customer();
		$language        = $customer->get_language();
		$billing_address = $payment->get_billing_address();
		
		// @see https://github.com/ismaail/cmi-php/blob/feature/new_package/docs/CMI.md
		// @see https://github.com/mehdirochdi/cmi-payment-php/blob/main/example/process.php
		// @see https://www.youtube.com/watch?v=X7etohIC238
		$require_opts = [
			'clientid'         => $this->config->client_id,
			'storetype'        => '3D_PAY_HOSTING',
			'trantype'         => 'PreAuth',
			'amount'           => $payment->get_total_amount()->number_format( null, '.', '' ),
			'currency'         => $payment->get_total_amount()->get_currency()->get_numeric_code(),
			'oid'              => $payment->get_transaction_id(),
			'okUrl'            => $payment->get_return_url(),
			'failUrl'          => $payment->get_return_url(),
			'lang'             => $language,
			'email'            => $customer->get_email(),
			'BillToName'       => substr( trim( ( html_entity_decode( $customer->get_name(), ENT_QUOTES, 'UTF-8' ) ) ), 0, 250 ),
			'rnd'              => microtime(),
			'hashAlgorithm'    => 'ver3',

			'encoding'         => 'UTF-8',
			'description'      => $payment->get_description(),
			'tel'              => $billing_address->get_phone(),
			'BillToCompany'    => $billing_address->get_company_name(),
			'BillToStreet1'    => $billing_address->get_line_1(),
			'BillToStreet2'    => $billing_address->get_line_2(),
			'BillToCity'       => $billing_address->get_city(),
			// 'BillToStateProv' => $billing_address->get_region(), // � causing issue.
			'BillToPostalCode' => $billing_address->get_postal_code(),
			'BillToCountry'    => $billing_address->get_country_code(),

			'CallbackURL'      => add_query_arg( 'kp_cmi_webhook', '', home_url( '/' ) ),
			'shopurl'          => add_query_arg( 'cancelled', true, $payment->get_return_url() ),
			'AutoRedirect'     => 'true',
			'refreshtime'      => '5',
		];

		$require_opts = array_map(
			function( $string ) {
				if ( is_string( $string ) ) {
					  return trim( $string );
				}
				return $string;
			},
			$require_opts
		);

		$require_opts['hash'] = $this->cmi_client->generate_hash( $require_opts );

		return $require_opts;
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

		if ( filter_has_var( INPUT_GET, 'cancelled' ) ) {
			$payment->set_status( PaymentStatus::CANCELLED );
			return;
		}

		if ( filter_has_var( INPUT_POST, 'HASH' ) ) {
			$post_string = file_get_contents( 'php://input' );

			// Convert Query String to Array.
			parse_str( $post_string, $order_status );

			if ( $this->cmi_client->generate_hash( $order_status ) !== $order_status['HASH'] ) {
				$payment->add_note( 'Hash missmatch.' );
				return;
			}
		} else {
			$order_status = $this->cmi_client->get_order_status( $payment->get_transaction_id() );
		}

		$payment->add_note( '<strong>CMI Response:</strong><br><pre>' . print_r( $order_status, true ) . '</pre><br>' );

		if ( isset( $order_status['Response'] ) ) {
			$payment->set_status( Statuses::transform( $order_status['Response'] ) );
		}
	}
}
