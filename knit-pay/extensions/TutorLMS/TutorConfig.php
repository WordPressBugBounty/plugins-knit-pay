<?php

namespace KnitPay\Extensions\TutorLMS;

use Tutor\Ecommerce\Settings;
use Ollyo\PaymentHub\Core\Payment\BaseConfig;
use Tutor\PaymentGateways\Configs\PaymentUrlsTrait;
use Ollyo\PaymentHub\Contracts\Payment\ConfigContract;

/**
 * Title: TutorConfig class
 * Description: This class is used to manage the configuration settings for the "Knit Pay" gateway. It extends the `BaseConfig` class and implements the `ConfigContract` interface. The class is designed to interact with the `Settings` class to retrieve configuration data for the gateway and provide necessary methods for accessing and validating the configuration.
 * Copyright: 2020-2026 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   8.97.0.0
 */
class TutorConfig extends BaseConfig implements ConfigContract {

	/**
	 * This trait provides methods to retrieve the URLs used in the payment process for success, cancellation, and webhook 
	 * notifications. It includes functionality for retrieving dynamic URLs based on the current environment (e.g., 
	 * live or test) and allows for filterable URL customization.
	 */
	use PaymentUrlsTrait;

	private $checkout_label;

	/**
	 * Stores the Knit Pay configuration ID for the payment gateway.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	private $config_id;

	/**
	 * Stores the payment description of the payment gateway.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	private $payment_description;

	/**
	 * The name of the payment gateway.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $name = 'knit_pay';

	/**
	 * Constructor.
	 *
	 * Initializes the `TutorConfig` object by loading settings for the "knit_pay" gateway from the Settings 
	 * class. It populates the object's properties based on the keys retrieved from the settings.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		
		parent::__construct();
		
		$settings    = Settings::get_payment_gateway_settings( 'knit_pay' );
		$config_keys = array_keys( self::get_config_keys() );
		
		foreach ( $config_keys as $key ) {
			$this->$key = $this->get_field_value( $settings, $key );
		}
	}

	/**
	 * Checks if the payment gateway is properly configured. The gateway is considered configured if the properties values 
	 * are all present.
	 *
	 * @since 1.0.0
	 *
	 * @return boolean
	 */
	public function is_configured() {
		return true;
	}

	/**
	 * Returns an array of the configuration keys for the `knit_pay` gateway.
	 *
	 * @return array
	 */
	private function get_config_keys(): array {
		return [
			'checkout_label'      => 'checkout_label', // Field Type is Text
			'config_id'           => 'config_id', // Field Type is Text
			'payment_description' => 'payment_description', // Field Type is Text
		];
	}

	/**
	 * Creates the configuration for the payment gateway. 
	 * This method extends the `createConfig` method from the parent class and updates the configuration if needed.
	 *
	 * @return void
	 */
	public function createConfig(): void {
		parent::createConfig();

		// Update the configuration if the gateway requires additional fields beyond the default ones.
		$config = [
			'checkout_label'      => $this->checkout_label,
			'config_id'           => $this->config_id,
			'payment_description' => $this->payment_description,
		];
		$this->updateConfig( $config );
	}
}
