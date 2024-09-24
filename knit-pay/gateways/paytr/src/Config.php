<?php

namespace KnitPay\Gateways\Paytr;

use Pronamic\WordPress\Pay\Core\GatewayConfig;

/**
 * Title: PayTR Config
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.86.0.0
 * @since   8.86.0.0
 */
class Config extends GatewayConfig {
	public $mode;

	public $merchant_id;

	public $merchant_key;

	public $merchant_salt;
}
