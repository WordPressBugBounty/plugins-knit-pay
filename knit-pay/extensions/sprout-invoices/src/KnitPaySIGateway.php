<?php

use KnitPay\Extensions\SproutInvoices\Helper;
use Pronamic\WordPress\Money\Currency;
use Pronamic\WordPress\Money\Money;
use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Payments\Payment;

class KnitPaySIGateway extends SI_Offsite_Processors {
	protected static $si_payment_method_name  = 'Knit Pay';
	protected static $knit_pay_payment_method = 'knit_pay';
	protected static $class_alias;

	protected static $instance;

	protected static $title_setting;
	protected static $payment_description_setting;
	protected static $config_id_setting;

	protected function __construct() {
		parent::__construct();

		$default_title = 'Online Payment';
		if ( 'knit_pay' !== static::$knit_pay_payment_method ) {
			$default_title = $default_title . ' - ' . static::$knit_pay_payment_method;
		}

		static::$title_setting               = get_option( self::get_setting_prefix() . 'title', $default_title );
		static::$payment_description_setting = get_option( self::get_setting_prefix() . 'payment_description', 'Invoice {invoice_id}' );
		static::$config_id_setting           = get_option( self::get_setting_prefix() . 'config_id', get_option( 'pronamic_pay_config_id' ) );

		add_action( 'si_checkout_action_' . SI_Checkouts::PAYMENT_PAGE, [ $this, 'send_offsite' ], 0, 1 );
	}

	public static function payment_init( $si_payment_method_name, $kp_payment_method ) {
		static::$si_payment_method_name  = $si_payment_method_name;
		static::$knit_pay_payment_method = $kp_payment_method;
		static::$class_alias             = get_called_class();

		static::register();
	}

