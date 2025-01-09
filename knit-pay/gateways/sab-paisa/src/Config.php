<?php

namespace KnitPay\Gateways\SabPaisa;

use Pronamic\WordPress\Pay\Core\GatewayConfig;

/**
 * Title: Sab Paisa Config
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.95.0.0
 * @since   8.95.0.0
 */
class Config extends GatewayConfig {
	public $mode;
	public $client_code;
	public $username;
	public $password;
	public $auth_key;
	public $auth_iv;
}
