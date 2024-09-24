<?php

namespace KnitPay\Gateways\Paytm;

use Pronamic\WordPress\Pay\Core\GatewayConfig;

/**
 * Title: Paytm Config
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 4.9.0
 * @since   4.9.0
 */
class Config extends GatewayConfig {
	public $mode;

	public $merchant_id;

	public $merchant_key;

	public $website;

	public $order_id_format;

	public $expire_old_payments;
}
