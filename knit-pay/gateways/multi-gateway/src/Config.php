<?php

namespace KnitPay\Gateways\MultiGateway;

use Pronamic\WordPress\Pay\Core\GatewayConfig;

/**
 * Title: Multi Gateway Config
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 1.0.0
 * @since   4.0.0
 */
class Config extends GatewayConfig {
	const SELECTION_MANUAL_MODE = 0;
	
	const SELECTION_RANDOM_MODE = 1;
	
	public $gateway_selection_mode;
	
	public $enabled_payment_gateways;
}
