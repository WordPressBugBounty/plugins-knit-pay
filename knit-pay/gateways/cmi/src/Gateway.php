<?php
namespace KnitPay\Gateways\CMI;

use Pronamic\WordPress\Pay\Core\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;
use Mehdirochdi\CMI\CmiClient;
use Mehdirochdi\CMI\Exception\InvalidArgumentException;


require_once 'lib/Exception/ExceptionInterface.php';
require_once 'lib/Exception/InvalidArgumentException.php';
require_once 'lib/CmiClient.php';


/**
 * Title: CMI Gateway
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 7.71.0.0
 * @since 7.71.0.0
 */
class Gateway extends Core_Gateway {
	private $config;
	private $endpoint_url;
	private $cmi_client;
	/**
	 * Initializes an CMI gateway
	 *
	 * @param Config $config
	 *            Config.
	 */
	public function init( Config $config ) {
		$this->set_method( self::METHOD_HTML_FORM );
		
		$this->config = $config;
		
		$endpoint_urls      = [
			self::MODE_TEST => 'https://testpayment.cmi.co.ma',
			self::MODE_LIVE => 'https://payment.cmi.co.ma',
		];
		$this->endpoint_url = $endpoint_urls[ $this->get_mode() ];
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
		
		$payment->set_action_url( $this->endpoint_url . '/fim/est3Dgate' );
	}

	/**
	 * Get CMI Client with Payment Data.
	 *
	 * @param Payment $payment
	 *            Payment.
	 *
	 * @return array
	 */
	private function get_cmi_client( Payment $payment ) {
		$customer        = $payment->get_customer();
		$language        = $customer->get_language();
		$billing_address = $payment->get_billing_address();
		
		// @see https://github.com/ismaail/cmi-php/blob/main/docs/CMI.md
		// @see https://github.com/mehdirochdi/cmi-payment-php/blob/main/example/process.php
		// @see https://www.youtube.com/watch?v=X7etohIC238
		$require_opts = [
			'storetype'        => '3D_PAY_HOSTING',
			'trantype'         => 'PreAuth',
			'currency'         => $payment->get_total_amount()->get_currency()->get_numeric_code(), // TODO MAD
			'rnd'              => microtime(),
			'lang'             => $language,
			'hashAlgorithm'    => 'ver3',
			'encoding'         => 'UTF-8', // OPTIONAL
			'refreshtime'      => '5', // OPTIONAL
				
			'storekey'         => $this->config->store_key, // STOREKEY
			'clientid'         => $this->config->client_id, // CLIENTID
			'oid'              => $payment->get_transaction_id(), // COMMAND ID IT MUST BE UNIQUE
			'shopurl'          => add_query_arg( 'cancelled', true, $payment->get_return_url() ),
			'okUrl'            => $payment->get_return_url(),
			'failUrl'          => $payment->get_return_url(),
			'email'            => $customer->get_email(),
			'BillToName'       => substr( trim( ( html_entity_decode( $customer->get_name(), ENT_QUOTES, 'UTF-8' ) ) ), 0, 50 ),
			'BillToCompany'    => $billing_address->get_company_name(),
			'BillToStreet12'   => $billing_address->get_line_1(),
			'BillToCity'       => $billing_address->get_city(),
			// 'BillToStateProv' => $billing_address->get_region(), // é causing issue.
			'BillToPostalCode' => $billing_address->get_postal_code(),
			'BillToCountry'    => $billing_address->get_country_code(),
			'tel'              => $billing_address->get_phone(),
			'amount'           => $payment->get_total_amount()->number_format( null, '.', '' ),
			// 'CallbackURL' => '',
			'AutoRedirect'     => 'true',
		];
		
		$cmi_client = new CmiClient( $require_opts );
		
		$cmi_client->generateHash();
		
		return $cmi_client;
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
		$this->cmi_client = $this->get_cmi_client( $payment );

		return $this->cmi_client->getRequireOpts();
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
		
		$post_string = file_get_contents( 'php://input' );
		
		// Convert Query String to Array.
		parse_str( $post_string, $post_array );

		try {
			$post_array['storekey'] = $this->config->store_key;
			
			$cmi_client = new CmiClient( $post_array );
		} catch ( InvalidArgumentException $e ) {
			$payment->add_note( 'Exception: ' . $e->getMessage() );
			return;
		}
				
		if ( $cmi_client->generateHash() !== $post_array['HASH'] ) {
			$payment->add_note( 'Hash missmatch.' );
			return;
		}
		
		$payment->add_note( '<strong>CMI Response:</strong><br><pre>' . print_r( $post_array, true ) . '</pre><br>' );
						
		if ( isset( $post_array['Response'] ) ) {
			$payment->set_status( Statuses::transform( $post_array['Response'] ) );
		}
	}
}
