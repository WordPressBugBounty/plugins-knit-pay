<?php

namespace KnitPay\Gateways\Fygaro;

use Pronamic\WordPress\Pay\Core\GatewayConfig;

/**
 * Title: Fygaro Config
 * Copyright: 2020-2021 Knit Pay
 *
 * @author  Knit Pay
 * @version 5.0.0
 * @since   5.0.0
 */
class Config extends GatewayConfig {
	public $api_key;
	public $api_secret;
	public $payment_button_url;
}