	public static function get_instance() {
		if ( ! ( isset( static::$instance ) && is_a( static::$instance, static::$class_alias ) ) ) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	public function get_payment_method() {
		return static::$si_payment_method_name;
	}
	
	public function get_slug() {
		return trim( self::get_setting_prefix(), '_' );
	}

	public static function register() {
		if ( is_admin() ) {
			self::add_payment_processor( static::$class_alias, static::$si_payment_method_name );
		} else {
			$title_setting = get_option( self::get_setting_prefix() . 'title', 'Online Payment' );
			self::add_payment_processor( static::$class_alias, $title_setting );
		}
	}

	public static function public_name() {
		return __( 'Knit Pay', 'knit-pay-lang' );
	}

	public static function checkout_options() {
		$option = [
			'icons' => '',
			'label' => static::$title_setting,
			'cc'    => [],
		];
		return $option;
	}

	/**
	 * Hooked on init add the settings page and options.
	 */
	public static function register_settings( $settings = [] ) {
		// Settings
		$settings['payments'] = [
			self::get_setting_prefix() . 'settings' => self::get_setting_fields( static::$si_payment_method_name, static::$knit_pay_payment_method ),
		];
		
		return $settings;
	}
	
	protected static function get_setting_fields( $title, $payment_method ) {
		return [
			'title'    => $title,
			'weight'   => 200,
			'settings' => [
				self::get_setting_prefix() . 'title'     => [
					'label'  => __( 'Title', 'knit-pay-lang' ),
					'option' => [
						'type'    => 'text',
						'default' => static::$title_setting,
					],
				],
				self::get_setting_prefix() . 'config_id' => [
					'label'  => __( 'Configuration', 'knit-pay-lang' ),
					'option' => [
						'type'        => 'select',
						'options'     => Plugin::get_config_select_options( $payment_method ),
						'default'     => static::$config_id_setting,
						'description' => 'Configurations can be created in Knit Pay gateway configurations page at <strong>"Knit Pay >> Configurations"</strong>.',
					],
				],
				self::get_setting_prefix() . 'payment_description' => [
					'label'  => __( 'Payment Description', 'knit-pay-lang' ),
					'option' => [
						'type'        => 'text',
						'default'     => static::$payment_description_setting,
						'description' => sprintf( __( 'Available tags: %s', 'knit-pay-lang' ), sprintf( '<code>%s %s</code>', '{invoice_id}', '{invoice_name}' ) ),
					],
				],
			],
		];
	}

	/**
	 * Instead of redirecting to the SIcheckout page,
	 * redirect to payment gateway.
	 *
	 * @param SI_Checkouts $checkout
	 * @return void
	 */
	public function send_offsite( SI_Checkouts $checkout ) {
		// Check to see if the payment processor being used is for this payment processor
		if ( get_class( $checkout->get_processor() ) !== static::$class_alias ) { // FUTURE have parent class handle this smarter'r
			return;
		}

		// No form to validate
		remove_action( 'si_checkout_action_' . SI_Checkouts::PAYMENT_PAGE, [ $checkout, 'process_payment_page' ] );

		$config_id      = static::$config_id_setting;
		$payment_method = 'knit_pay';

		// Use default gateway if no configuration has been set.
		if ( empty( $config_id ) ) {
			$config_id = get_option( 'pronamic_pay_config_id' );
		}

		$gateway = Plugin::get_gateway( $config_id );

		if ( ! $gateway ) {
			return;
		}

		$invoice        = $checkout->get_invoice();
		$payment_amount = ( si_has_invoice_deposit( $invoice->get_id() ) ) ? $invoice->get_deposit() : $invoice->get_balance();

		// Refer SI_Stripe_Checkout for service fee implementation.
		if ( class_exists( 'SI_Service_Fee' ) ) {
			$service_fee_percent = SI_Service_Fee::get_service_fee( $this );
			$payment_amount      = $payment_amount + ( $payment_amount * $service_fee_percent / 100 );
		}

		/**
		 * Build payment.
		 */
		$payment = new Payment();

		$payment->source    = 'sprout-invoices';
		$payment->source_id = $invoice->get_id();
		$payment->order_id  = $invoice->get_id();

		$payment->set_description( Helper::get_description( $invoice, static::$payment_description_setting ) );

		$payment->title = Helper::get_title( $invoice );

		// Customer.
		$payment->set_customer( Helper::get_customer( $invoice ) );

		// Address.
		$payment->set_billing_address( Helper::get_address( $invoice ) );

		// Currency.
		$currency = Currency::get_instance( self::get_currency_code( $invoice->get_id() ) );

		// Amount.
		$payment->set_total_amount( new Money( $payment_amount, $currency ) );

		// Method.
		$payment->set_payment_method( $payment_method );

		// Configuration.
		$payment->config_id = $config_id;

		try {
			$payment = Plugin::start_payment( $payment );

			$payment->set_meta( 'si_payment_method', self::get_payment_method() );
			$payment->save();

			// Execute a redirect.
			wp_safe_redirect( $payment->get_pay_redirect_url() );
			exit();
		} catch ( \Exception $e ) {
			self::set_message( $e->getMessage(), self::MESSAGE_STATUS_ERROR );
		}
	}

	/**
	 * Process a payment
	 *
	 * @param SI_Checkouts $checkout
	 * @param SI_Invoice   $invoice
	 * @return SI_Payment|bool false if the payment failed, otherwise a Payment object
	 */
	public function process_payment( SI_Checkouts $checkout, SI_Invoice $invoice ) {
		return false;
	}

	//
	// Utility //
	//

	private function get_currency_code( $invoice_id ) {
		$invoice          = SI_Invoice::get_instance( $invoice_id );
		$invoice_currency = $invoice->get_currency();

		return apply_filters( 'si_currency_code', $invoice_currency, $invoice_id, static::$si_payment_method_name );
	}
	
	protected static function get_setting_prefix() {
		if ( self::$knit_pay_payment_method === static::$knit_pay_payment_method ) {
			return 'si_knit_pay_';
		}
		return 'si_knit_pay_' . static::$knit_pay_payment_method . '_';
	}
}
