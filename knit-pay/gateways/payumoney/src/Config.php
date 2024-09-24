<?php

namespace KnitPay\Gateways\PayUmoney;

use Pronamic\WordPress\Pay\Core\GatewayConfig;

/**
 * Title: PayUMoney Config
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 1.9.1
 * @since   1.0.0
 */
class Config extends GatewayConfig {
	public $mode;

	public $mid;

	public $merchant_key;

	public $merchant_salt;

	public $transaction_fees_percentage;

	public $transaction_fees_fix;

	public $auth_header;

	public $authorization_header_value;
}
