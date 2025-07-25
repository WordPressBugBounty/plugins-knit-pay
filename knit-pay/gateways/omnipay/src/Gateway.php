<?php
namespace KnitPay\Gateways\Omnipay;

use Pronamic\WordPress\Pay\Core\Gateway as Core_Gateway;
use Exception;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;
use Omnipay\Common\AbstractGateway;
use Omnipay\Common\CreditCard;
use KnitPay\Utils as KnitPayUtils;

/**
 * Title: Omnipay Gateway
 * Copyright: 2020-2025 Knit Pay
 *
 * @author Knit Pay
 * @version 8.72.0.0
 * @since 8.72.0.0
 */
class Gateway extends Core_Gateway {
	/**
	 * Omnipay gateway.
	 *
	 * @var AbstractGateway
	 */
	private $omnipay_gateway;
	private $config;
	private $transaction_options;
	private $args;

	/**
	 * Initialize.
	 *
	 * @param AbstractGateway $omnipay_gateway
	 *            Omnipay gateway.
	 * @param array           $config
	 *            Configuration.
	 * @param array           $args
	 *            Arguments.
	 */
	public function init( AbstractGateway $omnipay_gateway, $config, $args ) {

		// Supported features.
		$this->supports = [
			'payment_status_request',
		];

		$transaction_options = isset( $args['transaction_options'] ) ? $args['transaction_options'] : [];

		$this->args                = $args;
		$this->omnipay_gateway     = $omnipay_gateway;
		$this->config              = $config;
		$this->transaction_options = $transaction_options;
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
		if ( isset( $this->args['supported_currencies'] ) && ! in_array( $payment_currency, $this->args['supported_currencies'] ) ) {
			throw new Exception( sprintf( 'The currency %s is not supported by this gateway.', $payment_currency ) );
		}

		if ( isset( $this->args['pre_purchase_callback'] ) ) {
			call_user_func( $this->args['pre_purchase_callback'], $this->omnipay_gateway );
		}

		$transaction_data = $this->get_payment_data( $payment );

		// Do a purchase transaction on the gateway
		$transaction = $this->omnipay_gateway->purchase( $transaction_data );
		$response    = $transaction->send();

		if ( $response->isRedirect() ) {
			$redirect_method = self::METHOD_HTTP_REDIRECT;
			if ( 'POST' === $response->getRedirectMethod() ) {
				$redirect_method = self::METHOD_HTML_FORM;
				$payment->set_meta( 'redirect_data', $response->getRedirectData() );
			}

			$payment->set_meta( 'redirect_method', $redirect_method );
			$this->set_method( $redirect_method );

			$payment->set_action_url( $response->getRedirectUrl() );
		} elseif ( $response->isSuccessful() ) {
			// Successful Payment.
			$payment->set_status( PaymentStatus::SUCCESS );
		} else {
			$payment->set_transaction_id( $payment->key . '_' . $payment->get_id() );
			if ( ! is_null( $response->getMessage() ) ) {
				 throw new Exception( $response->getMessage() );
			} elseif ( isset( $response->getData()->message ) ) {
				throw new Exception( $response->getData()->message );
			} else {
				throw new Exception( 'Something went wrong.' );
			}
		}

		if ( isset( $this->args['omnipay_transaction_id'] ) ) {
			$omnipay_transaction_id_key = ltrim( $this->args['omnipay_transaction_id'], '{data:' );
			$omnipay_transaction_id_key = rtrim( $omnipay_transaction_id_key, '}' );

			$payment->set_transaction_id( $response->getData()[ $omnipay_transaction_id_key ] );
		} elseif ( ! empty( $response->getTransactionReference() ) ) {
			$payment->set_transaction_id( $response->getTransactionReference() );
		} else {
			$payment->set_transaction_id( $transaction_data['transactionId'] );
		}

		update_post_meta( $payment->get_id(), 'omnipay_transaction_id', $payment->get_transaction_id() );
		$payment->set_meta( 'purchase_data', $response->getData() );
	}

