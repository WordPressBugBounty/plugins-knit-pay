<?php

namespace KnitPay\Gateways\SumUp;

use Pronamic\WordPress\Pay\Core\GatewayConfig;

/**
 * Title: SumUp Config
 * Copyright: 2020-2026 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.91.0.0
 * @since   8.91.0.0
 */
class Config extends GatewayConfig {
	public $merchant_code;
	public $client_id;
	public $client_secret;
} 
