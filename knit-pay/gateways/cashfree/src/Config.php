<?php

namespace KnitPay\Gateways\Cashfree;

use Pronamic\WordPress\Pay\Core\GatewayConfig;

/**
 * Title: Cashfree Config
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 1.0.0
 * @since   2.4
 */
class Config extends GatewayConfig {
	public $mode;

	public $api_id;

	public $secret_key;

	public $default_customer_phone;
}
