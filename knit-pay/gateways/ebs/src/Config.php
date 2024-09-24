<?php

namespace KnitPay\Gateways\EBS;

use Pronamic\WordPress\Pay\Core\GatewayConfig;

/**
 * Title: EBS Config
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 1.0.0
 * @since   3.0.0
 */
class Config extends GatewayConfig {
	public $account_id;

	public $secret_key;
}
