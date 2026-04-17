<?php

namespace KnitPay\Gateways\IPay88;

use Pronamic\WordPress\Pay\Core\GatewayConfig;

/**
 * Title: iPay88 Config
 * Copyright: 2020-2026 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.96.0.0
 * @since   8.96.0.0
 */
class Config extends GatewayConfig {
	public $country;
	public $merchant_code;
	public $merchant_key;
}
