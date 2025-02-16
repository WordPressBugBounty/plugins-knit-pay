<?php

namespace KnitPay\Gateways\MyFatoorah;

use Pronamic\WordPress\Pay\Core\GatewayConfig;

/**
 * Title: MyFatoorah Config
 * Copyright: 2020-2025 Knit Pay
 *
 * @author  Knit Pay
 * @version 1.0.0
 * @since   6.63.0.0
 */
class Config extends GatewayConfig {
	public $mode;
	public $api_token_key;
}
