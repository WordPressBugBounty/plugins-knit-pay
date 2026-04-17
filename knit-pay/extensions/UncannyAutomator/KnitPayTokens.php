<?php
namespace KnitPay\Extensions\UncannyAutomator;

use Pronamic\WordPress\Pay\Payments\Payment;
use KnitPay\Utils as KnitPayUtils;

/**
 * Title: Knit Pay Tokens
 * Description:
 * Copyright: 2020-2026 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   8.99.0.0
 */
class KnitPayTokens {
	/**
	 * Hydrate Payment tokens
	 *
	 * @param Payment $payment
	 * @return array
	 */
	public static function hydrate_payment_tokens( $payment ) {
		$customer        = $payment->get_customer();
		$billing_address = $payment->get_billing_address();

		$token_values = [
			'KNIT_PAY_PAYMENT_ID'          => $payment->get_id(),
			'KNIT_PAY_PAYMENT_STATUS'      => $payment->get_status(),
			'KNIT_PAY_TRANSACTION_ID'      => $payment->get_transaction_id(),
			'KNIT_PAY_ORDER_ID'            => $payment->get_order_id(),
			'KNIT_PAY_SOURCE_ID'           => $payment->get_source_id(),
			'KNIT_PAY_SOURCE'              => $payment->get_source(),
			'KNIT_PAY_PAYMENT_DESCRIPTION' => $payment->get_description(),
			'KNIT_PAY_REDIRECT_URL'        => $payment->get_pay_redirect_url(),
			'KNIT_PAY_PAYMENT_METHOD'      => $payment->get_payment_method(),
			'KNIT_PAY_PAYMENT_AMOUNT'      => $payment->get_total_amount()->number_format( null, '.', '' ),
			'KNIT_PAY_PAYMENT_CURRENCY'    => $payment->get_total_amount()->get_currency()->get_alphabetic_code(),

			'KNIT_PAY_CUSTOMER_NAME'       => KnitPayUtils::substr_after_trim( $customer->get_name() ),
			'KNIT_PAY_CUSTOMER_EMAIL'      => $customer->get_email(),
		];

		if ( null !== $billing_address ) {
			$token_values['KNIT_PAY_BILLING_PHONE'] = $billing_address->get_phone();
		}

		return $token_values;
	}
}
