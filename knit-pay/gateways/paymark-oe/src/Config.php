<?php

namespace KnitPay\Gateways\PaymarkOE;

use Pronamic\WordPress\Pay\Core\GatewayConfig;

/**
 * Title: Paymark OE Config
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 5.2.0
 * @since   5.2.0
 */
class Config extends GatewayConfig {
	public $consumer_key;

	public $consumer_secret;

	public $merchant_id_code;
}
