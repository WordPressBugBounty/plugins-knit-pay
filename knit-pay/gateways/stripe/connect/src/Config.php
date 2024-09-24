<?php

namespace KnitPay\Gateways\Stripe\Connect;

use KnitPay\Gateways\Stripe\Config as StripeConfig;

/**
 * Title: Stripe Connect Config
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 1.0.0
 * @since   3.7.0
 */
class Config extends StripeConfig {
	public $account_id;

	public $application_fees_percentage;

	public $is_connected;
}
