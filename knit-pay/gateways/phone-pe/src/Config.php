<?php

namespace KnitPay\Gateways\PhonePe;

use Pronamic\WordPress\Pay\Core\GatewayConfig;

/**
 * Title: PhonePe Config
 * Copyright: 2020-2025 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.73.0.0
 * @since   8.73.0.0
 */
class Config extends GatewayConfig {
	public $mode;
	public $api_version;

	// V1
	public $merchant_id;
	public $salt_key;
	public $salt_index;

	// V2
	public $client_id;
	public $client_secret;
	public $client_version;
}
