<?php
namespace KnitPay\Gateways\OpenMoney;

use Pronamic\WordPress\Pay\Core\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Core\PaymentMethod;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;
use Exception;

require_once 'lib/layer_api.php';

/**
 * Title: Open Money Gateway
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 5.3.0
 * @since 5.3.0
 */
class Gateway extends Core_Gateway {
	private $config;
	private $api;

	const NAME = 'open-money';

	/**
	 * Initializes an Open Money gateway
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

		$this->api = new LayerApi( $config->mode, $config->api_key, $config->api_secret );

		$this->register_payment_methods();
	}

	private function register_payment_methods() {
		$this->register_payment_method( new PaymentMethod( PaymentMethods::OPEN_MONEY ) );
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
		if ( ! empty( $payment->get_transaction_id() ) ) {
			return;
		}

		if ( ! defined( 'KNIT_PAY_OPEN_MONEY' ) ) {
			$error = sprintf(
				/* translators: 1: Open Money */
				__( 'Knit Pay supports %1$s with a Premium Addon. By signing up Open Money using Knit Pay\'s referral link you can get this premium addon for free. Visit the Knit Pay website (knitpay.org) to know more.', 'knit-pay-lang' ),
				__( 'Open Money', 'knit-pay-lang' )
			);
			throw new Exception( $error );
		}

		$layer_payment_token = $this->api->create_payment_token( $this->get_payment_data( $payment ) );

		if ( ! isset( $layer_payment_token['id'] ) ) {
			throw new Exception( reset( $layer_payment_token['error_data'] )[0] );
		}
		$payment->set_transaction_id( $layer_payment_token['id'] );
		$payment->set_action_url( $payment->get_pay_redirect_url() );
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

		$customer        = $payment->get_customer();
		$billing_address = $payment->get_billing_address();
		$customer_phone  = '';
		if ( ! empty( $billing_address ) && ! empty( $billing_address->get_phone() ) ) {
			$customer_phone = $billing_address->get_phone();
		}

		return [
			'amount'         => $payment->get_total_amount()->number_format( null, '.', '' ),
			'name'           => substr( trim( ( html_entity_decode( $customer->get_name(), ENT_QUOTES, 'UTF-8' ) ) ), 0, 20 ),
			'contact_number' => $customer_phone,
			'email_id'       => $customer->get_email(),
			'currency'       => $payment->get_total_amount()->get_currency()->get_alphabetic_code(),
			'mtx'            => date( 'ymd' ) . '-' . wp_rand( 1, 100 ) . '-' . $payment->get_id(),
		];
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

		$payment_details = $this->api->get_payment_details_by_token( $payment->get_transaction_id() );

		if ( isset( $payment_details['status'] ) ) {
			$payment_status = $payment_details['status'];

			if ( Statuses::CAPTURED === $payment_status ) {
				$payment->set_transaction_id( $payment_details['id'] );
			}

			$payment->set_status( Statuses::transform( $payment_status ) );
			$payment->add_note( 'Open Money Payment Status: ' . $payment_status . '<br>Token Status: ' . $payment_details['payment_token']['status'] . '<br>Payment ID: ' . $payment_details['id'] . '<br>Token ID: ' . $payment_details['payment_token']['id'] );
		}
	}

	/**
	 * Redirect via HTML.
	 *
	 * @see Core_Gateway::redirect_via_html()
	 *
	 * @param Payment $payment The payment to redirect for.
	 * @return void
	 */
	public function redirect_via_html( Payment $payment ) {
	    /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped */
		echo $this->output_form( $payment );

		exit;
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

		if ( PaymentStatus::SUCCESS === $payment->get_status() ) {
			wp_safe_redirect( $payment->get_return_redirect_url() );
		}

		$script_url = 'https://payments.open.money/layer';
		if ( self::MODE_TEST === $this->config->mode ) {
			$script_url = 'https://sandbox-payments.open.money/layer';
		}

		$html = '<meta name="viewport" content="width=device-width, initial-scale=1.0">';

		$script  = "<script id='context' type='text/javascript' src='{$script_url}'></script>";
		$script .= "
        <script>
            window.onload = function() {
            Layer.checkout({
                token: '{$payment->get_transaction_id()}',
                accesskey: '{$this->config->api_key}',
            },
            function(response) {
                window.location.href = '{$payment->get_return_url()}';
            },
            function(err) {
                alert(err);
            });
            };
        </script>";

		return $html . $script;
	}
}
