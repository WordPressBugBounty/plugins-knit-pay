<?php
namespace KnitPay\Gateways\PayU;

use Pronamic\WordPress\Money\Money;
use Pronamic\WordPress\Number\Number;
use Pronamic\WordPress\Pay\Core\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Core\PaymentMethod;
use Pronamic\WordPress\Pay\Payments\FailureReason;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;
use Pronamic\WordPress\Pay\Refunds\Refund;
use DateTime;
use Exception;

/**
 * Title: PayU Gateway
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 5.4.0
 * @since 5.4.0
 */
class Gateway extends Core_Gateway {
	protected $config;

	/**
	 * Client.
	 *
	 * @var Client
	 */
	private $client;

	/**
	 * Initializes an PayU gateway
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
			'refunds',
		];

		// Client.
		$this->client = new Client( $config, self::MODE_TEST === $config->mode );

		$this->register_payment_methods();
	}

	private function register_payment_methods() {
		$this->register_payment_method( new PaymentMethod( PaymentMethods::CREDIT_CARD ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::PAY_U ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::UPI ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::DEBIT_CARD ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::NET_BANKING ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::PAYTM ) );
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
		// Show error If Knit Pay Pay not available after 30 Jun 2024.
		if ( ! defined( 'KNIT_PAY_PRO' ) && ( new DateTime() > new DateTime( '2024-06-30' ) ) ) {
			$error = 'Support for PayU has ended. Please contact the website admin to fix the configuration.';
			throw new \Exception( $error );
		}

		/*
		 if ( ! $this->config->is_connected ) {
			$error       = 'PayU is not connected. If you are a store owner, integrate PayU and try again.';
			$this->error = new WP_Error( 'payu_error', $error );
			return;
		} */

		if ( empty( $this->config->mid ) ) {
			 $error = 'PayU Merchant ID is missing. Kindly enter the correct Merchant ID on the PayU configuration page.';
			 throw new \Exception( $error );
		}

		if ( '0.00' === $payment->get_total_amount()->number_format( null, '.', '' ) ) {
			throw new \Exception( 'The amount can not be zero.' );
		}

		// For Transaction fees setup validation.
		$this->get_formated_transaction_fees( $payment->get_total_amount(), $this->config->transaction_fees_percentage, $this->config->transaction_fees_fix );

		$payment_currency = $payment->get_total_amount()->get_currency()->get_alphabetic_code();
		if ( isset( $payment_currency ) && 'INR' !== $payment_currency ) {
			$currency_error = 'PayU only accepts payments in Indian Rupees. If you are a store owner, kindly activate INR currency for ' . $payment->get_source() . ' plugin.';
			throw new \Exception( $currency_error );
		}

