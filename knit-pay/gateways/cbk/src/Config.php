<?php

namespace KnitPay\Gateways\CBK;

use Pronamic\WordPress\Pay\Core\GatewayConfig;

/**
 * Title: CBK (Commercial Bank of Kuwait - Al-Tijari) Config
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 6.68.0.0
 * @since   6.68.0.0
 */
class Config extends GatewayConfig {
	public $mode;
	public $client_id;
	public $client_secret;
	public $encrypt_key;
}
