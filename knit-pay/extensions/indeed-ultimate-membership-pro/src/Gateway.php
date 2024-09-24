<?php

namespace KnitPay\Extensions\IndeedUltimateMembershipPro;

use Indeed\Ihc\Db\OrderMeta;
use Pronamic\WordPress\Money\Currency;
use Pronamic\WordPress\Money\Money;
use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;
use Indeed\Ihc\Gateways\PaymentAbstract as IHC_PaymentAbstract;

/**
 * Title: Indeed Ultimate Membership Pro Gateway
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   5.5.0
 */

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit();

class Gateway extends IHC_PaymentAbstract {

	protected $paymentType = 'knit_pay'; // slug. cannot be empty.

	protected $paymentRules = [
		'canDoRecurring'                               => false, // does current payment gateway supports recurring payments.
		'canDoTrial'                                   => false, // does current payment gateway supports trial subscription
		'canDoTrialFree'                               => false, // does current payment gateway supports free trial subscription
		'canApplyCouponOnRecurringForFirstPayment'     => false, // if current payment gateway support coupons on recurring payments only for the first transaction
		'canApplyCouponOnRecurringForFirstFreePayment' => false, // if current payment gateway support coupons with 100% discount on recurring payments only for the first transaction.
		'canApplyCouponOnRecurringForEveryPayment'     => false, // if current payment gateway support coupons on recurring payments for every transaction
		'paymentMetaSlug'                              => 'payment_knit_pay', // payment gateway slug. exenple: paypal, stripe, etc.
		'returnUrlAfterPaymentOptionName'              => '', // option name ( in wp_option table ) where it's stored the return URL after a payment is done.
		'returnUrlOnCancelPaymentOptionName'           => '', // option name ( in wp_option table ) where it's stored the return URL after a payment is canceled.
		'paymentGatewayLanguageCodeOptionName'         => '', // option name ( in wp_option table ) where it's stored the language code.
	];

	protected $stopProcess       = false;
	protected $inputData         = []; // input data from user
	protected $paymentOutputData = [];
	protected $paymentSettings   = []; // api key, some credentials used in different payment types

	protected $paymentTypeLabel = 'Knit Pay Payment Gateway'; // label of payment
	protected $errors           = [];

	/**
	 * @param none
	 * @return object
	 */
	public function charge() {
		$config_id      = 0;// TODO $this->paymentSettings
		$payment_method = $this->paymentType;

		// Use default gateway if no configuration has been set.
		if ( empty( $config_id ) ) {
			$config_id = get_option( 'pronamic_pay_config_id' );
		}

		$gateway = Plugin::get_gateway( $config_id );

		if ( ! $gateway ) {
			return false;
		}

		$order_id = $this->paymentOutputData['order_id'];

		/**
		 * Build payment.
		 */
		$payment = new Payment();

		$payment->source    = 'indeed-ultimate-membership-pro';
		$payment->source_id = $order_id;
		$payment->order_id  = $order_id;

		$payment->set_description( Helper::get_description( $this->paymentOutputData ) );

		$payment->title = Helper::get_title( $this->paymentOutputData );

		// Customer.
		$payment->set_customer( Helper::get_customer( $this->paymentOutputData ) );

		// Address.
		$payment->set_billing_address( Helper::get_address( $this->paymentOutputData ) );

		// Currency.
		$currency = Currency::get_instance( $this->paymentOutputData['currency'] );

		// Amount.
		$payment->set_total_amount( new Money( $this->paymentOutputData['amount'], $currency ) );

		// Method.
		$payment->set_payment_method( $payment_method );

		// Configuration.
		$payment->config_id = $config_id;

		try {
			$payment = Plugin::start_payment( $payment );

			$orderMeta = new OrderMeta();
			$orderMeta->save( $this->paymentOutputData['order_id'], 'order_identificator', $payment->get_id() );

			$this->redirectUrl = $payment->get_pay_redirect_url();
		} catch ( \Exception $e ) {
			echo $e->getMessage();
			// TODO not working properly.
			$this->stopProcess = true;
			$this->errors[]    = $e->getMessage();
		}

			return $this;
	}

	public function webhook() {
		if ( ! filter_has_var( INPUT_GET, 'payment_id' ) ) {
			return;
		}

		$payment_id = filter_input( INPUT_GET, 'payment_id', FILTER_SANITIZE_NUMBER_INT );

		$payment = get_pronamic_payment( $payment_id );

		if ( null === $payment ) {
			return;
		}

		$order_id            = intval( $payment->get_order_id() );
		$orders              = new \Indeed\Ihc\Db\Orders();
		$order               = $orders->setId( $order_id )->fetch()->get();
		$order_meta          = new OrderMeta();
		$order_identificator = $order_meta->get( $order_id, 'code' );

		switch ( $payment->get_status() ) {
			case Core_Statuses::CANCELLED:
			case Core_Statuses::EXPIRED:
				$payment_status = 'cancel';
				break;
			case Core_Statuses::FAILURE:
				$payment_status = 'failed';

				break;
			case Core_Statuses::SUCCESS:
				$payment_status = 'completed';

				break;
			case Core_Statuses::OPEN:
				$payment_status = 'pending';

				break;
			default:
				$payment_status = 'other';
		}

		$this->webhookData = [
			'payment_status'      => $payment_status,
			'transaction_id'      => $payment->get_transaction_id(),
			'uid'                 => $payment->get_customer()->get_user_id(),
			'lid'                 => isset( $order->lid ) ? $order->lid : 0,
			'order_identificator' => $payment->get_id(),
			'amount'              => $payment->get_total_amount()->get_value(),
			'currency'            => $payment->get_total_amount()->get_currency()->get_alphabetic_code(),
		];
	}

	/**
	 * @param int
	 * @param int
	 */
	public function afterRefund( $uid = 0, $lid = 0 ) {

	}

	/**
	 * @param int
	 * @param int
	 */
	public function cancelSubscription( $uid = 0, $lid = 0, $transactionId = '' ) {

	}
}
