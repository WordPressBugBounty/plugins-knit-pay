<?php
namespace KnitPay\Extensions\EngineThemes;

use Pronamic\WordPress\Money\Currency;
use Pronamic\WordPress\Money\Money;
use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Payments\Payment;
use ET_Cash;
use ET_Order;
use ET_PaymentVisitor;
use WP_User;

/**
 * Title: Engine Themes Visitor
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   4.7.0
 */

class KnitPayVisitor extends ET_PaymentVisitor {

	protected $_payment_type = 'knit_pay';

	function setup_checkout( ET_Order $order ) {
		$setting = ae_get_option( 'knit_pay' );

		$config_id      = $setting['config_id'];
		$payment_method = '';

		// Use default gateway if no configuration has been set.
		if ( empty( $config_id ) ) {
			$config_id = get_option( 'pronamic_pay_config_id' );
		}

		$gateway = Plugin::get_gateway( $config_id );

		if ( ! $gateway ) {
			return false;
		}

		$order = $order->generate_data_to_pay();
		$payer = new WP_User( $order['payer'] );

		/**
		 * Build payment.
		 */
		$payment = new Payment();

		$payment->source    = Extension::SLUG;
		$payment->source_id = $order['ID'];
		$payment->order_id  = $order['ID'];

		$payment->set_description( Helper::get_description( $order, $setting ) );

		$payment->title = Helper::get_title( $order );

		// Customer.
		$payment->set_customer( Helper::get_customer( $payer ) );

		// Address.
		$payment->set_billing_address( Helper::get_address( $payer ) );

		// Currency.
		$currency = Currency::get_instance( $order['currencyCodeType'] );

		// Amount.
		$payment->set_total_amount( new Money( $order['total'], $currency ) );

		// Method.
		$payment->set_payment_method( $payment_method );

		// Configuration.
		$payment->config_id = $config_id;

		try {
			$payment = Plugin::start_payment( $payment );

			// Execute a redirect.
			$response = [
				'url'    => $payment->get_pay_redirect_url(),
				'ACK'    => true,
				'extend' => false,
			];
		} catch ( \Exception $e ) {
			$response = [
				'ACK' => false,
				'msg' => Plugin::get_default_error_message() . $e->getMessage(),
			];
		}

		return $response;
	}

	function do_checkout( $order ) {
		$order_data = $order->get_order_data();
		switch ( strtoupper( $order_data['status'] ) ) {
			case 'COMPLETED':
			case 'PUBLISH':
				$paymentStatus = 'Completed';
				break;
			case 'PROCESSING':
			case 'PENDING':
				$paymentStatus = 'Pending';
				break;
			case 'DRAFT':
				$paymentStatus = 'waiting';
				break;
			default:
				$paymentStatus = 'fraud';
				break;
		}
		
		// return
		return [
			'ACK'            => in_array( strtoupper( $order_data['status'] ), [ 'COMPLETED', 'PUBLISH', 'PROCESSING', 'PENDING' ] ),
			'payment'        => 'knit_pay',
			'payment_status' => $paymentStatus,
		];
	}
}
