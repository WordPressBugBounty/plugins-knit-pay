<?php

namespace KnitPay\Gateways\CMI;

use Pronamic\WordPress\Pay\Core\GatewayConfig;

/**
 * Title: CMI Config
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 7.71.0.0
 * @since   7.71.0.0
 */
class Config extends GatewayConfig {
	public $mode;
	public $client_id;
	public $store_key;
}
