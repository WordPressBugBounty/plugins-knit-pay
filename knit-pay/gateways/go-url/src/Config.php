<?php

namespace KnitPay\Gateways\GoUrl;

use Pronamic\WordPress\Pay\Core\GatewayConfig;

/**
 * Title: GoUrl Config
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.76.0.0
 * @since   8.76.0.0
 */
class Config extends GatewayConfig {
	public $payment_received_status;
	public $payment_confirmed_status;
}
