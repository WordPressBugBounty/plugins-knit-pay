<?php

namespace KnitPay\Gateways\SumUp;

use Pronamic\WordPress\Pay\Core\GatewayConfig;

/**
 * Title: SumUp Config
 * Copyright: 2020-2025 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.91.0.0
 * @since   8.91.0.0
 */
class Config extends GatewayConfig {
	public $login_email;
	public $client_id;
	public $client_secret;
} 
