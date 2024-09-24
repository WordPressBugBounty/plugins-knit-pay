<?php
namespace KnitPay\Gateways\Easebuzz;

use KnitPay\Gateways\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Core\PaymentMethod;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;
use Pronamic\WordPress\Pay\Refunds\Refund;
use Exception;

/**
 * Title: Easebuzz Gateway
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 1.0.0
 * @since 1.2.0
 */
class Gateway extends Core_Gateway {
	protected $config;

	private $env;

	const NAME = 'easebuzz';

	/**
	 * Initializes an Easebuzz gateway
	 *
	 * @param Config $config
	 *            Config.
	 */
	public function init( Config $config ) {
		$this->config = $config;

		$this->set_method( self::METHOD_HTTP_REDIRECT );

		// Supported features.
		$this->supports = [
			'payment_status_request',
			'refunds',
		];

		$this->env = 'prod';
		if ( self::MODE_TEST === $config->mode ) {
			$this->env = 'test';
		}

		$this->register_payment_methods();

		// Included Easebuzz library.
		include_once 'easebuzz-lib/easebuzz_payment_gateway.php';

		// TODO Implement phone field
		// https://github.com/pronamic/wp-pronamic-pay/issues/351
	}

	private function register_payment_methods() {
		$this->register_payment_method( new PaymentMethod( PaymentMethods::EASEBUZZ ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::CREDIT_CARD ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::DEBIT_CARD ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::NET_BANKING ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::UPI ) );
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
		$payment_currency = $payment->get_total_amount()->get_currency()->get_alphabetic_code();
		if ( isset( $payment_currency ) && 'INR' !== $payment_currency ) {
			$currency_error = 'Easebuzz only accepts payments in Indian Rupees. If you are a store owner, kindly activate INR currency for ' . $payment->get_source() . ' plugin.';
			throw new Exception( $currency_error );
		}

		$merchant_key    = $this->config->merchant_key;
		$merchant_salt   = $this->config->merchant_salt;
		$sub_merchant_id = $this->config->sub_merchant_id;

		$product_info = $payment->get_description();
		$product_info = preg_replace( '/[^a-zA-Z0-9\s]/', ' ', $product_info );

		$customer   = $payment->get_customer();
		$first_name = '';
		if ( null !== $customer->get_name() ) {
			$first_name = $customer->get_name()->get_first_name();
		}
		if ( empty( $first_name ) && filter_has_var( INPUT_POST, 'test_pay_gateway' ) ) {
			$first_name = 'Empty';
		}

		$surl = $payment->get_return_url();
		$furl = $payment->get_return_url();

		$easebuzzObj = new Easebuzz( $merchant_key, $merchant_salt, $this->env );

		$payment_source = $payment->get_source();
		if ( 'woocommerce' === $payment_source ) {
			$payment_source = 'wc';
		}

		// @see https://docs.easebuzz.in/docs/payment-gateway/8ec545c331e6f-initiate-payment-api
		$postData = wp_parse_args(
			$this->get_transaction_data_array( $payment ),
			[
				'txnid'             => $payment->key . '_' . $payment->get_id(),
				'firstname'         => $first_name,
				'productinfo'       => $product_info,
				'surl'              => $surl,
				'furl'              => $furl,
				'udf1'              => 'knit-pay',
				'udf2'              => KNITPAY_VERSION,
				'udf3'              => $payment_source,
				'udf4'              => $payment->get_id(),
				'udf5'              => $payment->get_source_id(),
				'udf6'              => $payment->get_order_id(),
				'udf7'              => home_url( '/' ),
				'address1'          => '',
				'address2'          => '',
				'city'              => '',
				'state'             => '',
				'country'           => '',
				'zipcode'           => '',
				'show_payment_mode' => PaymentMethods::transform( $payment->get_payment_method() ),
				'sub_merchant_id'   => $sub_merchant_id,
			]
		);

		$response = $easebuzzObj->initiatePaymentAPI( $postData, false );

		if ( ! isset( $response->status ) ) {
			throw new Exception( 'An error occurred while creating the payment link. Kindly retry after some time.' );
		}

		if ( 0 === $response->status ) {
			if ( isset( $response->error_desc ) ) {
				throw new Exception( $response->error_desc );
			} else {
				throw new Exception( $response->data );
			}
			return;
		}

		$accesskey = ( $response->status === 1 ) ? $response->data : null;

		$payment_link = _getURL( $this->env ) . 'pay/' . $accesskey;

		$payment->set_transaction_id( $postData['txnid'] );
		$payment->set_action_url( $payment_link );
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

		$status_check_action = false;
		if ( empty( $_POST['key'] ) ) {
			$status_check_action = true;
		}

		$merchant_key  = $this->config->merchant_key;
		$merchant_salt = $this->config->merchant_salt;

		$easebuzzObj = new Easebuzz( $merchant_key, $merchant_salt, $this->env );

		if ( $status_check_action ) {

			$postData          = $this->get_transaction_data_array( $payment );
			$postData['txnid'] = $payment->get_transaction_id();

			$response = $easebuzzObj->transactionAPI( $postData );
			$response = json_decode( $response, true );

			if ( empty( $response ) ) {
				return;
			}

			// Error from Easebuzz PHP Library.
			if ( 0 === $response['status'] ) {
				throw new Exception( $response['data'] );
			}

			// Error from Easebuzz API Call.
			if ( ! $response['status'] ) {
				throw new Exception( $response['msg'] );
			}

			$data = $response['msg'];
		} else {
			$result = $easebuzzObj->easebuzzResponse( $_POST );
			$result = json_decode( $result, true );

			if ( 0 === $result['status'] ) {
				throw new Exception( $result['data'] );
			}
			$data = $result['data'];
		}

		if ( $data['txnid'] !== $payment->get_transaction_id() ) {
			return;
		}

		if ( $data['key'] !== $merchant_key ) {
			throw new Exception( 'Key Missmatch!' );
		}

		$payment->set_status( Statuses::transform( $data['status'] ) );
		$payment->add_note( 'Easebuzz ID: ' . $data['easepayid'] . '<br>Easebuzz Status: ' . $data['status'] . '<br>Error Message: ' . $data['error_Message'] );

		if ( PaymentStatus::SUCCESS === $payment->get_status() ) {
			$payment->set_transaction_id( $data['easepayid'] );
		}

	}

