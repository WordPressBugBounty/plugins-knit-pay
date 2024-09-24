<?php
namespace KnitPay\Gateways\PayUmoney;

use KnitPay\Gateways\PayU\PaymentMethods;
use Pronamic\WordPress\Pay\Core\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Core\PaymentMethod;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;
use DateTime;
use Exception;
use WP_Error;

/**
 * Title: PayUMoney Gateway
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 1.9.1
 * @since 1.0.0
 */
class Gateway extends Core_Gateway {
	private $config;
	private $config_id;

	const NAME = 'payumoney';

	/**
	 * Client.
	 *
	 * @var Client
	 */
	private $client;

	/**
	 * Initializes an PayUMoney gateway
	 *
	 * @param Config $config
	 *            Config.
	 */
	public function init( Config $config, $config_id ) {
		$this->config = $config;

		$this->set_method( self::METHOD_HTML_FORM );

		// Supported features.
		/*
		 $this->supports = array(
			'payment_status_request',
		); */

		// Client.
		$this->client = new Client( $config );

		if ( self::MODE_TEST === $config->mode ) {
			$this->client->set_payment_server_url( Client::TEST_URL );
			$this->client->set_api_url( Client::API_TEST_URL );
		}

		$this->config_id = $config_id;

		$this->register_payment_methods();
	}

