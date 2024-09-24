<?php

namespace KnitPay\Gateways\PayU;

use Pronamic\WordPress\Pay\Core\GatewayConfig;

/**
 * Title: PayU Config
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 5.4.0
 * @since   5.4.0
 */
class Config extends GatewayConfig {
	public $mode;

	public $mid;

	public $merchant_key;

	public $merchant_salt;

	public $transaction_fees_percentage;

	public $transaction_fees_fix;

	public $is_connected;
}
