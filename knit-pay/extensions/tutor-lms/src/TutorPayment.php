<?php

namespace KnitPay\Extensions\TutorLMS;

use Throwable;
use ErrorException;
use Ollyo\PaymentHub\Core\Payment\BasePayment;
use Pronamic\WordPress\Money\Currency;
use Pronamic\WordPress\Money\Money;
use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Payments\Payment;

/**
 * Title: TutorPayment
 * Description:
 * Copyright: 2020-2026 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   8.97.0.0
 */
class TutorPayment extends BasePayment {

	/**
	 * Checks if all required configuration keys are present and not empty.
	 *
	 * @return bool Returns true if all required configuration keys are present and not empty, otherwise false.
	 */
	public function check(): bool {
		return true;
	}


	/**
	 * Initializes the necessary configurations for the custom payment gateway.
	 */
	public function setup(): void {
	}   

	/**
	 * Creates the payment process by sending the necessary data to the payment gateway.
	 */
	public function createPayment() {
		try {
			$config_id           = $this->config->get( 'config_id' );
			$payment_description = $this->config->get( 'payment_description' );
			$payment_method      = 'knit_pay'; // TODO: Make this dynamic based on the payment method selected by the user.

			// Use default gateway if no configuration has been set.
			if ( empty( $config_id ) ) {
				$config_id = get_option( 'pronamic_pay_config_id' );
			}

			$gateway = Plugin::get_gateway( $config_id );

			if ( ! $gateway ) {
				return false;
			}

			// Retrieve payment data that was set using setData() earlier
			$payment_data = $this->getData();
			$order_id     = $payment_data->order_id;

			/**
			 * Build payment.
			 */
			$payment = new Payment();

			$payment->source    = 'tutor-lms';
			$payment->source_id = $order_id;
			$payment->order_id  = $order_id;

			$payment->set_description( Helper::get_description( $payment_description, $order_id ) );

			$payment->title = Helper::get_title( $order_id );

			// Customer.
			$payment->set_customer( Helper::get_customer( $payment_data ) );

			// Address.
			$payment->set_billing_address( Helper::get_address( $payment_data ) );

			// Currency.
			$currency = Currency::get_instance( Helper::get_currency( $payment_data ) );

			// Amount.
			$payment->set_total_amount( new Money( $payment_data->total_price, $currency ) );

			// Method.
			$payment->set_payment_method( $payment_method );

			// Configuration.
			$payment->config_id = $config_id;

			$payment = Plugin::start_payment( $payment );

			wp_safe_redirect( $payment->get_pay_redirect_url() );
		} catch ( \Exception $e ) {
			throw new ErrorException( $e->getMessage() );
		}
	}

	/**
	 * 
	 * Verifies and processes the order data received from the payment gateway.
	 * 
	 * Not used in Knit Pay.
	 *
	 * @param  object $payload  An associative array with (object) ['get' => $_GET, 'post' => $_POST, 'server' => $_SERVER, 'stream' => file_get_contents('php://input')]
	 * @return object
	 * @throws Throwable
	 */
	public function verifyAndCreateOrderData( object $payload ): object {
		return (object) [];
	}
}
