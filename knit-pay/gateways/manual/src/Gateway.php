<?php
namespace KnitPay\Gateways\Manual;

use KnitPay\Gateways\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;


/**
 * Title: Manual Gateway
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 1.0.0
 * @since 4.5.0
 */
class Gateway extends Core_Gateway {

	/**
	 * Constructs and initializes an Manual Gateway
	 *
	 * @param Config $config
	 *            Config.
	 */
	public function __construct( Config $config ) {
		parent::__construct( $config );
		
		$this->config = $config;

		$this->set_method( self::METHOD_HTML_FORM );

		$this->payment_page_title       = $config->payment_page_title;
		$this->payment_page_description = $config->payment_page_description;
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
		$payment->set_transaction_id( $payment->get_id() );

		$payment->set_action_url( $payment->get_pay_redirect_url() );
	}

	/**
	 * Output form.
	 *
	 * @param Payment $payment Payment.
	 * @return void
	 * @throws \Exception When payment action URL is empty.
	 */
	public function output_form(
		Payment $payment
		) {
		$account_detail_page = get_post( $this->config->account_details_page );

		$form_inner = '<hr>';

		$form_inner .= $account_detail_page->post_content;

		$form_inner .= '<hr>';

		$form_inner .= '<strong>Transaction Details</strong><br><br>';
		$form_inner .= '<table style="margin: auto;"';
		$form_inner .= '<tr><td><b>Payment Description:</b></td><td>' . $payment->get_description() . '</td></tr>';
		$form_inner .= '<tr><td><b>Transaction Reference:</b></td><td>' . $payment->get_transaction_id() . '</td></tr>';
		$form_inner .= '<tr><td><b>Amount:</b></td><td>' . $payment->get_total_amount()->number_format( null, '.', '' ) . ' ' . $payment->get_total_amount()->get_currency()->get_alphabetic_code() . '</td></tr>';
		$form_inner .= '</table><br><hr>';

		$form_inner .= sprintf(
			'<input class="pronamic-pay-btn" type="submit" name="pay" value="%s" />',
			__( 'Paid', 'knit-pay-lang' )
		);
		$form_inner .= '&nbsp;&nbsp;';
		$form_inner .= sprintf(
			'<input class="pronamic-pay-btn" type="submit" name="pay" value="%s" />',
			__( 'Cancel', 'knit-pay-lang' )
		);

		echo sprintf(
			'<form id="pronamic_ideal_form" name="pronamic_ideal_form" method="post" action="%s">%s</form>',
			esc_attr( $payment->get_return_url() ),
			$form_inner
		);

	}

	/**
	 * Update status of the specified payment.
	 *
	 * @param Payment $payment
	 *            Payment.
	 */
	public function update_status( Payment $payment ) {
		$pay_action = filter_input( INPUT_POST, 'pay', FILTER_SANITIZE_STRING );
		if ( empty( $pay_action ) ) {
			return;
		}

		switch ( $pay_action ) {
			case 'Paid':
				$payment->set_status( PaymentStatus::ON_HOLD );
				break;
			case 'Cancel':
				$payment->set_status( PaymentStatus::CANCELLED );
				break;
			default:
				$payment->set_status( PaymentStatus::FAILURE );
		}
	}
}
