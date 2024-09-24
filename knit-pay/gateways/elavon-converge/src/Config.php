<?php

namespace KnitPay\Gateways\ElavonConverge;

use Pronamic\WordPress\Pay\Core\GatewayConfig;

/**
 * Title: Elavon Converge Config
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 1.0.0
 * @since   4.3.0
 */
class Config extends GatewayConfig {
	public $mode;

	public $merchant_id;

	public $user_id;

	public $terminal_pin;

	public $multi_currency_enabled;
}
