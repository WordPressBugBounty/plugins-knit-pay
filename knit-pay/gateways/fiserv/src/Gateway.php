<?php
namespace KnitPay\Gateways\Fiserv;

use Pronamic\WordPress\Pay\Core\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;
use KnitPay\Gateways\Fiserv\Lib\Utility;
use WP_Error;

require_once 'lib/Utility.php';

/**
 * Title: Fiserv Gateway
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 6.64.0.0
 * @since 6.64.0.0
 */
class Gateway extends Core_Gateway {

	/**
	 * Initializes an Fiserv gateway
	 *
	 * @param Config $config
	 *            Config.
	 */
	public function init( Config $config ) {
		$this->config = $config;

		$this->set_method( self::METHOD_HTML_FORM );
		
		$this->features = Utility::getFeatures();
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
		
		$payment_currency = $payment->get_total_amount()
			->get_currency()
			->get_alphabetic_code();
		if ( isset( $payment_currency ) && 'INR' !== $payment_currency ) {
			$currency_error = 'Fiserv only accepts payments in Indian Rupees. If you are a store owner, kindly activate INR currency for ' . $payment->get_source() . ' plugin.';
			throw new \Exception( $currency_error );
		}

		$payment->set_transaction_id( $payment->get_id() );

		$payment->set_action_url( $this->get_feature( 'connecturl' ) );
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
		$charge_total = $payment->get_total_amount()->number_format( null, '.', '' );
		$currency     = $payment->get_total_amount()->get_currency()->get_numeric_code();
		
		$txndatetime = $payment->get_date()->format( 'Y:m:d-H:i:s' );
		
		$ipg_args = [
			'txntype'               => 'sale',
			'timezone'              => $payment->get_date()->getTimezone()->getName(),
			'txndatetime'           => $txndatetime,
			'hash'                  => $this->create_hash( $txndatetime, $charge_total, $currency ),
			'currency'              => $currency,
			'mode'                  => 'payonly',
			'storename'             => $this->config->storename,
			'chargetotal'           => $charge_total,
			'checkoutoption'        => 'classic',
			'oid'                   => $payment->get_order_id(),
			'merchantTransactionId' => $payment->get_id(),
			'language'              => 'en_US',
			'responseSuccessURL'    => $payment->get_return_url(),
			'responseFailURL'       => $payment->get_return_url(),
			// 'transactionNotificationURL' => $notifyUrl,
			// 'checkoutoption' => 'combinedpage',
		];
		return $ipg_args;
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
		
		$post_data = [];
		
		$getPost = wp_unslash( $_POST );
		
		if ( ! empty( $getPost ) ) {
			foreach ( $getPost as $key => $value ) {
				$post_data[ $key ] = htmlentities( $value, ENT_QUOTES );
			}
		} else {
			throw new \Exception( 'Transaction data not found.' );
		}
		
		if ( $post_data['oid'] !== $payment->get_order_id() ) {
			throw new \Exception( 'Order ID missmatch.' );
		}
		
		$chargetotal  = $post_data['chargetotal'];
		$currency     = $post_data['currency'];
		$txndatetime  = $post_data['txndatetime'];
		$approvalcode = $post_data['approval_code'];
		
		$sharedsecret = $this->config->sharedsecret;
		$storename    = $this->config->storename;
		
		$payment->add_note( '<strong>Fiserv Response:</strong><br><pre>' . print_r( $post_data, true ) . '</pre><br>' );
		
		$hashValue = sha1( bin2hex( $sharedsecret . $approvalcode . $chargetotal . $currency . $txndatetime . $storename ) );
		if ( $hashValue !== $post_data['response_hash'] ) {
			throw new \Exception( 'Hash missmatch.' );
		}
		
		$approval_code_first = substr( $post_data['approval_code'], 0, 1 );
		
		$payment->set_transaction_id( $post_data['ipgTransactionId'] );     
		$payment->set_status( Statuses::transform( $approval_code_first ) );        
	}
	
	private function get_feature( $type ) {
		$resellerFeatures = $this->features['ind']['icici']; // TODO remove hardcode
		
		switch ( $type ) {
			case 'customer':
				$return = $resellerFeatures['customer_detail_title'];
				break;
			case 'customer_detail':
				$return = $resellerFeatures['customer_detail'];
				break;
			case 'contact_support_title':
				$return = $resellerFeatures['contact_support_title'];
				break;
			case 'contact_support':
				$return = $resellerFeatures['contact_support'];
				break;
			case 'connecturl':
				if ( self::MODE_TEST === $this->config->mode ) {
					$return = $resellerFeatures['testurl'];
				} else {
					$return = $resellerFeatures['produrl'];
				}
				break;
			case 'apiurl':
				if ( self::MODE_TEST === $this->config->mode ) {
					$return = $resellerFeatures['apiurl'];
				} else {
					$return = $resellerFeatures['prodapiurl'];
				}
				break;
			case 'refunds':
				$return = $resellerFeatures['refunds'];
				break;
			default:
				$return = '';
		}
		return $return;
	}
	
	private function create_hash( $txndatetime, $charge_total, $currency ) {       
		$stringToHash = $this->config->storename . $txndatetime . $charge_total . $currency . $this->config->sharedsecret;
		$ascii        = bin2hex( $stringToHash );
		
		return sha1( $ascii );
	}
}
