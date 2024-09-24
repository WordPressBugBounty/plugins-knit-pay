<?php

namespace KnitPay\Gateways\Sodexo;

use Pronamic\WordPress\Pay\Core\GatewayConfig;

/**
 * Title: Sodexo Config
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 1.0.0
 * @since   3.3.0
 */
class Config extends GatewayConfig {
	public $mode;

	public $api_keys;

	public $aid;

	public $mid;

	public $tid;
}
