<?php

namespace KnitPay\Extensions\BooklyPro;

use Pronamic\WordPress\Pay\AbstractPluginIntegration;
use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;
use Pronamic\WordPress\Pay\Core\Util;
use Pronamic\WordPress\Pay\Payments\Payment;
use Bookly\Lib as BooklyLib;
use Pronamic\WordPress\Pay\Core\PaymentMethods;

/**
 * Title: Bookly Pro extension
 * Description:
 * Copyright: 2020-2026 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   3.4
 */
class Extension extends AbstractPluginIntegration {
	/**
	 * Slug
	 *
	 * @var string
	 */
	const SLUG = 'bookly-pro';

	/**
	 * Constructs and initialize Bookly Pro extension.
	 */
	public function __construct() {
		parent::__construct(
			[
				'name' => __( 'Bookly Pro', 'knit-pay-lang' ),
			]
		);

		// Dependencies.
		$dependencies = $this->get_dependencies();

		$dependencies->add( new BooklyProDependency() );
	}

	/**
	 * Setup plugin integration.
	 *
	 * @return void
	 */
	public function setup() {
		add_filter( 'pronamic_payment_source_text_' . self::SLUG, [ $this, 'source_text' ], 10, 2 );
		add_filter( 'pronamic_payment_source_description_' . self::SLUG, [ $this, 'source_description' ], 10, 2 );
		add_filter( 'pronamic_payment_source_url_' . self::SLUG, [ $this, 'source_url' ], 10, 2 );

		// Check if dependencies are met and integration is active.
		if ( ! $this->is_active() ) {
			return;
		}

		add_filter( 'pronamic_payment_redirect_url_' . self::SLUG, [ $this, 'redirect_url' ], 10, 2 );
		
		add_action( 'plugins_loaded', [ $this, 'init_gateway' ] );

		// TODO check if webhook is possible or not. Refer /bookly-addon-stripe/frontend/modules/stripe/Ajax.php

		add_filter( 'pre_option_bookly_pmt_local', [ $this, 'filter_payment_step_disabled' ], 10, 2 );
	}

	/**
	 * Initialize Gateway
	 */
	public static function init_gateway() {
		\BooklyKnitPay\Lib\Plugin::init();
	}

	/**
	 * Payment redirect URL filter.
	 *
	 * @param string  $url     Redirect URL.
	 * @param Payment $payment Payment.
	 *
	 * @return string
	 */
	public static function redirect_url( $url, $payment ) {
		return $payment->get_meta( 'bookly_response_url' );
	}

	/**
	 * Source column
	 *
	 * @param string  $text    Source text.
	 * @param Payment $payment Payment.
	 *
	 * @return string $text
	 */
	public function source_text( $text, Payment $payment ) {
		$text = __( 'Bookly', 'knit-pay-lang' ) . '<br />';

		$text .= sprintf(
			'<a href="%s">%s</a>',
			add_query_arg( 'page', 'bookly-payments', admin_url( 'admin.php' ) ),
			/* translators: %s: source id */
			sprintf( __( 'Payment %s', 'knit-pay-lang' ), $payment->source_id )
		);

		return $text;
	}

	/**
	 * Source description.
	 *
	 * @param string  $description Description.
	 * @param Payment $payment     Payment.
	 *
	 * @return string
	 */
	public function source_description( $description, Payment $payment ) {
		return __( 'Bookly Payment', 'knit-pay-lang' );
	}

	/**
	 * Source URL.
	 *
	 * @param string  $url     URL.
	 * @param Payment $payment Payment.
	 *
	 * @return string
	 */
	public function source_url( $url, Payment $payment ) {
		return add_query_arg( 'page', 'bookly-payments', admin_url( 'admin.php' ) );
	}
	
	public static function get_active_payment_methods() {
		$payment_methods        = PaymentMethods::get_active_payment_methods();
		$active_payment_methods = [ 'knit_pay' => 'knit_pay' ];
		
		foreach ( $payment_methods as $payment_method ) {
			$active_payment_methods[ $payment_method ] = 'knit_pay_' . $payment_method;
		}
		
		return $active_payment_methods;
	}

	public static function is_gateway_enabled( $gateway ) {
		$active_payment_methods = self::get_active_payment_methods();
		return in_array( $gateway, $active_payment_methods ) && get_option( 'bookly_' . $gateway . '_enabled' );
	}

	/**
	 * Check if any Knit Pay payment method is enabled.
	 *
	 * @return bool True if at least one Knit Pay payment method is enabled, false otherwise.
	 */
	public static function is_any_knit_pay_enabled() {
		$active_payment_methods = self::get_active_payment_methods();
		foreach ( $active_payment_methods as $payment_method ) {
			if ( get_option( 'bookly_' . $payment_method . '_enabled' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Ensure the Bookly payment step remains visible when only Knit Pay is enabled.
	 *
	 * \Bookly\Lib\Config::paymentStepDisabled() only checks built-in gateways.
	 * When Knit Pay is the only active gateway,
	 * the payment step gets hidden. This filter returns '1' for the
	 * 'bookly_pmt_local' option only when paymentStepDisabled() is asking,
	 * so payLocallyEnabled() evaluates as true and the step stays visible.
	 *
	 * @param mixed  $pre_value The pre-filtered value.
	 * @param string $option    Option name.
	 * @return string|mixed '1' if called from paymentStepDisabled(), otherwise the real value.
	 */
	public static function filter_payment_step_disabled( $pre_value, $option ) {
		if ( ! self::is_any_knit_pay_enabled() ) {
			return $pre_value;
		}

		$backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
		foreach ( $backtrace as $frame ) {
			if ( isset( $frame['class'], $frame['function'] )
				&& 'Bookly\Lib\Config' === $frame['class']
				&& 'paymentStepDisabled' === $frame['function'] ) {
				return '1';
			}
		}

		return $pre_value;
	}
}
