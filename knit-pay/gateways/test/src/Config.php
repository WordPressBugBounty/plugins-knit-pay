<?php

namespace KnitPay\Gateways\Test;

use Pronamic\WordPress\Pay\Core\GatewayConfig;
use KnitPay\Gateways\Easebuzz\Config as Parent_Config;

/**
 * Title: Test Config
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 1.0.0
 * @since   2.5.4
 */
class Config extends Parent_Config {
	public $checkout_mode;
}
