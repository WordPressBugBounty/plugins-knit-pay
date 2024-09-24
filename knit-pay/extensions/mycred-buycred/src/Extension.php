<?php

namespace KnitPay\Extensions\MycredBuycred;

use Pronamic\WordPress\Pay\AbstractPluginIntegration;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: myCRED buyCRED extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   3.5.0
 */
class Extension extends AbstractPluginIntegration {
	/**
	 * Slug
	 *
	 * @var string
	 */
	const SLUG = 'mycred-buycred';

	/**
	 * Constructs and initialize myCRED-buyCRED extension.
	 */
	public function __construct() {
		parent::__construct(
			[
				'name' => __( 'myCRED - buyCRED', 'knit-pay-lang' ),
			]
		);

		// Dependencies.
		$dependencies = $this->get_dependencies();

		$dependencies->add( new MyCredDependency() );
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
		add_action( 'pronamic_payment_status_update_' . self::SLUG, [ $this, 'status_update' ], 10 );

		add_action( 'plugins_loaded', [ $this, 'init_gateway' ], 10000 );
	}

	/**
	 * Initialize Gateway
	 */
	public function init_gateway() {
		require_once 'Gateway.php';
		require_once 'Helper.php';
		add_filter( 'mycred_setup_gateways', [ $this, 'mycred_setup_gateways' ] );
		add_filter( 'mycred_dropdown_currencies', [ $this, 'mycred_dropdown_currencies' ] );
	}

	public function mycred_dropdown_currencies( $currencies ) {
		$currencies['INR'] = 'Indian Rupee';
		return $currencies;
	}

	public function mycred_setup_gateways( $installed ) {
		// Knit Pay.
		// TODO: add more options
		$installed['knit_pay'] = [
			'title'         => 'Knit Pay',
			'documentation' => '', // TODO add documentation URL
			'callback'      => [ __NAMESPACE__ . '\Gateway' ],
			'icon'          => 'dashicons-admin-generic',
			'sandbox'       => false,
			'external'      => true,
			'custom_rate'   => true,
		];

		return $installed;
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
		self::status_update( $payment );
		$source_id = $payment->get_source_id();
		$gateway   = buycred_gateway( $payment->get_payment_method() );

		$pending_payment = $gateway->get_pending_payment( $source_id );

		if ( ! $pending_payment ) {
			return;
		}

		switch ( $payment->get_status() ) {
			case Core_Statuses::CANCELLED:
			case Core_Statuses::EXPIRED:
			case Core_Statuses::FAILURE:
				$gateway->get_cancelled( $source_id );

				break;

			case Core_Statuses::SUCCESS:
				$url = $gateway->get_thankyou();
				break;

			case Core_Statuses::AUTHORIZED:
			case Core_Statuses::OPEN:
				return home_url( '/' );
		}

		return $url;
	}

	/**
	 * Update the status of the specified payment
	 *
	 * @param Payment $payment Payment.
	 */
	public static function status_update( Payment $payment ) {

		$source_id = $payment->get_source_id();
		$gateway   = buycred_gateway( $payment->get_payment_method() );

		$pending_payment = $gateway->get_pending_payment( $source_id );

		if ( ! $pending_payment ) {
			return;
		}

		switch ( $payment->get_status() ) {
			case Core_Statuses::CANCELLED:
				$new_call[] = __( 'Payment Cancelled.' );
				break;
			case Core_Statuses::EXPIRED:
			case Core_Statuses::FAILURE:
				$new_call[] = __( 'Payment Failed.' );
				break;
			case Core_Statuses::SUCCESS:
				\buycred_complete_pending_payment( $source_id );
			case Core_Statuses::OPEN:
			default:
				break;
		}

		// Log Call
		if ( ! empty( $new_call ) ) {
			$gateway->log_call( $pending_payment->payment_id, $new_call );
		}
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
		$text = __( 'buyCRED', 'knit-pay-lang' ) . '<br />';

		if ( ! function_exists( 'buycred_get_pending_payment_id' ) ) {
			$text .= sprintf( __( 'Transaction %s', 'knit-pay-lang' ), $payment->source_id );
			return $text;
		}

		$payment_id = buycred_get_pending_payment_id( $payment->get_source_id() );
		if ( 'trash' === get_post_status( $payment_id ) ) {
			$text .= sprintf( __( 'Transaction %s', 'knit-pay-lang' ), $payment->source_id );
			return $text;
		}

		$text .= sprintf(
			'<a href="%s">%s</a>',
			get_edit_post_link( $payment_id ),
			/* translators: %s: source id */
			sprintf( __( 'Transaction %s', 'knit-pay-lang' ), $payment->source_id )
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
		return __( 'buyCRED Transaction', 'knit-pay-lang' );
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
		if ( ! function_exists( 'buycred_get_pending_payment_id' ) ) {
			return $url;
		}

		$payment_id = buycred_get_pending_payment_id( $payment->get_source_id() );
		if ( 'trash' === get_post_status( $payment_id ) ) {
			return $url;
		}
		return get_edit_post_link( $payment_id );
	}

}
