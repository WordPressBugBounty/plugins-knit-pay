<?php

namespace KnitPay\Gateways\Revolut;

use Pronamic\WordPress\Pay\Core\GatewayConfig;

/**
 * Title: Revolut Config
 * Copyright: 2020-2026 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.98.0.0
 * @since   8.98.0.0
 */
class Config extends GatewayConfig {
	/**
	 * API Secret Key for authentication (Bearer token)
	 *
	 * @var string
	 */
	public $api_secret_key;
	
	/**
	 * Mode (test/live)
	 * Determines which API endpoints to use
	 *
	 * @var string
	 */
	public $mode;
}
