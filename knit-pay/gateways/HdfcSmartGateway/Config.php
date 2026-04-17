<?php

namespace KnitPay\Gateways\HdfcSmartGateway;

use Pronamic\WordPress\Pay\Core\GatewayConfig;

/**
 * Title: HDFC Smart Gateway Config
 * Copyright: 2020-2026 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.93.0.0
 * @since   8.93.0.0
 */
class Config extends GatewayConfig {
	public $api_key;
	public $merchant_id;
	public $client_id;
	public $mode;
}
