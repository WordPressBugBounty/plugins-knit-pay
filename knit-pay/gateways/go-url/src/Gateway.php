<?php
namespace KnitPay\Gateways\GoUrl;

use Pronamic\WordPress\Pay\Core\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Core\PaymentMethod;
use Pronamic\WordPress\Pay\Core\PaymentMethods;
use Pronamic\WordPress\Pay\Core\Util as Core_Util;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;

/**
 * Title: GoUrl Gateway
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 8.76.0.0
 * @since 8.76.0.0
 */
class Gateway extends Core_Gateway {
	/**
	 * Initializes an GoUrl gateway
	 *
	 * @param Config $config
	 *            Config.
	 */
	public function init( Config $config ) {
		$this->config = $config;

		$this->set_method( self::METHOD_HTML_FORM );

		$this->register_payment_methods();
	}

	private function register_payment_methods() {
		$this->register_payment_method( new PaymentMethod( PaymentMethods::BITCOIN ) );
	}

	/**
	 * Get available payment methods.
	 *
	 * @return array<int, string>
	 * @see Core_Gateway::get_available_payment_methods()
	 */
	public function get_available_payment_methods() {
		return $this->get_supported_payment_methods();
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
		$payment->set_transaction_id( $payment->key . '_' . $payment->get_id() );

		$payment->set_action_url( $payment->get_pay_redirect_url() );
	}

	/**
	 * Update status of the specified payment.
	 *
	 * @param Payment $payment
	 *            Payment.
	 */
	public function update_status( Payment $payment ) {
		$payment_details = $this->get_crypto_payment( $payment );

		if ( 'payment_received' !== $payment_details['status'] ) {
			return;
		}

		if ( $payment_details['is_confirmed'] ) {
			$payment->set_status( $this->config->payment_confirmed_status );
		} elseif ( $payment_details['is_paid'] ) {
			$payment->set_status( $this->config->payment_received_status );
		}

		$this->save_payment_details( $payment, $payment_details );
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
		if ( headers_sent() ) {
			parent::redirect_via_html( $payment );
		} else {
			Core_Util::no_cache();

			include KNITPAY_DIR . '/views/redirect-via-html-for-iframe.php';
		}

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
			exit;
		}

		echo $this->get_output_html( $payment );
	}

	private function get_crypto_payment( Payment $payment ) {
		global $gourl;

		$customer = $payment->get_customer();

		$plugin      = 'knitpay';
		$amount      = $payment->get_total_amount()->number_format( null, '.', '' );
		$currency    = $payment->get_total_amount()->get_currency()->get_alphabetic_code();
		$orderID     = $payment->get_transaction_id();
		$userID      = $customer->get_user_id();
		$period      = 'NOEXPIRY';
		$language    = $customer->get_language();
		$coin        = '';
		$icon_width  = 60; // TODO make dynamic
		$partner_key = 'DEV1933GB41A7CA6026EBD4G299984229'; // TODO set different partner key for different ext.

		if ( ! $userID ) {
			$userID = 'guest'; // allow guests to make checkout (payments)
		}

		if ( ! class_exists( 'gourlclass' ) || ! defined( 'GOURL' ) || ! is_object( $gourl ) ) {
			echo "Please install and activate wordpress plugin 'GoUrl Bitcoin Gateway' (https://gourl.io/bitcoin-wordpress-plugin.html) " .
				'to accept Bitcoin/Altcoin Payments on your website';
			return false;
		}

		// Convert to USD (optional, except cryptocurrencies)
		// ---------------------------------------------------
		if ( $currency != 'USD' && ! array_key_exists( $currency, $gourl->coin_names() ) ) {
			$amount   = gourl_convert_currency( $currency, 'USD', $amount );
			$currency = 'USD';
		}

		if ( $amount <= 0 ) {
			echo 'Sorry, but there was an error processing your order. Please try a different payment method.';
			return false;
		}

		// Generate payment box or return paid result
		return $gourl->cryptopayments( $plugin, $amount, $currency, $orderID, $period, $language, $coin, $partner_key, $userID, $icon_width );
	}

	public function get_output_html( Payment $payment ) {
		$result = $this->get_crypto_payment( $payment );

		if ( ! $result ) {
			return;
		} elseif ( $result['error'] ) {
			echo "<div style='color:red'>" . $result['error'] . '</div>';
		} else {
			if ( $result['is_paid'] ) {
				wp_redirect( $payment->get_return_url() );
				exit;
			}

			$payment_box = $result['html_payment_box'];
			if ( pronamic_pay_plugin()->is_debug_mode() ) {
				 $this->save_payment_details( $payment, $result );
			}

			// Display Payment Box OR display payment received result
			return $payment_box;
		}
	}

	public function save_payment_details( $payment, $payment_details ) {
		unset( $payment_details['html_payment_box'] );
		$payment->add_note( '<strong>Payment Details:</strong><br><pre>' . print_r( $payment_details, true ) . '</pre><br>' );
	}
}