	private function get_transaction_data_array( Payment $payment ) {
		$customer = $payment->get_customer();
		$email    = $customer->get_email();
		$phone    = '';

		$billing_address = $payment->get_billing_address();
		if ( null !== $billing_address && ! empty( $billing_address->get_phone() ) ) {
			$phone = $billing_address->get_phone();
		}
		// Easebuzz throws error if whitespace or special characters available in phone, hence removing them.
		$phone = preg_replace( '/[^\d+]/', '', $phone );

		$amount = $payment->get_total_amount()->number_format( null, '.', '' );

		// There is bug in Easebuzz, which cause issue while processing refund if amount has 00 after decimal.
		$amount = rtrim( $amount, '0' );
		if ( '.' === substr( $amount, -1 ) ) {
			$amount .= '0';
		}

		$postData = [
			'amount' => $amount,
			'email'  => $email,
			'phone'  => $phone,
		];

		return $postData;
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

		$merchant_key  = $this->config->merchant_key;
		$merchant_salt = $this->config->merchant_salt;
		$easebuzzObj   = new Easebuzz( $merchant_key, $merchant_salt, $this->env );

		$postData                  = $this->get_transaction_data_array( $refund->get_payment() );
		$postData['txnid']         = $transaction_id;
		$postData['refund_amount'] = $amount->number_format( null, '.', '' );

		$response = json_decode( $easebuzzObj->refundAPI( $postData ), false );

		if ( ! isset( $response->status ) ) {
			throw new Exception( 'An error occurred while initiating refund.' );
		}

		if ( ! $response->status ) {
			throw new Exception( $response->reason );
		}

		if ( ! isset( $response->refund_id ) ) {
			throw new Exception( 'Something went wrong.' );
		}

		$refund->psp_id = $response->refund_id;
	}
}
