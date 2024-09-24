<?php

namespace KnitPay\Gateways\NMI;

use Pronamic\WordPress\Pay\Core\GatewayConfig;

/**
 * Title: NMI Config
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.83.0.0
 * @since   8.83.0.0
 */
class Config extends GatewayConfig {
	public $private_key;
	public $public_key;
}
