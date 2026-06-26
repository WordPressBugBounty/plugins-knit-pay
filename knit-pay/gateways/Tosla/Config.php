<?php

namespace KnitPay\Gateways\Tosla;

use Pronamic\WordPress\Pay\Core\GatewayConfig;

/**
 * Title: Tosla Config
 * Copyright: 2020-2026 Knit Pay
 *
 * @author  Knit Pay
 * @version 9.6.0.0
 * @since   9.6.0.0
 */
class Config extends GatewayConfig {
	public $mode;
	public $client_id;
	public $api_user;
	public $api_pass;
}
