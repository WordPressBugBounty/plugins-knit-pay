<?php

namespace KnitPay\Extensions\VikWP;

use Pronamic\WordPress\Pay\AbstractPluginIntegration;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;
use JLoader;

/**
 * Title: Vik WP extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   6.69.0.0
 */
class Extension extends AbstractPluginIntegration {
	/**
	 * Slug
	 *
	 * @var string
	 */
	const SLUG = 'vik-wp';

	/**
	 * Constructs and initialize Vik WP extension.
	 */
	public function __construct() {
		parent::__construct(
			[
				'name' => __( 'Vik WP', 'knit-pay-lang' ),
			]
		);

		// Dependencies.
		$dependencies = $this->get_dependencies();

		$dependencies->add( new VikWPDependency() );
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

		// @link https://vikwp.com/support/documentation/payment-plugins
		add_filter( 'get_supported_payments_vikbooking', [ $this, 'get_supported_payments_vikbooking' ] );
		add_action( 'load_payment_gateway_vikbooking', [ $this, 'load_payment_gateway_vikbooking' ], 10, 2 );
	}

	public function get_supported_payments_vikbooking( $drivers ) {
		$driver = 'KnitPayVikBookingGateway.php';
		
		// make sure the driver exists
		if ( $driver ) {
			$drivers[] = $driver;
		}
		
		return $drivers;
	}
	
	public function load_payment_gateway_vikbooking( &$drivers, $payment ) {
		// make sure the classname hasn't been generated yet by a different hook
		// and the request payment matches 'mypay' string
		if ( $payment == 'KnitPayVikBookingGateway' ) {
			$drivers[] = 'KnitPay\\Extensions\\VikWP\\' . $payment;
		}
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
		return $payment->get_meta( 'vik_return_url' );
	}

	/**
	 * Update the status of the specified payment
	 *
	 * @param Payment $payment Payment.
	 */
	public static function status_update( Payment $payment ) {
		if ( empty( $payment->get_meta( 'vik_return_url' ) ) ) {
			return;
		}
		
		// Calling KnitPayVikBookingGateway->validateTransaction.
		$notify_url =
		add_query_arg(
			[
				'payment_id' => $payment->get_id(),
				'tmpl'       => 'component',
				'task'       => 'notifypayment',
			],
			$payment->get_meta( 'vik_return_url' )
		);
		wp_remote_get( $notify_url, [ 'sslverify' => false ] );
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
		$text = __( 'Vik WP', 'knit-pay-lang' ) . '<br />';

		$text .= sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'admin.php?option=com_vikbooking&task=editorder&cid[0]=' . $payment->source_id ),
			/* translators: %s: source id */
			sprintf( __( 'Order %s', 'knit-pay-lang' ), $payment->source_id )
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
		return __( 'Vik WP Order', 'knit-pay-lang' );
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
		return admin_url( 'admin.php?option=com_vikbooking&task=editorder&cid[0]=' . $payment->source_id );
	}

}
