<?php

namespace KnitPay\Gateways\Pesapal;

use Pronamic\WordPress\Pay\Core\GatewayConfig;

/**
 * Title: Pesapal Config
 * Copyright: 2020-2025 Knit Pay
 *
 * @author  Knit Pay
 * @version 9.2.0.0
 * @since   9.2.0.0
 */
class Config extends GatewayConfig {
	/**
	 * Consumer Key for Pesapal API authentication
	 * 
	 * @var string
	 */
	public $consumer_key;
	
	/**
	 * Consumer Secret for Pesapal API authentication
	 * 
	 * @var string
	 */
	public $consumer_secret;
	
	/**
	 * IPN ID for webhook notifications
	 * 
	 * @var string
	 */
	public $ipn_id;
	
	/**
	 * Mode (test/live)
	 * Determines which API endpoints to use
	 * 
	 * @var string
	 */
	public $mode;
}
