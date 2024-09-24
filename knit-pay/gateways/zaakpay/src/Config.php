<?php

namespace KnitPay\Gateways\Zaakpay;

use Pronamic\WordPress\Pay\Core\GatewayConfig;

/**
 * Title: Zaakpay Config
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 6.66.0.0
 * @since   6.66.0.0
 */
class Config extends GatewayConfig {
	public $mode;

	public $merchant_identifier;
	
	public $secret_key;
}