	public function payment_redirect( Payment $payment ) {
		if ( ! is_null( $payment->get_meta( 'redirect_method' ) ) ) {
			$this->set_method( $payment->get_meta( 'redirect_method' ) );
		}
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
		return $payment->get_meta( 'redirect_data' );
	}

	/**
	 * Get Payment Data.
	 *
	 * @param Payment $payment
	 *            Payment.
	 *
	 * @return array
	 */
	private function get_payment_data( Payment $payment ) {
		$customer         = $payment->get_customer();
		$billing_address  = $payment->get_billing_address();
		$delivery_address = $payment->get_shipping_address();

		$amount              = $payment->get_total_amount()->number_format( null, '.', '' );
		$currency            = $payment->get_total_amount()->get_currency()->get_alphabetic_code();
		$payment_description = $payment->get_description();
		if ( ! empty( $payment->get_transaction_id() ) ) {
			$transaction_id = $payment->get_transaction_id();
		} else {
			$transaction_id = $payment->key . '_' . $payment->get_id();
		}
		
		$payment_return_url = $payment->get_return_url();
		$payment_cancel_url = add_query_arg( 'cancelled', true, $payment_return_url );

		// @see https://omnipay.thephpleague.com/api/cards/
		$credit_card = [
			'firstName'       => '',
			'lastName'        => '',
			'number'          => '',
			'expiryMonth'     => '',
			'expiryYear'      => '',
			'startMonth'      => '',
			'startYear'       => '',
			'cvv'             => '',
			'issueNumber'     => '',
			'type'            => '',
			'billingAddress1' => '',
			'billingAddress2' => '',
			'billingCity'     => '',
			'billingPostcode' => '',
			'billingState'    => '',
			'billingCountry'  => '',
			'billingPhone'    => '',
			'company'         => '',
			'email'           => $customer->get_email(),
		];

		if ( ! is_null( $customer->get_name() ) ) {
			$credit_card['firstName'] = $customer->get_name()->get_first_name();
			$credit_card['lastName']  = $customer->get_name()->get_last_name();
		}

		if ( ! is_null( $billing_address ) ) {
			$credit_card['billingAddress1'] = $billing_address->get_line_1();
			$credit_card['billingAddress2'] = $billing_address->get_line_2();
			$credit_card['billingCity']     = $billing_address->get_city();
			$credit_card['billingPostcode'] = $billing_address->get_postal_code();
			$credit_card['billingState']    = $billing_address->get_region();
			$credit_card['billingCountry']  = $billing_address->get_country_code();
			$credit_card['billingPhone']    = $billing_address->get_phone();
			$credit_card['company']         = $billing_address->get_company_name();
		}

		if ( ! is_null( $delivery_address ) ) {
			$credit_card['shippingAddress1'] = $delivery_address->get_line_1();
			$credit_card['shippingAddress2'] = $delivery_address->get_line_2();
			$credit_card['shippingCity']     = $delivery_address->get_city();
			$credit_card['shippingState']    = $delivery_address->get_region();
			$credit_card['shippingPostcode'] = $delivery_address->get_postal_code();
			$credit_card['shippingCountry']  = $delivery_address->get_country();
			$credit_card['shippingPhone']    = $delivery_address->get_phone();
		}
		
		$card = new CreditCard( $credit_card );
		
		// @see https://omnipay.thephpleague.com/api/authorizing/
		$transaction_data = [
			'card'                 => $card,
			'amount'               => $amount,
			'currency'             => $currency,
			'description'          => $payment_description,
			'transactionId'        => $transaction_id,
			'transactionReference' => $transaction_id,
			'clientIp'             => $customer->get_ip_address(),
			'returnUrl'            => $payment_return_url,
			'cancelUrl'            => $payment_cancel_url,
			'notifyUrl'            => $payment_return_url,
			'email'                => $customer->get_email(),
		];

		if ( isset( $this->args['accept_notification'] ) ) {
			$transaction_data['notifyUrl'] = rest_url( '/knit-pay/' . $this->args['id'] . '/v1/notification/' . wp_hash( home_url( '/' ) ) . '/' . $this->args['config_id'] . '/' );
		}

		// Replacements.
		$replacements = [
			'{customer_phone}'      => $billing_address ? $billing_address->get_phone() : '',
			'{customer_email}'      => $customer->get_email(),
			'{customer_name}'       => KnitPayUtils::substr_after_trim( html_entity_decode( $customer->get_name(), ENT_QUOTES, 'UTF-8' ), 0, 20 ),
			'{customer_language}'   => $customer->get_language(),
			'{currency}'            => $currency,
			'{amount}'              => $amount,
			'{amount_minor}'        => $payment->get_total_amount()->get_minor_units()->format( 0, '.', '' ),
			'{payment_return_url}'  => $payment_return_url,
			'{payment_cancel_url}'  => $payment_cancel_url,
			'{payment_description}' => $payment->get_description(),
			'{order_id}'            => $payment->get_order_id(),
			'{payment_id}'          => $payment->get_id(),
			'{transaction_id}'      => $transaction_id,
			'{payment_timestamp}'   => $payment->get_date()->getTimestamp(),
		];
		foreach ( $this->config as $key => $value ) {
			$replacements[ '{config:' . $key . '}' ] = $value;
		}
		if ( is_object( $payment->get_meta( 'purchase_data' ) ) ) {
			foreach ( $payment->get_meta( 'purchase_data' ) as $key => $value ) {
				$replacements[ '{data:' . $key . '}' ] = $value;
			}
		}

		foreach ( $this->transaction_options as $option_key => $option_value ) {
			if ( is_string( $option_value ) ) {
				 $transaction_data[ $option_key ] = strtr( $option_value, $replacements );
			} else {
				$transaction_data[ $option_key ] = $option_value;
			}
		}

		return $transaction_data;
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
		
		$response = $this->complete_purchase( $payment, 'complete_purchase_method' );

		// If Successful, Transaction is either Authorized or Captured.
		if ( $response->isSuccessful() ) {
			// Set Transaction ID before attempting Capture.
			$payment->set_transaction_id( $response->getTransactionReference() );

			// Capture the payment if required.
			$response = $this->complete_purchase( $payment, 'capture_purchase_method', $response, false );

			// Rechecking to make sure capture was also successful.
			if ( $response->isSuccessful() ) {
				$payment->set_status( PaymentStatus::SUCCESS );

				// Delete purchase data meta after successful payment to save the database storage.
				$payment->delete_meta( 'purchase_data' );
				$payment->delete_meta( 'redirect_data' );
				$payment->delete_meta( 'redirect_method' );
			} else {
				$payment->set_status( PaymentStatus::AUTHORIZED );
			}
		} elseif ( $response->isCancelled() || filter_has_var( INPUT_GET, 'cancelled' ) ) {
			$payment->set_status( PaymentStatus::CANCELLED );
		} elseif ( method_exists( $response, 'isDeclined' ) && $response->isDeclined() ) { // TODO, make it dynamic
			$payment->set_status( PaymentStatus::FAILURE );
		}
	}

	/**
	 * Complete Purchase.
	 *
	 * @param Payment $payment
	 *            Payment.
	 * @param string  $primary_method
	 *            Primary Method.
	 * @param mixed   $response
	 *            Response.
	 * @param bool    $try_complete_purchase
	 *            Try Complete Purchase.
	 *
	 * @return mixed
	 */
	private function complete_purchase( $payment, $primary_method, $response = null, $try_complete_purchase = true ) {
		// Do a purchase transaction on the gateway
		if ( isset( $this->args[ $primary_method ] ) ) {
			$transaction_data = $this->get_payment_data( $payment );

			$complete_purchase_method = $this->args[ $primary_method ];
			$transaction              = $this->omnipay_gateway->$complete_purchase_method( $transaction_data );
		} elseif ( $try_complete_purchase ) {
			$transaction_data = $this->get_payment_data( $payment );

			$transaction = $this->omnipay_gateway->completePurchase( $transaction_data );
		} else {
			return $response;
		}

		$response = $transaction->send();

		$payment->add_note( '<strong>Response Data:</strong><br><pre>' . print_r( $response->getData(), true ) . '</pre><br>' );
		return $response;
	}
}
