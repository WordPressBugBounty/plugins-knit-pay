<?php

namespace KnitPay\Extensions\FluentForms;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Pronamic\WordPress\Money\Currency;
use Pronamic\WordPress\Money\Money;
use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Payments\Payment;
use FluentFormPro\Payments\PaymentMethods\BaseProcessor;
use KnitPay\Extensions\FluentForms\Helper as PaymentHelper;

/**
 * Title: Fluent Forms Gateway Processor
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   8.92.0.0
 */

class GatewayProcessor extends BaseProcessor {

	public $method = 'knit_pay';

	protected $form;

	public function init() {
		add_action( 'fluentform/process_payment_' . $this->method, [ $this, 'handlePaymentAction' ], 10, 6 );
		add_action( 'fluentform/payment_frameless_' . $this->method, [ $this, 'handleSessionRedirectBack' ] );

		add_filter( 'fluentform/validate_payment_items_' . $this->method, [ $this, 'validateSubmittedItems' ], 10, 4 );
	}

	public function handlePaymentAction( $submissionId, $submissionData, $form, $methodSettings, $hasSubscriptions, $totalPayable ) {
		$this->setSubmissionId( $submissionId );
		$this->form   = $form;
		$submission   = $this->getSubmission();
		$paymentTotal = $this->getAmountTotal();

		if ( ! $paymentTotal && ! $hasSubscriptions ) {
			return false;
		}

		// Create the initial transaction here
		$transaction = $this->createInitialPendingTransaction( $submission, $hasSubscriptions );

		$this->handleRedirect( $transaction, $submission, $form, $methodSettings, $hasSubscriptions );
	}

	public function handleRedirect( $transaction, $submission, $form, $methodSettings, $hasSubscriptions ) {
		$payment_method = new KnitPayPaymentMethod( $this->method );
		$settings       = $payment_method->getGlobalSettings();

		$config_id      = $settings['config_id'];
		$payment_method = $this->method;

		// Use default gateway if no configuration has been set.
		if ( empty( $config_id ) ) {
			$config_id = get_option( 'pronamic_pay_config_id' );
		}

		$gateway = Plugin::get_gateway( $config_id );

		if ( ! $gateway ) {
			return false;
		}

		/**
		 * Build payment.
		 */
		$payment = new Payment();

		$payment->source    = 'fluent-forms';
		$payment->source_id = $submission->id;
		$payment->order_id  = $submission->id;

		$payment->set_description( PaymentHelper::get_description( $submission, $form, $settings ) );

		$payment->title = PaymentHelper::get_title( $submission->id );

		// Customer.
		$payment->set_customer( PaymentHelper::get_customer( $submission, $form ) );

		// Address.
		$payment->set_billing_address( PaymentHelper::get_address( $submission, $form ) );

		// Currency.
		$currency = Currency::get_instance( PaymentHelper::getFormCurrency( $form->id ) );

		// Amount.
		$payment->set_total_amount( new Money( $transaction->payment_total / 100, $currency ) );

		// Method.
		$payment->set_payment_method( $payment_method );

		// Configuration.
		$payment->config_id = $config_id;

		try {
			$payment = Plugin::start_payment( $payment );

			$payment->set_meta( 'fluentforms_transaction_hash', $transaction->transaction_hash );
			$payment->save();

			// Execute a redirect.
			$redirect_url = $payment->get_pay_redirect_url();
		} catch ( \Exception $e ) {
			wp_send_json_success(
				[
					'message' => $e->getMessage(),
				],
				423
			);
		}

		$logData = [
			'parent_source_id' => $submission->form_id,
			'source_type'      => 'submission_item',
			'source_id'        => $submission->id,
			'component'        => 'Payment',
			'status'           => 'info',
			'title'            => __( 'Redirect to Payment Gateway', 'knit-pay-lang' ),
			'description'      => __( 'User redirect to payment gateway for completing the payment', 'knit-pay-lang' ),
		];
		do_action( 'fluentform/log_data', $logData );

		wp_send_json_success(
			[
				'nextAction'   => 'payment',
				'actionName'   => 'normalRedirect',
				'redirect_url' => $redirect_url,
				'message'      => __( 'You are redirecting to complete the purchase. Please wait while you are redirecting....', 'knit-pay-lang' ),
				'result'       => [
					'insert_id' => $submission->id,
				],
			],
			200
		);
	}

	public function getPaymentMode( $formId = false ) {
		return '';
	}

	public function validateSubmittedItems( $errors, $paymentItems, $subscriptionItems, $form ) {
		$singleItemTotal = 0;

		foreach ( $paymentItems as $paymentItem ) {
			if ( $paymentItem['line_total'] ) {
				$singleItemTotal += $paymentItem['line_total'];
			}
		}

		if ( count( $subscriptionItems ) && ! $singleItemTotal ) {
			$errors[] = __( 'Error: We do not support subscriptions right now!', 'knit-pay-lang' );
		}
		return $errors;
	}
}
