<?php

namespace KnitPay\Gateways\Paytm;

use Pronamic\WordPress\Pay\Core\PaymentMethods as Core_PaymentMethods;

class PaymentMethods extends Core_PaymentMethods {
	const PAYTM = 'paytm';

	const UPI = 'upi';
}
