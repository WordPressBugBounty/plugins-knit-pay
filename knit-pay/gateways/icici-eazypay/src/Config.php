<?php

namespace KnitPay\Gateways\IciciEazypay;

use Pronamic\WordPress\Pay\Core\GatewayConfig;

/**
 * Title: ICICI Eazypay Config
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 6.62.0.0
 * @since   6.62.0.0
 */
class Config extends GatewayConfig {
	public $merchant_id;

	public $encryption_key;

	public $mandatory_fields;

	public $optional_fields;

	public $static_return_url;
}
