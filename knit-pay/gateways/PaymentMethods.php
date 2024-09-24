<?php

namespace KnitPay\Gateways;

use Pronamic\WordPress\Pay\Core\PaymentMethods as Core_PaymentMethods;

class PaymentMethods extends Core_PaymentMethods {
	const PAYTM = 'paytm';

	const UPI = 'upi';

	/**
	 * Debit Card
	 *
	 * @var string
	 */
	const DEBIT_CARD = 'debit_card';

	const NET_BANKING = 'net_banking';
}
