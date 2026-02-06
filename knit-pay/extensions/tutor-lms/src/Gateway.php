<?php

namespace KnitPay\Extensions\TutorLMS;

use Pronamic\WordPress\Money\Currency;
use Pronamic\WordPress\Money\Money;
use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Payments\Payment;
use Tutor\PaymentGateways\GatewayBase;
use Tutor\PaymentGateways\Configs\PaymentUrlsTrait;

/**
 * Title: Tutor LMS Gateway
 * Description:
 * Copyright: 2020-2026 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   8.97.0.0
 */

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit();

class Gateway extends GatewayBase {
	use PaymentUrlsTrait;

	/**
	 * Payment gateway dir name
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	private $dir_name = 'knit_pay';

	protected $config_id;
	protected $payment_description;

	/**
	 * @var string
	 */
	public $id = 'knit_pay';

	/**
	 * Payment method.
	 *
	 * @var string
	 */
	private $payment_method;

	/**
	 * Bootstrap
	 *
	 * @param array $args Gateway properties.
	 */
	public function __construct() {
		parent::__construct();

		add_filter( 'tutor_payment_gateways', [ $this, 'add_tutor_payment_method' ], 100 ); // Add Settings Options

		add_filter( 'tutor_option_input', [ $this, 'tutor_option_input' ] );
	}

	public function tutor_option_input( $option ) {
		$payment_settings       = $option['payment_settings'];
		$payment_settings_array = json_decode( $payment_settings, true );

		foreach ( $payment_settings_array['payment_methods'] as &$payment_method ) {
			if ( 'knit_pay' === $payment_method['name'] ) {

				$payment_method['label'] = $this->get_field_value( $payment_method, 'checkout_label' );
			}
		}

		$option['payment_settings'] = wp_json_encode( $payment_settings_array );

		return $option;
	}

	
	/**
	 * Add custom payment method.
	 *
	 * This method defines the configuration fields for the Custom Payment method and adds it to Tutor's payment options.
	 *
	 * @since 1.0.0
	 *
	 * @param array $methods Tutor existing payment methods.
	 *
	 * @return array
	 */
	public function add_tutor_payment_method( $methods ) {
		$payment_method = [
			'name'                 => 'knit_pay',
			'label'                => 'Knit Pay', // TODO
			'is_installed'         => true,
			'is_active'            => true,
			'is_plugin_active'     => true,
			'icon'                 => '', // Icon url.
			'support_subscription' => false,
			'fields'               => [
				[
					'name'  => 'checkout_label',
					'type'  => 'text',
					'label' => __( 'Checkout Label', 'knit-pay-lang' ),
					'value' => 'Pay Online',
				],
				[
					'name'    => 'config_id',
					'type'    => 'select',
					'label'   => __( 'Configuration', 'knit-pay-lang' ),
					'options' => Plugin::get_config_select_options( $this->payment_method ),
					'value'   => get_option( 'pronamic_pay_config_id' ),
				],
				[
					'name'  => 'payment_description',
					'type'  => 'text',
					'label' => __( 'Payment Description', 'knit-pay-lang' ),
					'value' => 'Order {order_id}',
				],
			],
		];

		$methods[] = $payment_method;
		return $methods;
	}

		/**
		 * Root dir name of payment gateway src
		 *
		 * @since 1.0.0
		 *
		 * @return string
		 */
	public function get_root_dir_name(): string {
		return $this->dir_name;
	}

		/**
		 * Payment config class
		 *
		 * @since 1.0.0
		 *
		 * @return string
		 */
	public function get_config_class(): string {
		return TutorConfig::class;
	}

		/**
		 * Payment class from payment hub
		 *
		 * @since 1.0.0
		 *
		 * @return string
		 */
	public function get_payment_class(): string {
		return TutorPayment::class;
	}

	/**
	 * Returns the autoload file for the payment gateway.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function get_autoload_file() {
		return '';
	}
}