		$payment->set_transaction_id( $payment->key . '_' . $payment->get_id() );
		$payment->set_action_url( $this->client->get_payment_server_url() . '/_payment' );
	}

	/**
	 * Update status of the specified payment.
	 *
	 * @param Payment $payment
	 *            Payment.
	 */
	public function update_status( Payment $payment ) {
		if ( Core_Statuses::SUCCESS === $payment->get_status() ) {
			return;
		}

		$transaction = $this->client->verify_payment( $payment->get_transaction_id() );
		if ( $transaction->txnid !== $payment->get_transaction_id() ) {
			$payment->add_note( 'Something went wrong: ' . print_r( $transaction, true ) );
			return;
		}

		if ( isset( $transaction->status ) ) {
			$transaction_status = $transaction->status;

			if ( Statuses::SUCCESS === $transaction_status ) {
				$payment->set_transaction_id( $transaction->mihpayid );
			} elseif ( isset( $transaction->error_Message ) ) {
				$failure_reason = new FailureReason();
				$failure_reason->set_message( $transaction->error_Message );
				$payment->set_failure_reason( $failure_reason );
			}

			$note = '<strong>PayU Transaction Details:</strong><br><pre>' . print_r( $transaction, true ) . '</pre>';

			$payment->set_status( Statuses::transform( $transaction_status ) );
			$payment->add_note( $note );
		}
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
		$merchant_key  = $this->config->merchant_key;
		$merchant_salt = $this->config->merchant_salt;

		$txnid  = $payment->get_transaction_id();
		$amount = $payment->get_total_amount()->number_format( null, '.', '' );
		
		$product_info = esc_attr( $payment->get_description() );
		if ( empty( $product_info ) ) {
			$product_info = preg_replace( '/[^!-~\s]/', '', $payment->get_description() );
		}
		$product_info = wp_specialchars_decode( $product_info );

		$first_name = '';
		$last_name  = '';
		$customer   = $payment->get_customer();
		if ( null !== $customer->get_name() ) {
			$first_name = $customer->get_name()->get_first_name();
			$last_name  = $customer->get_name()->get_last_name();
		}
		$email = $customer->get_email();

		$phone     = '';
		$address   = '';
		$address_2 = '';
		$city      = '';
		$state     = '';
		$country   = '';
		$zipcode   = '';

		$billing_address = $payment->get_billing_address();
		if ( null !== $billing_address ) {
			if ( ! empty( $billing_address->get_phone() ) ) {
				$phone = $billing_address->get_phone();
			}
			$address   = $billing_address->get_line_1();
			$address_2 = $billing_address->get_line_2();
			$city      = $billing_address->get_city();
			$state     = $billing_address->get_region();
			$country   = $billing_address->get_country();
			$zipcode   = $billing_address->get_postal_code();
		}

		$udf1 = PHP_VERSION;
		$udf2 = KNITPAY_VERSION;
		$udf3 = $payment->get_source();
		$udf4 = home_url( '/' );
		$udf5 = defined( 'KNIT_PAY_PRO' ) ? 'Knit Pay Pro' : 'Knit Pay';

		if ( 'woocommerce' === $udf3 ) {
			$udf3 = 'wc';
		}

		// @see: https://devguide.payu.in/docs/collect-additional-charges/
		$transaction_fees   = $this->get_transaction_fees( $payment->get_total_amount(), $this->config->transaction_fees_percentage, $this->config->transaction_fees_fix );
		$additional_charges = "CC:{$transaction_fees},DC:{$transaction_fees},NB:{$transaction_fees},UPI:{$transaction_fees},CASH:{$transaction_fees},EMI:{$transaction_fees}";

		$str = "{$merchant_key}|{$txnid}|{$amount}|{$product_info}|{$first_name}|{$email}|{$udf1}|{$udf2}|{$udf3}|{$udf4}|{$udf5}||||||{$merchant_salt}|{$additional_charges}";

		$hash = strtolower( hash( 'sha512', $str ) );

		$return_url = $payment->get_return_url();

		// @see: https://devguide.payu.in/docs/payu-hosted-checkout/payu-hosted-checkout-integration/#Request_Params
		return [
			'key'                => $merchant_key,
			'txnid'              => $txnid,
			'amount'             => $amount,
			'productinfo'        => $product_info,
			'firstname'          => $first_name,
			'lastname'           => $last_name,
			'address1'           => $address,
			'address2'           => $address_2,
			'city'               => $city,
			'state'              => $state,
			'country'            => $country,
			'zipcode'            => $zipcode,
			'email'              => $email,
			'phone'              => $phone,
			'surl'               => $return_url,
			'furl'               => $return_url,
			'hash'               => $hash,
			'udf1'               => $udf1,
			'udf2'               => $udf2,
			'udf3'               => $udf3,
			'udf4'               => $udf4,
			'udf5'               => $udf5,
			'additional_charges' => $additional_charges,
			'enforce_paymethod'  => PaymentMethods::transform( $payment->get_payment_method() ),
			'display_lang'       => $this->get_payu_language( $customer->get_language() ),
		];
	}

	/**
	 * Create refund.
	 *
	 * @param Refund $refund Refund.
	 * @return void
	 * @throws \Exception Throws exception on unknown resource type.
	 */
	public function create_refund( Refund $refund ) {
		$amount         = $refund->get_amount();
		$transaction_id = $refund->get_payment()->get_transaction_id();

		$refund->psp_id = $this->client->cancel_refund_transaction( $transaction_id, uniqid( 'refund_' ), $amount->number_format( null, '.', '' ) );
	}

	private function get_transaction_fees( Money $amount, $transaction_fees_percentage, $transaction_fees_fix ) {
		if ( empty( $transaction_fees_percentage ) && empty( $transaction_fees_fix ) ) {
			return 0;
		}

		$formated_transaction_fees   = $this->get_formated_transaction_fees( $amount, $transaction_fees_percentage, $transaction_fees_fix );
		$transaction_fees_percentage = $formated_transaction_fees['transaction_fees_percentage'];
		$transaction_fees_fix        = $formated_transaction_fees['transaction_fees_fix'];

		// Transaction Fees calculated using Percentage.
		$transaction_fees = $amount->multiply( $transaction_fees_percentage )->divide( ( new Number( 100 ) )->subtract( $transaction_fees_percentage ) );

		// Transaction Fees after addition of Fix Transaction Fees.
		$transaction_fees = $transaction_fees->add( $transaction_fees_fix );

		return $transaction_fees->number_format( null, '.', '' );
	}

	private function get_formated_transaction_fees( Money $amount, $transaction_fees_percentage, $transaction_fees_fix ) {
		try {
			$transaction_fees_percentage = new Number( $transaction_fees_percentage );
			if ( 59 < $transaction_fees_percentage->get_value() || 0 > $transaction_fees_percentage->get_value() ) {
				throw new Exception( 'Transaction Fees Percentage should be between 0 and 59.' );
			}
			$transaction_fees_fix = new Money( $transaction_fees_fix, $amount->get_currency() );
			return [
				'transaction_fees_percentage' => $transaction_fees_percentage,
				'transaction_fees_fix'        => $transaction_fees_fix,
			];
		} catch ( \Exception $e ) {
			throw new Exception( 'Invalid Transaction Fees. ' . $e->getMessage() );
		}
	}

	private function get_payu_language( $wordpress_lang ) {
		$payu_lang = 'English';
		switch ( $wordpress_lang ) {
			case 'hi':
				$payu_lang = 'Hindi';
				break;
			case 'gu':
				$payu_lang = 'Gujarati';
				break;
			case 'mr':
				$payu_lang = 'Marathi';
				break;
			case 'te':
				$payu_lang = 'Telugu';
				break;
			case 'ta':
				$payu_lang = 'Tamil';
				break;
			case 'kn':
				$payu_lang = 'Kannada';
				break;
			case 'bn':
				$payu_lang = 'Bengali';
				break;
			default:
				$payu_lang = 'English';
				break;
		}
		return $payu_lang;
	}

	// For now this function is not getting used, but might be used later.
	private function get_verified_redirect_transaction_data( $payment, $post_array ) {
		$status      = $post_array['status'];
		$firstname   = $post_array['firstname'];
		$amount      = $post_array['amount'];
		$txnid       = $post_array['txnid'];
		$posted_hash = $post_array['hash'];
		$productinfo = $post_array['productinfo'];
		$email       = $post_array['email'];
		$mihpayid    = $post_array['mihpayid'];
		$udf1        = $post_array['udf1'];
		$udf2        = $post_array['udf2'];
		$udf3        = $post_array['udf3'];
		$udf4        = $post_array['udf4'];
		$udf5        = $post_array['udf5'];

		if ( ! ( $mihpayid === $payment->get_transaction_id() || $txnid === $payment->get_transaction_id() ) ) {
			return;
		}

		$merchant_key  = $this->config->merchant_key;
		$merchant_salt = $this->config->merchant_salt;

		if ( isset( $post_array['additionalCharges'] ) ) {
			$additionalCharges = $post_array['additionalCharges'];
			$retHashSeq        = $additionalCharges . '|' . $merchant_salt . '|' . $status . '||||||' . $udf5 . '|' . $udf4 . '|' . $udf3 . '|' . $udf2 . '|' . $udf1 . '|' . $email . '|' . $firstname . '|' . $productinfo . '|' . $amount . '|' . $txnid . '|' . $merchant_key;
		} else {
			$retHashSeq = $merchant_salt . '|' . $status . '||||||' . $udf5 . '|' . $udf4 . '|' . $udf3 . '|' . $udf2 . '|' . $udf1 . '|' . $email . '|' . $firstname . '|' . $productinfo . '|' . $amount . '|' . $txnid . '|' . $merchant_key;
		}
		$hash = hash( 'sha512', $retHashSeq );

		if ( $hash !== $posted_hash ) {
			throw new \Exception( 'Invalid Transaction. Hash Missmatch.' );
		} else {
			return (object) $post_array;
		}
	}
}
