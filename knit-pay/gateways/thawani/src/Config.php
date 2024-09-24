<?php

namespace KnitPay\Gateways\Thawani;

use Pronamic\WordPress\Pay\Core\GatewayConfig;

/**
 * Title: Thawani Config
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 6.70.0.0
 * @since   6.70.0.0
 */
class Config extends GatewayConfig {
	public $mode;

	public $publishable_key;
	
	public $secret_key;
}
