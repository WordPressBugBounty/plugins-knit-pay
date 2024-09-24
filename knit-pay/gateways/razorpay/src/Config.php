<?php

namespace KnitPay\Gateways\Razorpay;

use Pronamic\WordPress\Pay\Core\GatewayConfig;

/**
 * Title: Razorpay Config
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 1.0.0
 * @since   1.7.0
 */
class Config extends GatewayConfig {
	const CHECKOUT_STANDARD_MODE = 1;

	const CHECKOUT_HOSTED_MODE = 2;

	public $mode;

	public $key_id;

	public $key_secret;

	public $webhook_id;

	public $webhook_secret;

	public $is_connected;

	public $connected_at;

	public $expires_at;

	public $access_token;

	public $refresh_token;

	public $company_name;

	public $checkout_image;

	public $checkout_mode;

	public $transaction_fees_percentage;

	public $transaction_fees_fix;

	public $merchant_id;
	
	public $connection_fail_count;

	public $expire_old_payments;

	public $config_id;
}
