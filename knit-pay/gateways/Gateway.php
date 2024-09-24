<?php
namespace KnitPay\Gateways;

use Pronamic\WordPress\Pay\Core\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Core\GatewayConfig;
use Pronamic\WordPress\Pay\Core\Util as Core_Util;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;

/**
 * Title: Custom Redirect Page Gateway
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 1.0.0
 * @since 4.1.0
 */
class Gateway extends Core_Gateway {
	protected $payment_page_title;
	protected $payment_page_description;

	/**
	 * Constructs and initializes Gateway
	 *
	 * @param GatewayConfig $config
	 *            Config.
	 */
	public function __construct( GatewayConfig $config = null ) {

		parent::__construct( $config );

		$this->payment_page_title       = 'Redirecting…';
		$this->payment_page_description = '<p>You will be automatically redirected to the online payment environment.</p><p>Please click the button below if you are not automatically redirected.</p>';
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
}
