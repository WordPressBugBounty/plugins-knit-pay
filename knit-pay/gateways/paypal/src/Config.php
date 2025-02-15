<?php

namespace KnitPay\Gateways\Paypal;

use Pronamic\WordPress\Pay\Core\GatewayConfig;

/**
 * Title: Paypal Config
 * Copyright: 2020-2025 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.94.0.0
 * @since   8.94.0.0
 */
class Config extends GatewayConfig {
	public $mode;
	public $config_id;
	public $client_id;
	public $client_secret;

	// OAuth.
	public $merchant_id;
	public $is_connected;
	public $connected_at;
}
