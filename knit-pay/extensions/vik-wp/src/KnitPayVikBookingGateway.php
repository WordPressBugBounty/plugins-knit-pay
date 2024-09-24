<?php

namespace KnitPay\Extensions\VikWP;

use Pronamic\WordPress\Money\Currency;
use Pronamic\WordPress\Money\Money;
use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Payments\Payment;
use JPaymentStatus;
use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;
use JFactory;


/**
 * Title: Vik WP Gateway
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   6.69.0.0
 */

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit();

class KnitPayVikBookingGateway extends AbstractKnitPayPayment {

	/**
	 * @var string
	 */
	public $id = 'knit_pay';

	/**
	 * Payment method.
	 *
	 * @var string
	 */
	private $payment_method;
	
	protected function buildAdminParameters() {
		return [
			'payment_description' => [
				'label' => __( 'Payment Description', 'knit-pay-lang' ),
				'type'  => 'text',
				'help'  => sprintf( __( 'Available tags: %s', 'knit-pay-lang' ), sprintf( '<code>%s</code> <code>%s</code> <code>%s</code>', '{order_id}', '{room_name}', '{transaction_name}' ) ),
			],
			'config_id'           => [
				'label'   => __( 'Configuration', 'knit-pay-lang' ),
				'type'    => 'select',
				'options' => Plugin::get_config_select_options( $this->payment_method ),
				'help'    => 'Configurations can be created in Knit Pay gateway configurations page at <a href="' . admin_url() . 'edit.php?post_type=pronamic_gateway">"Knit Pay >> Configurations"</a>.',
			],
		];
	}
	
	protected function beginTransaction() {
		$config_id      = $this->getParam( 'config_id' );
		$payment_method = $this->id;
		
		// Use default gateway if no configuration has been set.
		if ( empty( $config_id ) ) {
			$config_id = get_option( 'pronamic_pay_config_id' );
		}
		
		$gateway = Plugin::get_gateway( $config_id );
		
		if ( ! $gateway ) {
			return false;
		}
		
		$order_id = $this->get( 'id' );
		
		/**
		 * Build payment.
		 */
		$payment = new Payment();
		
		$payment->source    = 'vik-wp';
		$payment->source_id = $order_id;
		$payment->order_id  = $order_id;
		
		$payment->set_description( Helper::get_description( $this ) );
		
		$payment->title = Helper::get_title( $order_id );
		
		// Customer.
		$payment->set_customer( Helper::get_customer_from_order( $this ) );
		
		// Address.
		$payment->set_billing_address( Helper::get_address_from_order( $this ) );
		
		// Currency.
		$currency = Currency::get_instance( $this->get( 'transaction_currency' ) );

		// Amount.
		$payment->set_total_amount( new Money( $this->get( 'total_to_pay' ), $currency ) );
		
		// Method.
		$payment->set_payment_method( $payment_method );
		
		// Configuration.
		$payment->config_id = $config_id;
		
		try {
			$payment = Plugin::start_payment( $payment );
	
			$payment->set_meta( 'vik_return_url', $this->get( 'return_url' ) );
			$payment->save();
			
			$form  = '<form action="' . $payment->get_pay_redirect_url() . '" method="post">';
			$form .= '<input type="submit" name="_submit" value="Pay Now!" />';
			$form .= '</form>';
			
			echo $form;
		} catch ( \Exception $e ) {
			die();// TODO
			llms_add_notice( Plugin::get_default_error_message(), 'error' );
			llms_add_notice( $e->getMessage(), 'error' );
			$order->set_status( 'llms-failed' );
			return;
		}
	}
	
	protected function validateTransaction( JPaymentStatus &$status ) {
		if ( ! filter_has_var( INPUT_GET, 'payment_id' ) ) {
			$status->appendLog( 'Payment ID is missing' );
			return false;
		}
		
		$payment_id = filter_input( INPUT_GET, 'payment_id', FILTER_SANITIZE_NUMBER_INT );
		
		$payment = get_pronamic_payment( $payment_id );
		
		if ( null === $payment ) {
			return false;
		}
		
		$order_id = $payment->get_order_id();
		
		if ( $order_id !== $this->get( 'id' ) ) {
			$status->appendLog( 'Provided Payment ID does not below to this order' );
			return;
		}
		
		switch ( $payment->get_status() ) {
			case Core_Statuses::CANCELLED:
			case Core_Statuses::EXPIRED:
				$status->appendLog( 'Payment Cancelled.' );
				return false;
				break;
			case Core_Statuses::FAILURE:
				$status->appendLog( 'Payment Failed.' );
				return false;
				
				break;
			case Core_Statuses::SUCCESS:
				$status->appendLog( 'Payment is Successful. Knit Pay Payment ID: ' . $payment->get_id() . '. Transaction ID: ' . $payment->get_transaction_id() );
				$status->verified();
				/** Set a value for the value paid */
				$status->paid( $payment->get_total_amount()->get_value() );
				
				break;
			case Core_Statuses::OPEN:
			default:
				$status->appendLog( 'Payment is Pending.' );
				return false;
		}
		
		return true;
	}   
	
}
