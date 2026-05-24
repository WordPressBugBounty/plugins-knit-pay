<?php

namespace KnitPay\Gateways\PayPal;

use Pronamic\WordPress\Pay\Core\GatewayConfig;

/**
 * Title: PayPal Config
 * Copyright: 2020-2026 Knit Pay
 *
 * @author  Knit Pay
 * @version 8.94.0.0
 * @since   8.94.0.0
 */
class Config extends GatewayConfig {
	public $mode;
	public $config_id;
	public $client_id;
	public $client_secret;
	public $invoice_prefix;
	public $webhook_id;
	public $test_buyer_country;

	// OAuth.
	public $merchant_id;
	public $is_connected;
	public $connected_at;
}
