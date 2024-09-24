<?php

namespace KnitPay\Gateways\OpenMoney;

use Pronamic\WordPress\Pay\Core\GatewayConfig;

/**
 * Title: Open Money Config
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 5.3.0
 * @since   5.3.0
 */
class Config extends GatewayConfig {
	public $mode;

	public $api_key;

	public $api_secret;
}
