<?php
namespace KnitPay\Gateways\PaymarkOE;

use Pronamic\WordPress\Pay\Core\Gateway as Core_Gateway;
use Exception;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;
use WP_Error;

require_once 'lib/API.php';

/**
 * Title: Paymark OE Gateway
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 5.2.0
 * @since 5.2.0
 */
class Gateway extends Core_Gateway {

	/**
	 * Initializes an Cashfree gateway
	 *
	 * @param Config $config
	 *            Config.
	 */
	public function init( Config $config ) {


		$this->set_method( self::METHOD_HTML_FORM );

		// Supported features.
		$this->supports = [
			'payment_status_request',
		];

		$this->test_mode = 0;
		if ( self::MODE_TEST === $config->mode ) {
			$this->test_mode = 1;
		}

		$this->api = new API( $config->consumer_key, $config->consumer_secret, $config->merchant_id_code, $this->test_mode );
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
		$payment_currency = $payment->get_total_amount()->get_currency()->get_alphabetic_code();
		if ( isset( $payment_currency ) && 'NZD' !== $payment_currency ) {
			$currency_error = 'Paymark - Online EFTPOS only accepts payments in NZD. If you are a store owner, kindly activate NZD currency for ' . $payment->get_source() . ' plugin.';
			throw new \Exception( $currency_error );
		}

		if ( strpos( home_url( '/' ), 'https' ) !== 0 ) {
			$ssl_error = 'SSL is mandatory on the website.';
			throw new \Exception( $ssl_error );
		}


			$this->session_id = $this->api->create_session( $this->get_payment_data( $payment ) );

			$payment->set_transaction_id( $this->session_id );
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
		$payment_currency = $payment->get_total_amount()->get_currency()->get_alphabetic_code();

		return [
			'amount'      => intval( strval( $payment->get_total_amount()->number_format( null, '.', '' ) * 100 ) ),
			'redirectUrl' => preg_replace( '/^http:/i', 'https:', $payment->get_return_url() ), // Paymark EO does not allow redirection on http URL
			'currency'    => $payment_currency,
			'description' => $payment->get_description(),
			'orderId'     => $payment->get_order_id(),
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

			$session = $this->api->get_session( $payment->get_transaction_id() );
			$payment->add_note( 'Session Status: ' . $session->status );
		if ( 'PAYMENT_PROCESSED' !== $session->status ) {
			return;
		}

			$paymark_payment = $this->api->get_payment( $payment->get_transaction_id() );

		if ( isset( $paymark_payment->status ) ) {
			if ( Statuses::AUTHORISED === $paymark_payment->status ) {
				$payment->set_transaction_id( $paymark_payment->id );
			}

			$payment->set_status( Statuses::transform( $paymark_payment->status ) );
			$payment->add_note( 'Paymark Session Status: ' . $session->status . '<br>Payment Status: ' . $paymark_payment->status . '<br>Session ID: ' . $session->id . '<br>Payment ID: ' . $paymark_payment->id );
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
		$this->output_form( $payment );

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

		$html  = '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
		$html .= '<div id="embed-openjs" class="embed-openjs-wrap"></div>';

		$script  = '<script type="text/javascript" src="' . $this->api->get_open_plugin_url() . '"></script>';
		$script .= "
        <script>
            const sessionId = '{$payment->get_transaction_id()}'
            if (window.openjs) {
            window.openjs.init({
                sessionId: sessionId,
                elementId: 'embed-openjs',
            })
            }
        </script>";

		echo $html . $script;
	}
}
