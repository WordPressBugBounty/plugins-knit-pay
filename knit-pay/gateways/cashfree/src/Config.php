<?php

namespace KnitPay\Gateways\Cashfree;

use Pronamic\WordPress\Pay\Core\GatewayConfig;

/**
 * Title: Cashfree Config
 * Copyright: 2020-2025 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.91.0.0
 * @since   2.4
 */
class Config extends GatewayConfig {
	public $mode;

	public $api_id;

	public $secret_key;

	public $default_customer_phone;

	public $config_id;

	// OAuth.
	public $merchant_id;

	public $access_token;

	public $refresh_token;

	public $token_expires_at;

	public $is_connected;

	public $connected_at;

	public $expires_at;
}
