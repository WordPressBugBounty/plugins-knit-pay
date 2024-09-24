<?php

namespace KnitPay\Gateways\Easebuzz;

use Pronamic\WordPress\Pay\Core\GatewayConfig;

/**
 * Title: Easebuzz Config
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 1.0.0
 * @since   1.2.0
 */
class Config extends GatewayConfig {
	public $mode;

	public $merchant_key;

	public $merchant_salt;

	public $sub_merchant_id;
}
