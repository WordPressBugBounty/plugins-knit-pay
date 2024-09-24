<?php
namespace KnitPay\Gateways\EBS;

use Pronamic\WordPress\Pay\Core\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;
use WP_Error;
use Pronamic\WordPress\Pay\Core\PaymentMethod;


/**
 * Title: EBS Gateway
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 1.0.0
 * @since 3.0.0
 */
class Gateway extends Core_Gateway {


	const NAME = 'ebs';

	const PAYMENT_URL = 'https://secure.ebs.in/pg/ma/payment/request';

	/**
	 * Initializes an EBS gateway
	 *
	 * @param Config $config
	 *            Config.
	 */
	public function init( Config $config ) {        
		$this->config = $config;

		$this->set_method( self::METHOD_HTML_FORM );

		$this->register_payment_methods();
	}

	private function register_payment_methods() {
		$this->register_payment_method( new PaymentMethod( PaymentMethods::CREDIT_CARD ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::EBS ) );
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
			$currency_error = 'EBS only accepts payments in Indian Rupees. If you are a store owner, kindly activate INR currency for ' . $payment->get_source() . ' plugin.';
			throw new \Exception( $currency_error );
		}

		$payment->set_transaction_id( $payment->key . '_' . $payment->get_id() );

		$payment->set_action_url( $payment->get_pay_redirect_url() );
	}

	/**
	 * Redirect to the gateway action URL.
	 *
	 * @param Payment $payment The payment to redirect for.
	 * @return void
	 * @throws \Exception Throws exception when action URL for HTTP redirect is empty.
	 */
	public function redirect( Payment $payment ) {
		$payment->set_action_url( self::PAYMENT_URL );
		parent::redirect( $payment );
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
		$account_id = $this->config->account_id;
		$secret_key = $this->config->secret_key;
		$page_id    = ''; // TODO: Give option to use custom page id
		$hash_algo  = 'sha1'; // TODO: Give option to use change algo;
		$mode       = strtoupper( $this->config->mode );

		$customer         = $payment->get_customer();
		$name             = substr( trim( ( html_entity_decode( $customer->get_name(), ENT_QUOTES, 'UTF-8' ) ) ), 0, 100 );
		$billing_address  = $payment->get_billing_address();
		$delivery_address = $payment->get_shipping_address();

		$data['channel']      = 0; // Standard Mode
		$data['account_id']   = $account_id;
		$data['reference_no'] = $payment->get_transaction_id();
		$data['amount']       = $payment->get_total_amount()->number_format( null, '.', '' );
		$data['mode']         = $mode;
		$data['currency']     = $payment->get_total_amount()->get_currency()->get_alphabetic_code();
		$data['description']  = $payment->get_description();
		$data['return_url']   = $payment->get_return_url();

		$data['name']        = empty( $name ) ? 'name' : $name;
		$data['address']     = empty( $billing_address->get_line_1() ) ? 'address' : $billing_address->get_line_1();
		$data['city']        = empty( $billing_address->get_city() ) ? 'city' : $billing_address->get_city();
		$data['state']       = $billing_address->get_region();
		$data['country']     = ( 3 === strlen( $billing_address->get_country_code() ) ) ? $billing_address->get_country_code() : 'xyz';
		$data['postal_code'] = empty( $billing_address->get_postal_code() ) ? '000000' : $billing_address->get_postal_code();
		$data['phone']       = $billing_address->get_phone();
		$data['email']       = $customer->get_email();

		if ( ! empty( $delivery_address ) ) {
			$data['ship_name']        = substr( trim( ( html_entity_decode( $delivery_address->get_name()->get_full_name(), ENT_QUOTES, 'UTF-8' ) ) ), 0, 100 );
			$data['ship_address']     = $delivery_address->get_line_1();
			$data['ship_city']        = $delivery_address->get_city();
			$data['ship_state']       = $delivery_address->get_region();
			$data['ship_country']     = ( 3 === strlen( $delivery_address->get_country_code() ) ) ? $delivery_address->get_country_code() : 'xyz';
			$data['ship_postal_code'] = $delivery_address->get_postal_code();
			$data['ship_phone']       = $delivery_address->get_phone();
		}

		$data['page_id'] = $page_id;

		$data['secure_hash'] = $this->calculate_hash( $data, $secret_key, $hash_algo );

		return $data;
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

		$account_id = $this->config->account_id;
		$secret_key = $this->config->secret_key;
		$page_id    = ''; // TODO: Give option to use custom page id
		$hash_algo  = 'sha1'; // TODO: Give option to use change algo;
		$mode       = strtoupper( $this->config->mode );

		$posted_data = $_POST;

		if ( ! isset( $posted_data['SecureHash'] ) ) {
			return;
		}

		$secure_hash = $posted_data['SecureHash'];
		$hash_value  = $this->calculate_hash( $posted_data, $secret_key, $hash_algo );
		if ( $secure_hash !== $hash_value ) {
			$payment->add_note( 'Hash validation Failed!' );
			return;
		}

		if ( $payment->get_total_amount()->number_format( null, '.', '' ) !== $posted_data['Amount'] ) {
			return;
		}

		if ( $payment->get_transaction_id() !== $posted_data['MerchantRefNo'] ) {
			return;
		}

		$new_payment_Status = Statuses::transform( $posted_data['ResponseCode'], $posted_data['IsFlagged'] );
		$payment->set_status( $new_payment_Status );
		if ( $new_payment_Status === PaymentStatus::SUCCESS ) {
			$payment->set_transaction_id( $posted_data['PaymentID'] );
		}
		$payment->add_note( 'EBS Response: <pre>' . print_r( $posted_data, true ) . '</pre>' );
	}

	private function calculate_hash( $data, $secret_key, $hash_algo ) {

		unset( $data['SecureHash'] );

		$hash_string = $secret_key;
		ksort( $data );
		foreach ( $data as $key => $value ) {
			if ( strlen( $value ) > 0 ) {
				$hash_string .= '|' . $value;
			}
		}

		switch ( $hash_algo ) {
			case 'sha1':
				$hash_value = sha1( $hash_string );
				break;
			case 'sha256':
				$hash_value = sha512( $hash_string );
				break;
			case 'md5':
				$hash_value = md5( $hash_string );
				break;
			default:
				$hash_value = '';
		}
		return strtoupper( $hash_value );
	}
}
