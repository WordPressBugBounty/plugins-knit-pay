<?php

namespace KnitPay\Gateways\SumUp;

use KnitPay\Gateways\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;

/**
 * Title: SumUp Gateway
 * Copyright: 2020-2026 Knit Pay
 *
 * @author Knit Pay
 * @version 8.91.0.0
 * @since   8.91.0.0
 */
class Gateway extends Core_Gateway {
	/**
	 * Config
	 *
	 * @var Config
	 */
	protected $config;

	/**
	 * Client
	 *
	 * @var Client
	 */
	private $client;

	/**
	 * Constructs and initialize SumUp gateway.
	 *
	 * @param Config $config Config.
	 */
	public function __construct( Config $config ) {
		parent::__construct( $config );

		$this->config = $config;
		$this->client = new Client( $config );

		$this->is_iframe_checkout_method = true;
	}

	/**
	 * Start payment.
	 *
	 * @param Payment $payment Payment.
	 */
	public function start( Payment $payment ) {
		// @see https://developer.sumup.com/api/checkouts/create
		$data = [
			'checkout_reference' => $payment->key . '_' . $payment->get_id(),
			'amount'             => $payment->get_total_amount()->number_format( null, '.', '' ),
			'currency'           => $payment->get_total_amount()->get_currency()->get_alphabetic_code(),
			'merchant_code'      => $this->config->merchant_code,
			'description'        => $payment->get_description(),
			'return_url'         => $payment->get_return_url(),
			'redirect_url'       => $payment->get_return_url(),
		];

		$checkout_session = $this->client->create_checkout_session( $data );
		$payment->set_transaction_id( $checkout_session['id'] );
	}

	/**
	 * Update status of the specified payment.
	 *
	 * @param Payment $payment Payment.
	 */
	public function update_status( Payment $payment ) {
		try {
			$data = $this->client->get_checkout_status( $payment->get_transaction_id() );

			if ( isset( $data['status'] ) ) {
				$payment->set_status( Statuses::transform( $data['status'] ) );
			}

			if ( PaymentStatus::SUCCESS === $payment->get_status() && isset( $data['transaction_id'] ) ) {
				$payment->set_transaction_id( $data['transaction_id'] );
			}

			$payment->add_note( '<strong>SumUp Checkout Response:</strong><br><pre>' . print_r( $data, true ) . '</pre><br>' );
		} catch ( \Exception $e ) {
			$payment->add_note( 'SumUp Status Error: ' . $e->getMessage() );
		}
	}

	/**
	 * Output form.
	 *
	 * @param Payment $payment Payment.
	 * @return void
	 * @throws \Exception When payment action URL is empty.
	 */
	public function output_form( Payment $payment ) {
		if ( PaymentStatus::SUCCESS === $payment->get_status() ) {
			wp_safe_redirect( $payment->get_return_redirect_url() );
		}

		$script_url = 'https://gateway.sumup.com/gateway/ecom/card/v2/sdk.js';

		$html = '<meta name="viewport" content="width=device-width, initial-scale=1.0"><div id="sumup-card"></div>';

		// Get customer email if available for better APM support
		$customer = $payment->get_customer();

		// @see https://developer.sumup.com/online-payments/checkouts/card-widget#configurations
		$mount_parameter = [
			'id'          => 'sumup-card',
			'checkoutId'  => $payment->get_transaction_id(),
			'showZipCode' => true,
			'showEmail'   => true,
			'email'       => $customer->get_email(),
			// 'donateSubmitButton' => true,
			'locale'      => $customer->get_locale(),
			'country'     => $payment->get_billing_address()->get_country_code(),
			/*
			'googlePay' => [
				'merchantId' => $this->get_option( 'google_pay_merchant_id' ),
				'merchantName' => $this->get_option( 'google_pay_merchant_name' ),
			],*/
		];

		$script  = '<script src="' . $script_url . '"></script>';
		$script .= '<script type="text/javascript">';
		$script .= 'var mount_parameter = ' . json_encode( $mount_parameter ) . ';';
		$script .= 'mount_parameter.onResponse = function(type, body) {';
		$script .= '    console.log("SumUp Response Type:", type);';
		$script .= '    console.log("SumUp Response Body:", body);';
		$script .= '    if ("sent" !== type) {';
		$script .= '        document.getElementById("sumup-card").style.display = "none";';
		$script .= '        window.location.href = "' . $payment->get_return_url() . '";';
		$script .= '    }';
		$script .= '};';
		$script .= 'mount_parameter.onPaymentMethodsLoad = function(methods) {';
		$script .= '    console.log("Available Payment Methods:", methods);';
		$script .= '};';
		$script .= 'SumUpCard.mount(mount_parameter);';
		$script .= '</script>';

		echo $html . $script;
	}
}
