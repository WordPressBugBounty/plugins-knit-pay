<?php

namespace KnitPay\Gateways\Slydepay;

use Pronamic\WordPress\Pay\Core\GatewayConfig;

/**
 * Title: Slydepay Config
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 6.67.0.0
 * @since   6.67.0.0
 */
class Config extends GatewayConfig {
	public $merchant_email;

	public $api_key;
}
