<?php

namespace KnitPay\Gateways\SSLCommerz;

use Pronamic\WordPress\Pay\Core\GatewayConfig;

/**
 * Title: SSLCommerz Config
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.80.0.0
 * @since   8.80.0.0
 */
class Config extends GatewayConfig {
	public $mode;
	public $store_id;
	public $store_password;
}
