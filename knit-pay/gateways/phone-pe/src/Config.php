<?php

namespace KnitPay\Gateways\PhonePe;

use Pronamic\WordPress\Pay\Core\GatewayConfig;

/**
 * Title: PhonePe Config
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.73.0.0
 * @since   8.73.0.0
 */
class Config extends GatewayConfig {
	public $mode;

	public $merchant_id;

	public $salt_key;

	public $salt_index;
}
