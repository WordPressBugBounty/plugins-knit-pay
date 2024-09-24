<?php

namespace KnitPay\Gateways\Coinbase;

use Pronamic\WordPress\Pay\Core\GatewayConfig;

/**
 * Title: Coinbase Commerce Config
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.77.0.0
 * @since   8.77.0.0
 */
class Config extends GatewayConfig {
	public $api_key;
	public $webhook_shared_secret;
}
