<?php

namespace KnitPay\Gateways\Iyzico;

use Pronamic\WordPress\Pay\Core\GatewayConfig;

/**
 * Title: Iyzico Config
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 5.6.0
 * @since   5.6.0
 */
class Config extends GatewayConfig {
	public $api_key;
	public $secret_key;
}
