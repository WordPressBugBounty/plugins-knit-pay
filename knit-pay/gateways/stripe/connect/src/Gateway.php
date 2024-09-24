<?php
namespace KnitPay\Gateways\Stripe\Connect;

use KnitPay\Gateways\Stripe\Gateway as Stripe_Gateway;
use Pronamic\WordPress\Pay\Payments\Payment;
use WP_Error;

/**
 * Title: Stripe Connect Gateway
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 1.0.0
 * @since 3.7.0
 */
class Gateway extends Stripe_Gateway {

	protected function create_session_data( $payment ) {
		$session_data = parent::create_session_data( $payment );

		if ( ! defined( 'KNIT_PAY_PRO' ) ) {
			$session_data['payment_intent_data']['application_fee_amount'] = $this->get_application_fee_amount( $payment );
		}

		return $session_data;
	}

	private function get_application_fee_amount( $payment ) {
		$amount                      = $this->get_payment_amount( $payment );
		$application_fees_percentage = $this->get_application_fee_percentage();
		$application_fees_amount     = round( $amount * $application_fees_percentage, 0 );
		return max( $application_fees_amount, 0 );
	}

	public function get_application_fee_percentage() {
		if ( null === $this->config->application_fees_percentage ) {
			$this->config->application_fees_percentage = Integration::STRIPE_CONNECT_APPLICATION_FEES_PERCENTAGE / 100;
		}
		return $this->config->application_fees_percentage;
	}
}
