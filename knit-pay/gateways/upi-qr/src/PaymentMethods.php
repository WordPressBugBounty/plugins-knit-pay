<?php

namespace KnitPay\Gateways\UpiQR;

use Pronamic\WordPress\Pay\Core\PaymentMethods as Core_PaymentMethods;

class PaymentMethods extends Core_PaymentMethods {
	const UPI = 'upi';
}
