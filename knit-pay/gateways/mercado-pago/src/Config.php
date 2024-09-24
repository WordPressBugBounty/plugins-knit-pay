<?php

namespace KnitPay\Gateways\MercadoPago;

use Pronamic\WordPress\Pay\Core\GatewayConfig;

/**
 * Title: Mercado Pago Config
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.88.0.0
 * @since   8.88.0.0
 */
class Config extends GatewayConfig {
	public $public_key;
	public $access_token;
}
