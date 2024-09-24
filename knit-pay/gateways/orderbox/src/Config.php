<?php

namespace KnitPay\Gateways\OrderBox;

use Pronamic\WordPress\Pay\Core\GatewayConfig;

/**
 * Title: Orderbox Config
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 6.65.0.0
 * @since   6.65.0.0
 */
class Config extends GatewayConfig {
	public $payment_type_id;
	public $key;
	public $config_id;
	public $payment_method;
	public $gateway_currency;
	public $exchange_rate;
	public $transaction_fees_percentage;
	public $transaction_fees_fix;
}
