<?php

namespace KnitPay\Gateways\Fiserv;

use Pronamic\WordPress\Pay\Core\GatewayConfig;

/**
 * Title: Fiserv Config
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 6.64.0.0
 * @since   6.64.0.0
 */
class Config extends GatewayConfig {
	public $storename;

	public $sharedsecret;
}
