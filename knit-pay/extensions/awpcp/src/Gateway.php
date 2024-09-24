<?php

namespace KnitPay\Extensions\AWPCP;

use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;
use Pronamic\WordPress\Money\Currency;
use Pronamic\WordPress\Money\Money;
use Pronamic\WordPress\Pay\Payments\Payment;
use AWPCP_PaymentGateway;
use AWPCP_Payment_Transaction;

/**
 * Title: AWP Classifieds extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   2.5
 */

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit();

class Gateway extends AWPCP_PaymentGateway {
	protected $payment_description;

	/**
	 * @var string
	 */
	public $id = 'knit_pay';

	public function process_payment( $transaction ) {
		if ( isset( $_POST['cancel'] ) ) {
			$payments   = awpcp_payments_api();
			$cancel_url = $payments->get_cancel_url( $transaction );
			wp_redirect( $cancel_url );
			exit;
		}
		if ( isset( $_POST['step'] ) && 'checkout' === $_POST['step'] ) {
			return $this->render_knitpay_billing_form( $transaction );
		}

		$config_id      = get_awpcp_option( 'knit_pay_config_id' );
		$payment_method = $this->id;

		// Use default gateway if no configuration has been set.
		if ( empty( $config_id ) ) {
			$config_id = get_option( 'pronamic_pay_config_id' );
		}

		$gateway = Plugin::get_gateway( $config_id );

		if ( ! $gateway ) {
			return false;
		}

		$ad_id = $transaction->get( 'ad-id' );

		/**
		 * Build payment.
		 */
		$payment = new Payment();
		$helper  = new Helper( $transaction );

		$payment->source    = 'awp-classifieds';
		$payment->source_id = $ad_id;
		$payment->order_id  = $transaction->id;

		$payment->set_description( $helper->get_description( $ad_id ) );

		$payment->title = $helper->get_title( $ad_id );

		// Customer.
		$payment->set_customer( $helper->get_customer() );

		// Address.
		$payment->set_billing_address( $helper->get_address() );

		// Currency.
		$currency = Currency::get_instance( \get_awpcp_option( 'currency-code' ) );

		// Amount.
		$payment->set_total_amount( new Money( $transaction->get_totals()['money'], $currency ) );

		// Method.
		$payment->set_payment_method( $payment_method );

		// Configuration.
		$payment->config_id = $config_id;

		try {
			$payment = Plugin::start_payment( $payment );
			
			update_post_meta( $payment->get_id(), '_pronamic_payment_order_id', $payment->order_id );

			// Execute a redirect.
			wp_redirect( $payment->get_pay_redirect_url() );
			exit();
		} catch ( \Exception $e ) {
			$knitpay_error = $e->getMessage();
			return $this->render_knitpay_billing_form( $transaction, $knitpay_error );
		}

		return '';
	}

	public function process_payment_notification( $transaction ) {
		return $this->process_payment_completed( $transaction );
	}

	public function process_payment_completed( $transaction ) {
		$payment = get_pronamic_payment_by_meta( '_pronamic_payment_order_id', get_query_var( 'awpcp-txn' ) );

		switch ( $payment->get_status() ) {
			case PaymentStatus::CANCELLED:
			case PaymentStatus::EXPIRED:
				$this->process_payment_canceled( $transaction );
				break;
			case PaymentStatus::FAILURE:
				$message                           = __( 'The payment transaction was failed.', 'knit-pay-lang' );
				$transaction->errors['validation'] = $message;
				$transaction->payment_status       = AWPCP_Payment_Transaction::PAYMENT_STATUS_FAILED;
				awpcp_payment_failed_email( $transaction, $message );
				break;

			case PaymentStatus::SUCCESS:
				$transaction->payment_status = AWPCP_Payment_Transaction::PAYMENT_STATUS_COMPLETED;
				break;

			case PaymentStatus::AUTHORIZED:
			case PaymentStatus::OPEN:
				$transaction->payment_status = AWPCP_Payment_Transaction::PAYMENT_STATUS_NOT_VERIFIED;
				break;
			default:
				$transaction->payment_status = AWPCP_Payment_Transaction::PAYMENT_STATUS_UNKNOWN;
		}
	}

	public function get_integration_type() {
		return self::INTEGRATION_CUSTOM_FORM;
	}

	public function process_payment_canceled( $transaction ) {
		$transaction->errors[]       = __( 'The payment transaction was failed or canceled by the user.', 'knit-pay-lang' );
		$transaction->payment_status = AWPCP_Payment_Transaction::PAYMENT_STATUS_CANCELED;
	}

	/**
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	protected function render_knitpay_billing_form( $transaction, $knitpay_error = null ) {
		$errors = [];
		wp_enqueue_script( 'awpcp-billing-form' );

		$helper  = new Helper( $transaction );
		$address = $helper->get_address();

		$data = (object) [
			'first_name'     => '',
			'last_name'      => '',
			'user_email'     => '',
			'phone'          => '',
			'transaction_id' => $transaction->id,
		];

		if ( isset( $address ) ) {
			if ( null !== $address->get_name() ) {
				$data->first_name = $address->get_name()->get_first_name();
				$data->last_name  = $address->get_name()->get_last_name();
			}
			$data->user_email = $address->get_email();
			$data->phone      = $address->get_phone();
		}

		ob_start();
		include 'template/payments-billing-form.tpl.php';
		$html = ob_get_contents();
		ob_end_clean();

		return $html;
	}

}
