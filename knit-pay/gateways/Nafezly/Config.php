<?php

namespace KnitPay\Gateways\Nafezly;

use Pronamic\WordPress\Pay\Core\GatewayConfig;

/**
 * Title: Nafezly Gateway Config
 * Copyright: 2020-2025 Knit Pay
 *
 * @author  Knit Pay
 * @version 1.0.0
 * @since   1.0.0
 */
class Config extends GatewayConfig {
	/**
	 * @var string Test / live mode flag.
	 */
	public $mode;

	/**
	 * @var array Raw configuration key=>value pairs for the nafezly driver.
	 */
	public $nafezly_config = [];
}
