<?php

namespace KnitPay\Gateways\ZohoPay;

use Pronamic\WordPress\Pay\Core\GatewayConfig;

/**
 * Title: Zoho Pay Config
 * Copyright: 2020-2026 Knit Pay
 *
 * @author  Knit Pay
 * @version 9.0.0.0
 * @since   9.0.0.0
 */
class Config extends GatewayConfig {
	public $mode;

	public $config_id;

	public $account_id;

	/**
	 * OAuth.
	 */
	public $access_token;

	public $refresh_token;

	public $is_connected;

	public $connected_at;

	public $expires_at;

	public $connection_fail_count;
}
