<?php
namespace KnitPay\Gateways;

use Pronamic\WordPress\Pay\Core\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Core\GatewayConfig;
use Pronamic\WordPress\Pay\Core\Util as Core_Util;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;
use KnitPay\Utils;
use Pronamic\WordPress\Number\Number;

/**
 * Title: Custom Redirect Page Gateway
 * Copyright: 2020-2026 Knit Pay
 *
 * @author Knit Pay
 * @version 1.0.0
 * @since 4.1.0
 */
class Gateway extends Core_Gateway {
	protected $is_iframe_checkout_method;

	public $default_currency;
	public $supported_currencies;
	
	/**
	 * The method of this gateway
	 *
	 * @var int
	 */
	public $method;
	
	protected $payment_page_title;
	protected $payment_page_description;

	/**
	 * Constructs and initializes Gateway
	 *
	 * @param GatewayConfig $config
	 *            Config.
	 */
	public function __construct() {
		parent::__construct();

		$this->payment_page_title       = 'Redirecting…';
		$this->payment_page_description = '<p>You will be automatically redirected to the online payment environment.</p><p>Please click the button below if you are not automatically redirected.</p>';
	}

	public function change_currency( Payment $payment, $default_currency, $exchange_rate ) {
		$payment_currency = $payment->get_total_amount()->get_currency()->get_alphabetic_code();

		if ( empty( $default_currency ) ) {
			return;
		} elseif ( $default_currency === $payment_currency && 1.0 === floatval( $exchange_rate ) ) {
			return;
		}

		$exchange_rate = new Number( $exchange_rate );
		$total_amount  = $payment->get_total_amount();
		$total_amount->set_currency( $default_currency );
		$total_amount = $total_amount->multiply( $exchange_rate );
		$payment->set_total_amount( $total_amount );

		if ( ! is_null( $payment->get_lines() ) ) {
			$line_iterator = $payment->get_lines()->getIterator();
			foreach ( $line_iterator as $key => $payment_line ) {
				if ( ! is_null( $payment_line->get_total_amount() ) ) {
					$payment_line->get_total_amount()->set_currency( $default_currency );
					$payment_line->set_total_amount( $payment_line->get_total_amount()->multiply( $exchange_rate ) );
				}

				if ( ! is_null( $payment_line->get_discount_amount() ) ) {
					$payment_line->get_discount_amount()->set_currency( $default_currency );
					$payment_line->set_discount_amount( $payment_line->get_discount_amount()->multiply( $exchange_rate ) );
				}

				if ( ! is_null( $payment_line->get_unit_price() ) ) {
					$payment_line->get_unit_price()->set_currency( $default_currency );
					$payment_line->set_unit_price( $payment_line->get_unit_price()->multiply( $exchange_rate ) );
				}
			}
		}
	}
	
	/**
	 * Redirect to the gateway action URL.
	 *
	 * @param Payment $payment The payment to redirect for.
	 * @return void
	 * @throws \Exception Throws exception when action URL for HTTP redirect is empty.
	 */
	public function redirect( Payment $payment ) {
		parent::redirect( $payment );
		
		if ( $this->is_iframe_checkout_method ) {
			$this->init_iframe_checkout( $payment );
			exit;
		}
	}

	/**
	 * Redirect via HTML.
	 *
	 * @param Payment $payment The payment to redirect for.
	 * @return void
	 */
	public function redirect_via_html( Payment $payment ) {
		if ( PaymentStatus::SUCCESS === $payment->get_status() || PaymentStatus::EXPIRED === $payment->get_status() ) {
			wp_safe_redirect( $payment->get_return_redirect_url() );
			exit;
		}

		$payment_page_title       = $this->payment_page_title;
		$payment_page_description = $this->payment_page_description;

		if ( headers_sent() ) {
			parent::redirect_via_html( $payment );
		} else {
			Core_Util::no_cache();

			include KNITPAY_DIR . '/views/redirect-via-html-with-message.php';
		}

		exit;
	}
	
	/**
	 * Redirect via HTML.
	 *
	 * @param Payment $payment The payment to redirect for.
	 * @return void
	 */
	public function init_iframe_checkout( Payment $payment ) {
		if ( PaymentStatus::SUCCESS === $payment->get_status() || PaymentStatus::EXPIRED === $payment->get_status() ) {
			wp_safe_redirect( $payment->get_return_redirect_url() );
			exit;
		}
		
		$payment_page_title = __( 'Payment Page', 'knit-pay-lang' );
		
		if ( headers_sent() ) {
			parent::redirect_via_html( $payment );
		} else {
			Core_Util::no_cache();
			
			include KNITPAY_DIR . '/views/redirect-via-html-for-iframe.php';
		}
		
		exit;
	}
	
	/**
	 * Custom payment redirect.
	 * Intended to be overridden by gateway.
	 *
	 * @param Payment $payment Payment.
	 *
	 * @return void
	 */
	public function payment_redirect( Payment $payment ) {
		if ( $this->is_iframe_checkout_method ) {
			$this->init_iframe_checkout( $payment );
			exit;
		}
	}
}
