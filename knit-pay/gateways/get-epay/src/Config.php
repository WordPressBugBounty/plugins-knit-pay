<?php

namespace KnitPay\Gateways\GetEpay;

use Pronamic\WordPress\Pay\Core\GatewayConfig;

/**
 * Title: Get ePay Config
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.87.0.0
 * @since   8.87.0.0
 */
class Config extends GatewayConfig {
	public $mode;
	public $mid;
	public $terminal_id;
	public $key;
	public $iv;
}
