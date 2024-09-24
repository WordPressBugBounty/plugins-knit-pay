<?php
namespace KnitPay\Gateways\Sodexo;

use Pronamic\WordPress\Pay\Core\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Core\PaymentMethod;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;
use Pronamic\WordPress\Pay\Refunds\Refund;
use Exception;

require_once 'lib/API.php';


/**
 * Title: Sodexo Gateway
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 1.0.0
 * @since 3.3.0
 */
class Gateway extends Core_Gateway {
	private $config;
	private $test_mode;
	private $api;

	const NAME = 'sodexo';

	/**
	 * Initializes an Sodexo gateway
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

		$this->test_mode = 0;
		if ( self::MODE_TEST === $config->mode ) {
			$this->test_mode = 1;
		}

		$this->api = new API( $this->config->api_keys, $this->test_mode );

		$this->register_payment_methods();
	}

	private function register_payment_methods() {
		$this->register_payment_method( new PaymentMethod( PaymentMethods::SODEXO ) );
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
		$payment_currency = $payment->get_total_amount()
		->get_currency()
		->get_alphabetic_code();
		if ( isset( $payment_currency ) && 'INR' !== $payment_currency ) {
			$currency_error = 'Sodexo only accepts payments in Indian Rupees. If you are a store owner, kindly activate INR currency for ' . $payment->get_source() . ' plugin.';
			throw new Exception( $currency_error );
		}

		$transaction = $this->api->create_transaction( $this->get_payment_data( $payment ) );

		$payment->set_transaction_id( $transaction->transactionId );
		$payment->set_action_url( $transaction->redirectUserTo );
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
		/*
		 $sodexo_source_id = get_user_meta( $payment->user_id, 'sodexo_source_id', true );
		if ( ! $sodexo_source_id ) {
			$sodexo_source_id = null;
		} */

		$payment_currency = $payment->get_total_amount()->get_currency()->get_alphabetic_code();

		$requestId           = $payment->key . '_' . $payment->get_id();
		$amount              = [
			'value'    => $payment->get_total_amount()->number_format( null, '.', '' ),
			'currency' => $payment_currency,
		];
		$merchantInfo['aid'] = $this->config->aid;
		$merchantInfo['mid'] = $this->config->mid;
		$merchantInfo['tid'] = $this->config->tid;
		$returnUrl           = $payment->get_return_url();

		$data = [
			'requestId'    => $requestId,
			'sourceType'   => 'CARD', // TODO: Give admin/user option to choose
			// 'sourceId'     => $sodexo_source_id,
			'amount'       => $amount,
			'merchantInfo' => $merchantInfo,
			'purposes'     => [
				[
					'purpose' => 'FOOD',
					'amount'  => $amount,
				],
			],
			'failureUrl'   => $returnUrl,
			'successUrl'   => $returnUrl,
		];

		return wp_json_encode( $data );
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

		$transaction_details = $this->api->get_transaction_details( $payment->get_transaction_id() );

		if ( ! empty( $transaction_details->sourceId ) ) {
			unset( $transaction_details->sourceId );
			// update_user_meta( $payment->user_id, 'sodexo_source_id', $transaction_details->sourceId );
		}

		if ( isset( $transaction_details->transactionState ) ) {
			$payment->set_status( Statuses::transform( $transaction_details->transactionState ) );

			$note = 'Sodexo Transaction State: ' . $transaction_details->transactionState;
			if ( isset( $transaction_details->failureReason ) ) {
				$note .= '<br>failureReason: ' . $transaction_details->failureReason;
			}

			$payment->add_note( $note );
		}
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

		$data = [
			'requestId'     => uniqid( 'refund_' ),
			'amount'        => [
				'currency' => $amount->get_currency()->get_alphabetic_code(),
				'value'    => $amount->number_format( null, '.', '' ),
			],
			'transactionId' => $transaction_id,
		];

		$data['purposes'] = [
			[
				'purpose' => 'FOOD',
				'amount'  => $data['amount'],
			],
		];

		$refund->psp_id = $this->api->refund_transaction( wp_json_encode( $data ) );
	}
}
