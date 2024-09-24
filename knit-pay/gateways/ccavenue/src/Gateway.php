<?php
namespace KnitPay\Gateways\CCAvenue;

use KnitPay\Utils;
use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Core\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Core\PaymentMethod;
use Pronamic\WordPress\Pay\Payments\FailureReason;
use Pronamic\WordPress\Pay\Payments\Payment;
use Exception;

/**
 * Title: CCAvenue Gateway
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 1.0.0
 * @since 2.3.0
 */
class Gateway extends Core_Gateway {
	const NAME = 'ccavenue';

	private $config;
	private $api;

	/**
	 * Initializes an CCAvenue gateway
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
		];

		$this->api = new API( $config, $this->get_mode() );

		$this->register_payment_methods();
	}

	private function register_payment_methods() {
		$this->register_payment_method( new PaymentMethod( PaymentMethods::CCAVENUE ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::CREDIT_CARD ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::DEBIT_CARD ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::NET_BANKING ) );
		$this->register_payment_method( new PaymentMethod( PaymentMethods::UPI ) );
	}

	/**
	 * Get available payment methods.
	 *
	 * @return array<int, string>
	 * @see Core_Gateway::get_available_payment_methods()
	 */
	public function get_available_payment_methods() {
		return $this->get_supported_payment_methods();
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

		$payment->set_action_url(
			add_query_arg(
				$this->get_encrypted_payment_data( $payment ),
				$this->api->get_endpoint_url() . '/transaction/transaction.do?command=initiateTransaction'
			)
		);
	}

	/**
	 * Get encrpted CCAvenue payment data.
	 *
	 * @param Payment $payment
	 *            Payment.
	 *
	 * @return array
	 */
	public function get_encrypted_payment_data( Payment $payment ) {
		$access_code = $this->config->access_code;

		$customer         = $payment->get_customer();
		$language         = $customer->get_language();
		$billing_address  = $payment->get_billing_address();
		$delivery_address = $payment->get_shipping_address();

		$order_id = $this->get_order_id( $payment );

		$return_url = $this->get_return_url( $payment ); // TODO try to implement it using wp api

		$data['merchant_id']    = $this->config->merchant_id;
		$data['order_id']       = $order_id;
		$data['currency']       = $payment->get_total_amount()->get_currency()->get_alphabetic_code();
		$data['amount']         = $payment->get_total_amount()->number_format( null, '.', '' );
		$data['redirect_url']   = $return_url;
		$data['cancel_url']     = $return_url;
		$data['language']       = $language;
		$data['payment_option'] = PaymentMethods::transform( $payment->get_payment_method() );
		// $data['integration_type'] = 'iframe_normal'; // Parameter to activate iframe layout.

		$data['billing_name']  = substr( trim( ( html_entity_decode( $customer->get_name(), ENT_QUOTES, 'UTF-8' ) ) ), 0, 50 );
		$data['billing_email'] = $customer->get_email();

		if ( isset( $billing_address ) ) {
			$data['billing_address'] = $billing_address->get_line_1();
			$data['billing_city']    = $this->get_clean_string( '/[^a-zA-Z\s]/', $billing_address->get_city() );
			$data['billing_state']   = $this->get_clean_string( '/[^a-zA-Z\s]/', $billing_address->get_region() );
			$data['billing_zip']     = $this->get_clean_string( '/[^a-zA-Z0-9\s]/', $billing_address->get_postal_code() );
			$data['billing_country'] = $this->get_clean_string( '/[^a-zA-Z\s]/', Utils::get_country_name( $billing_address->get_country() ) );
			$data['billing_tel']     = $billing_address->get_phone();
		}

		if ( isset( $delivery_address ) ) {
			$data['delivery_name']    = substr( trim( ( html_entity_decode( $delivery_address->get_name(), ENT_QUOTES, 'UTF-8' ) ) ), 0, 50 );
			$data['delivery_address'] = $delivery_address->get_line_1();
			$data['delivery_city']    = $this->get_clean_string( '/[^a-zA-Z\s]/', $delivery_address->get_city() );
			$data['delivery_state']   = $this->get_clean_string( '/[^a-zA-Z\s]/', $delivery_address->get_region() );
			$data['delivery_zip']     = $this->get_clean_string( '/[^a-zA-Z0-9\s]/', $delivery_address->get_postal_code() );
			$data['delivery_country'] = $this->get_clean_string( '/[^a-zA-Z\s]/', Utils::get_country_name( $delivery_address->get_country() ) );
			$data['delivery_tel']     = $delivery_address->get_phone();
		}

		$data['merchant_param1'] = $payment->get_id();
		$data['tid']             = $payment->get_id();

		$merchant_data = '';
		foreach ( $data as $key => $value ) {
			$merchant_data .= $key . '=' . $value . '&';
		}

		$encrypted_data = $this->api->encrypt( $merchant_data ); // Method for encrypting the data.

		return [
			'encRequest'  => $encrypted_data,
			'access_code' => $access_code,
		];
	}

