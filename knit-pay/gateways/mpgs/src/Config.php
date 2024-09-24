<?php

namespace KnitPay\Gateways\MPGS;

use Pronamic\WordPress\Pay\Core\GatewayConfig;

/**
 * Title: MPGS Config
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.81.0.0
 * @since   8.81.0.0
 */
class Config extends GatewayConfig {
	public $mpgs_url;

	public $merchant_id;

	public $auth_pass;
}
