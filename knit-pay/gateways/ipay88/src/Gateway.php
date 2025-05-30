<?php
namespace KnitPay\Gateways\IPay88;

use KnitPay\Utils;
use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Core\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Core\PaymentMethod;
use Pronamic\WordPress\Pay\Payments\FailureReason;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;
use Exception;

/**
 * Title: iPay88 Gateway
 * Copyright: 2020-2025 Knit Pay
 *
 * @author Knit Pay
 * @version 8.96.0.0
 * @since 8.96.0.0
 */
class Gateway extends Core_Gateway {
	private $config;
	private $api;

	/**
	 * Initializes an iPay88 gateway
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
		];

		$this->api = new API( $config, $this->get_mode() );
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

		$payment->set_action_url( $this->api->get_endpoint_url() );
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
		$customer = $payment->get_customer();

		$merchant_code  = $this->config->merchant_code;
		$ref_no         = $payment->get_transaction_id();
		$amount         = $payment->get_total_amount()->number_format( null, '.', '' );
		$currency       = $payment->get_total_amount()->get_currency()->get_alphabetic_code();
		$prod_desc      = $payment->get_description();
		$user_name      = substr( trim( ( html_entity_decode( $customer->get_name(), ENT_QUOTES, 'UTF-8' ) ) ), 0, 100 );
		$user_email     = $customer->get_email();
		$user_contact   = '';
		$remark         = $payment->get_description();
		$signature_type = 'HMACSHA512';
		$response_url   = $payment->get_return_url();
		$backend_url    = add_query_arg( 'kp_ipay88_webhook', '', $response_url );

		if ( isset( $billing_address ) ) {
			$user_contact = $billing_address->get_phone();
		}

		return [
			'MerchantCode'  => $merchant_code,
			'RefNo'         => $ref_no,
			'Amount'        => $amount,
			'Currency'      => $currency,
			'ProdDesc'      => $prod_desc,
			'UserName'      => $user_name,
			'UserEmail'     => $user_email,
			'UserContact'   => $user_contact,
			'Remark'        => $remark,
			'SignatureType' => $signature_type,
			'Signature'     => $this->api->get_signature( [ $ref_no, $amount, $currency ] ),
			'ResponseURL'   => $response_url,
			'BackendURL'    => $backend_url,
		];
	}

	/**
	 * Update status of the specified payment.
	 *
	 * @param Payment $payment Payment.
	 * @throws Exception If error occurs while updating payment status.
	 */
	public function update_status( Payment $payment ) {
		if ( PaymentStatus::SUCCESS === $payment->get_status() ) {
			return;
		}

		try {
			if ( filter_has_var( INPUT_POST, 'TransId' ) && filter_has_var( INPUT_POST, 'Status' ) ) {
				$transaction_details = $this->get_transaction_response( $payment );
			} else {
				$transaction_data    = [
					'ReferenceNo' => $payment->get_transaction_id(),
					'Amount'      => $payment->get_total_amount()->number_format( null, '.', '' ),
				];
				$transaction_details = $this->api->get_transaction_details( $transaction_data );
			}
		} catch ( Exception $e ) {
			throw new Exception( $e->getMessage() );
		}

		$payment_status = Statuses::transform( $transaction_details['Status'] );
		if ( PaymentStatus::SUCCESS === $payment_status ) {
			$payment->set_transaction_id( $transaction_details['TransId'] );
		} elseif ( PaymentStatus::FAILURE === $payment_status ) {
			$failure_reason    = new FailureReason();
			$error_description = isset( $transaction_details['ErrDesc'] ) ? $transaction_details['ErrDesc'] : $transaction_details['Errdesc'];
			$failure_reason->set_message( $error_description );
			$payment->set_failure_reason( $failure_reason );
		}
		$payment->set_status( $payment_status );
		$payment->add_note( '<strong>iPay88 Response:</strong><br><pre>' . print_r( $transaction_details, true ) . '</pre><br>' );
	}

	private function get_transaction_response( $payment ) {
		$post_merchant_code = \sanitize_text_field( \wp_unslash( $_POST['MerchantCode'] ) );
		$post_ref_no        = \sanitize_text_field( \wp_unslash( $_POST['RefNo'] ) );
		$post_signature     = \sanitize_text_field( \wp_unslash( $_POST['Signature'] ) );

		$signature_array = [
			\sanitize_text_field( \wp_unslash( $_POST['PaymentId'] ) ),
			$post_ref_no,
			\sanitize_text_field( \wp_unslash( $_POST['Amount'] ) ),
			\sanitize_text_field( \wp_unslash( $_POST['Currency'] ) ),
			\sanitize_text_field( \wp_unslash( $_POST['Status'] ) ),
		];
		$generated_hash  = $this->api->get_signature( $signature_array, 2 );

		if ( $generated_hash !== $post_signature ) {
			throw new Exception( 'Signature missmatch' );
		} elseif ( $payment->get_transaction_id() !== $post_ref_no ) {
			throw new Exception( 'Ref no missmatch.' );
		} elseif ( $post_merchant_code !== $this->config->merchant_code ) {
			throw new Exception( 'Merchant Code missmatch' );
		}

		return $_POST;
	}
}
