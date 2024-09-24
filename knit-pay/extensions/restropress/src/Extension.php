<?php

namespace KnitPay\Extensions\RestroPress;

use Pronamic\WordPress\Pay\AbstractPluginIntegration;
use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Core\PaymentMethods;

/**
 * Title: Restro Press extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   2.6.0
 */
class Extension extends AbstractPluginIntegration {
	/**
	 * Slug
	 *
	 * @var string
	 */
	const SLUG = 'restropress';

	/**
	 * Constructs and initialize Restro Press extension.
	 */
	public function __construct() {
		parent::__construct(
			[
				'name' => __( 'Restro Press', 'knit-pay-lang' ),
			]
		);

		// Dependencies.
		$dependencies = $this->get_dependencies();

		$dependencies->add( new RestroPressDependency() );
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

		require_once 'Helper.php';
		require_once 'Gateway.php';
		new Gateway( 'Cashfree', 'cashfree' );
		new Gateway( 'Easebuzz', 'easebuzz' );
		new Gateway( 'Instamojo', 'instamojo' );
		new Gateway( 'PayUmoney', 'payumoney' );
		new Gateway( 'Razorpay', 'razorpay' );
		new Gateway( 'Knit Pay', 'knit_pay' );
		new Gateway( 'Knit Pay Test', 'test' );
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

		switch ( $payment->get_status() ) {
			case Core_Statuses::CANCELLED:
			case Core_Statuses::EXPIRED:
				rpress_debug_log( 'Payment Cancelled.' );
				rpress_set_error( 'knit_pay_error', 'Payment Cancelled.' );
				rpress_send_back_to_checkout( '?payment-mode=' . $payment->get_payment_method() );
				break;
			case Core_Statuses::FAILURE:
				rpress_debug_log( 'Payment Failed.' );
				rpress_set_error( 'knit_pay_error', 'Payment Failed.' );
				rpress_send_back_to_checkout( '?payment-mode=' . $payment->get_payment_method() );

				break;

			case Core_Statuses::SUCCESS:
				// Empty the shopping cart
				rpress_empty_cart();
				rpress_send_to_success_page();
				break;

			case Core_Statuses::AUTHORIZED:
			case Core_Statuses::OPEN:
			default:
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
		$payment_id = $payment->get_source_id();
		rpress_insert_payment_note( $payment_id, sprintf( __( 'Knit Pay Transaction ID: %s', 'knit-pay-lang' ), $payment->get_transaction_id() ) );
		rpress_set_payment_transaction_id( $payment_id, $payment->get_transaction_id() );

		switch ( $payment->get_status() ) {

			case Core_Statuses::CANCELLED:
			case Core_Statuses::EXPIRED:
				rpress_update_payment_status( $payment_id, 'abandoned' );

				break;
			case Core_Statuses::FAILURE:
				rpress_update_payment_status( $payment_id, 'failed' );

				break;
			case Core_Statuses::SUCCESS:
				rpress_update_payment_status( $payment_id, 'publish' );

				break;
			case Core_Statuses::OPEN:
			default:
				rpress_update_payment_status( $payment_id, 'pending' );

				break;
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
		$text = __( 'Restro Press', 'knit-pay-lang' ) . '<br />';

		$text .= sprintf(
			'<a href="%s">%s</a>',
			get_edit_post_link( $payment->source_id ),
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
		return __( 'Restro Press Order', 'knit-pay-lang' );
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
		return get_edit_post_link( $payment->source_id );
	}

}