	/**
	 * Update status of the specified payment.
	 *
	 * @param Payment $payment Payment.
	 * @throws Exception If error occurs while updating payment status.
	 */
	public function update_status( Payment $payment ) {
		try {
			if ( filter_has_var( INPUT_POST, 'encResp' ) && filter_has_var( INPUT_POST, 'orderNo' ) ) {
				$order_status = $this->get_order_response( $payment );
			} else {
				$order_status = $this->api->get_order_status( $this->get_order_id( $payment ) );
			}
		} catch ( Exception $e ) {
			throw new Exception( $e->getMessage() );
		}

		$payment_status = Statuses::transform( $order_status['order_status'] );
		if ( Statuses::SUCCESS === $payment_status ) {
			$payment->set_transaction_id( $order_status['reference_no'] );
		} elseif ( isset( $order_status['order_bank_response'] ) ) {
			$failure_reason = new FailureReason();
			$failure_reason->set_message( $order_status['order_bank_response'] );
			$payment->set_failure_reason( $failure_reason );
		}
		$payment->set_status( $payment_status );
		$payment->add_note( '<strong>CCAvenue Response:</strong><br><pre>' . print_r( $order_status, true ) . '</pre><br>' );
	}

	private function get_order_response( $payment ) {
		$post_order_no     = \sanitize_text_field( \wp_unslash( $_POST['orderNo'] ) );
		$post_enc_response = \sanitize_text_field( \wp_unslash( $_POST['encResp'] ) );
		$post_access_code  = \sanitize_text_field( \wp_unslash( $_POST['accessCode'] ) );
		$response_array    = [];

		if ( $this->get_order_id( $payment ) !== $post_order_no ) {
			throw new Exception( 'Order ID missmatch' );
		} elseif ( $post_access_code !== $this->config->access_code ) {
			throw new Exception( 'Access Code missmatch' );
		}

		$decrypted_response = $this->api->decrypt( $post_enc_response, $this->config->working_key );
		parse_str( $decrypted_response, $response_array );

		if ( $response_array['order_id'] !== $post_order_no ) {
			throw new Exception( 'Access Code missmatch' );
		}

		$response_array['reference_no']        = $response_array['tracking_id'];
		$response_array['order_bank_response'] = $response_array['status_message'];

		unset( $response_array['tracking_id'] );
		unset( $response_array['status_message'] );

		return $response_array;
	}

	private function get_return_url( Payment $payment ) {
		$return_url = remove_query_arg( [ 'key', 'payment' ], $payment->get_return_url() );
		return add_query_arg( 'kp_ccavenue_payment_id', $payment->get_id(), $return_url );
	}

	private function get_order_id( Payment $payment ) {
		// Replacements.
		$replacements = [
			'{transaction_id}'      => $payment->get_transaction_id(),
			'{payment_description}' => $payment->get_description(),
			'{order_id}'            => $payment->get_order_id(),
			'{source_id}'           => $payment->get_source_id(),
		];

		$ccavenue_order_id = strtr( $this->config->order_id_format, $replacements );
		$ccavenue_order_id = str_replace( ' ', '_', $ccavenue_order_id );
		$ccavenue_order_id = substr( trim( ( html_entity_decode( $ccavenue_order_id, ENT_QUOTES, 'UTF-8' ) ) ), 0, 29 );

		return $ccavenue_order_id;
	}

	private function get_clean_string( $pattern, $subject ) {
		if ( null === $subject ) {
			return '';
		}

		return preg_replace( $pattern, ' ', $subject );
	}
}