	private function register_payment_methods() {
		$this->register_payment_method( new PaymentMethod( PaymentMethods::CREDIT_CARD ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::PAY_U ) );
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

		$is_payu = self::is_one_payu( $this->config, $this->config_id );
		if ( $is_payu ) {
			$payu_integration = new \KnitPay\Gateways\PayU\Integration();
			$payu_gateway     = $payu_integration->get_gateway( $this->config_id );
			return $payu_gateway->start( $payment );
		}

		$payment_currency = $payment->get_total_amount()
			->get_currency()
			->get_alphabetic_code();
		if ( isset( $payment_currency ) && 'INR' !== $payment_currency ) {
			$currency_error = 'PayUMoney only accepts payments in Indian Rupees. If you are a store owner, kindly activate INR currency for ' . $payment->get_source() . ' plugin.';
			throw new Exception( $currency_error );
		}

		if ( '0.00' === $payment->get_total_amount()->number_format( null, '.', '' ) ) {
			throw new Exception( 'The amount can not be zero.' );
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
		if ( Core_Statuses::OPEN !== $payment->get_status() ) {
			return;
		}

		if ( isset( $_SERVER['HTTP_PAYUMONEY_WEBHOOK'] ) ) {
			$post_array = json_decode( file_get_contents( 'php://input' ), true );
			$this->handle_webhook( $payment, $post_array );
			return;
		}

		if ( empty( $_POST['key'] ) ) {
			$this->update_status_using_api( $payment );
			return;
		}

		$status      = $_POST['status'];
		$firstname   = $_POST['firstname'];
		$amount      = $_POST['amount'];
		$txnid       = $_POST['txnid'];
		$posted_hash = $_POST['hash'];
		$key         = $_POST['key'];
		$productinfo = $_POST['productinfo'];
		$email       = $_POST['email'];
		$payuMoneyId = $_POST['payuMoneyId'];
		$udf1        = $_POST['udf1'];
		$udf2        = $_POST['udf2'];
		$udf3        = $_POST['udf3'];
		$udf4        = $_POST['udf4'];
		$udf5        = $_POST['udf5'];

		$merchant_key  = $this->config->merchant_key;
		$merchant_salt = $this->config->merchant_salt;

		if ( ! ( $payuMoneyId === $payment->get_transaction_id() || $txnid === $payment->get_transaction_id() ) || $key !== $merchant_key ) {
			$payment->add_note( 'Payment transaction id or Merchant Key is incorrect.' );
			return;
		}

		if ( isset( $_POST['additionalCharges'] ) ) {
			$additionalCharges = $_POST['additionalCharges'];
			$retHashSeq        = $additionalCharges . '|' . $merchant_salt . '|' . $status . '||||||' . $udf5 . '|' . $udf4 . '|' . $udf3 . '|' . $udf2 . '|' . $udf1 . '|' . $email . '|' . $firstname . '|' . $productinfo . '|' . $amount . '|' . $txnid . '|' . $key;
		} else {
			$retHashSeq = $merchant_salt . '|' . $status . '||||||' . $udf5 . '|' . $udf4 . '|' . $udf3 . '|' . $udf2 . '|' . $udf1 . '|' . $email . '|' . $firstname . '|' . $productinfo . '|' . $amount . '|' . $txnid . '|' . $key;
		}
		$hash = hash( 'sha512', $retHashSeq );

		if ( $hash != $posted_hash ) {
			throw new Exception( 'Invalid Transaction. Hash Missmatch.' );
		} else {
			$payment->set_status( Statuses::transform( $status ) );
			$payment->set_transaction_id( $payuMoneyId );
		}
	}

	private function update_status_using_api( Payment $payment ) {
		$auth_header = $this->config->auth_header;

		if ( empty( $auth_header ) ) {
			throw new Exception( 'Auth Header is empty. Payment Status Request is not supported. Kindly setup Auth Header in Configuration page.' );
		}

			$txn_status_response = $this->client->get_merchant_transaction_status( $payment->get_transaction_id() );

		if ( $txn_status_response->merchantTransactionId !== $payment->get_transaction_id() ) {
			return;
		}

		$payment->set_status( Statuses::transform( $txn_status_response->status ) );

		$log = 'PayUmoney Status: ' . $txn_status_response->status;
		if ( Core_Statuses::OPEN !== $payment->get_status() ) {
			$payment->set_transaction_id( $txn_status_response->paymentId );
		}
		$payment->add_note( $log );
	}

	/**
	 * Handle Webhook Call.
	 *
	 * @param Payment $payment
	 *            Payment.
	 */
	private function handle_webhook( $payment, $post_array ) {
		if ( $_SERVER['HTTP_PAYUMONEY_WEBHOOK'] !== $this->config->authorization_header_value ) {
				$this->error = new WP_Error( 'payumoney_error', 'Invalid Transaction. Authorization Header Value Missmatch.' );
				return;
		}

		$status        = $post_array['status'];
		$firstname     = $post_array['customerName'];
		$amount        = $post_array['amount'];
		$txnid         = $post_array['merchantTransactionId'];
		$posted_hash   = $post_array['hash'];
		$productinfo   = $post_array['productInfo'];
		$email         = $post_array['customerEmail'];
		$payuMoneyId   = $post_array['paymentId'];
		$udf1          = $post_array['udf1'];
		$udf2          = $post_array['udf2'];
		$udf3          = $post_array['udf3'];
		$udf4          = $post_array['udf4'];
		$udf5          = $post_array['udf5'];
		$error_message = $post_array['error_Message'];

		if ( ! ( $payuMoneyId === $payment->get_transaction_id() || $txnid === $payment->get_transaction_id() ) ) {
			return;
		}

		$merchant_key  = $this->config->merchant_key;
		$merchant_salt = $this->config->merchant_salt;

		$received_status = $status;
		if ( Statuses::USER_CANCELED === $status || Statuses::FAILED === $status ) {
			$status = Statuses::FAILURE;
		}

		if ( isset( $_POST['additionalCharges'] ) ) {
			$additionalCharges = $_POST['additionalCharges'];
			$retHashSeq        = $additionalCharges . '|' . $merchant_salt . '|' . $status . '||||||' . $udf5 . '|' . $udf4 . '|' . $udf3 . '|' . $udf2 . '|' . $udf1 . '|' . $email . '|' . $firstname . '|' . $productinfo . '|' . $amount . '|' . $txnid . '|' . $merchant_key;
		} else {
			$retHashSeq = $merchant_salt . '|' . $status . '||||||' . $udf5 . '|' . $udf4 . '|' . $udf3 . '|' . $udf2 . '|' . $udf1 . '|' . $email . '|' . $firstname . '|' . $productinfo . '|' . $amount . '|' . $txnid . '|' . $merchant_key;
		}
		$hash = hash( 'sha512', $retHashSeq );

		if ( $hash != $posted_hash ) {
			$this->error = new WP_Error( 'payumoney_error', 'Invalid Transaction. Hash Missmatch.' );
		} else {
			if ( Statuses::SUCCESSFUL !== $received_status ) {
				$payment->add_note( 'error: ' . $error_message );
			}
			$payment->set_status( Statuses::transform( $received_status ) );
			$payment->set_transaction_id( $payuMoneyId );
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
		
		$customer = $payment->get_customer();
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

		$udf1 = defined( 'KNIT_PAY_PRO' ) ? 'Knit Pay Pro' : 'Knit Pay';
		$udf2 = KNITPAY_VERSION;
		$udf3 = $payment->get_source();
		$udf4 = home_url( '/' );
		$udf5 = PHP_VERSION;

		if ( 'woocommerce' === $udf3 ) {
			$udf3 = 'wc';
		}

		$str = "{$merchant_key}|{$txnid}|{$amount}|{$product_info}|{$first_name}|{$email}|{$udf1}|{$udf2}|{$udf3}|{$udf4}|{$udf5}||||||{$merchant_salt}";

		$hash = strtolower( hash( 'sha512', $str ) );

		$return_url = $payment->get_return_url();

		return [
			'key'              => $merchant_key,
			'txnid'            => $txnid,
			'amount'           => $amount,
			'productinfo'      => $product_info,
			'firstname'        => $first_name,
			'lastname'         => $last_name,
			'address1'         => $address,
			'address2'         => $address_2,
			'city'             => $city,
			'state'            => $state,
			'country'          => $country,
			'zipcode'          => $zipcode,
			'email'            => $email,
			'phone'            => $phone,
			'surl'             => $return_url,
			'furl'             => $return_url,
			'hash'             => $hash,
			'service_provider' => 'payu_paisa',
			'udf1'             => $udf1,
			'udf2'             => $udf2,
			'udf3'             => $udf3,
			'udf4'             => $udf4,
			'udf5'             => $udf5,
		];
	}

	public static function is_one_payu( Config $config, $config_id ) {
		// Temporarily disabling this plugin by returning, because few clients were facing issues.
		return false;

		$is_one_payu = get_transient( 'knit_pay_payumoney_is_one_payu_' . $config_id );
		if ( 'false' === $is_one_payu ) {
			return false;
		}

		if ( self::MODE_LIVE !== $config->mode ) {
			return false;
		}

		$payu_config                = new \KnitPay\Gateways\PayU\Config();
		$payu_config->merchant_key  = $config->merchant_key;
		$payu_config->merchant_salt = $config->merchant_salt;
		$payu_config->mode          = $config->mode;

		$payu_client = new \KnitPay\Gateways\PayU\Client( $payu_config, self::MODE_TEST === $config->mode );
		$connection  = $payu_client->test_connection();

		if ( $connection ) {
			// One PayU account.

			if ( empty( $config->mid ) ) {
				$config->mid = 1; // PayU gateway don't allow missing mid.
			}

			update_post_meta( $config_id, '_pronamic_gateway_id', 'pay-u' );
			update_post_meta( $config_id, '_pronamic_gateway_payu_mid', $config->mid );
			update_post_meta( $config_id, '_pronamic_gateway_payu_merchant_key', $config->merchant_key );
			update_post_meta( $config_id, '_pronamic_gateway_payu_merchant_salt', $config->merchant_salt );
			update_post_meta( $config_id, '_pronamic_gateway_payu_transaction_fees_percentage', $config->transaction_fees_percentage );
			update_post_meta( $config_id, '_pronamic_gateway_payu_transaction_fees_fix', $config->transaction_fees_fix );

			return true;
		}

		set_transient( 'knit_pay_payumoney_is_one_payu_' . $config_id, 'false', MONTH_IN_SECONDS );
		return false;
	}
}
