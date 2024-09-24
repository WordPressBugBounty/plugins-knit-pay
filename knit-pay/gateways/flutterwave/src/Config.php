<?php

namespace KnitPay\Gateways\Flutterwave;

use Pronamic\WordPress\Pay\Core\GatewayConfig;

/**
 * Title: Flutterwave Config
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 1.0.0
 * @since   4.8.0
 */
class Config extends GatewayConfig {
	public $secret_key;
}
