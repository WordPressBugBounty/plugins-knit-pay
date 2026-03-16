<?php

namespace KnitPay\Gateways;

use Pronamic\WordPress\Pay\Core\PaymentMethods as Core_PaymentMethods;

class PaymentMethods extends Core_PaymentMethods {
	const PAYTM = 'paytm';

	const UPI = 'upi';

	const UPI_COLLECT = 'upi_collect';

	const SMS_PAYMENT_LINK = 'sms_payment_link';

	const UPI_INTENT = 'upi_intent';
	const UPI_INTENT_PHONEPE = 'upi_intent_phonepe';

	/**
	 * Debit Card
	 *
	 * @var string
	 */
	const DEBIT_CARD = 'debit_card';

	const NET_BANKING = 'net_banking';

	const SODEXO = 'sodexo';

	const FPX = 'fpx';
}
