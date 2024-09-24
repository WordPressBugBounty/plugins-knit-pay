<?php

namespace KnitPay\Extensions\WPAdverts;

use Pronamic\WordPress\Pay\AbstractPluginIntegration;
use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;
use Pronamic\WordPress\Pay\Payments\Payment;

/**
 * Title: WP Adverts extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   4.0.0
 */
class Extension extends AbstractPluginIntegration {
	/**
	 * Slug
	 *
	 * @var string
	 */
	const SLUG = 'wpadverts';

	/**
	 * Constructs and initialize WP Adverts extension.
	 */
	public function __construct() {
		parent::__construct(
			[
				'name' => __( 'WP Adverts', 'knit-pay-lang' ),
			]
		);

		// Dependencies.
		$dependencies = $this->get_dependencies();

		$dependencies->add( new WPAdvertsDependency() );
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

		require_once 'Gateway.php';
		require_once 'Helper.php';
		new Gateway();

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
		return $url;
	}

	/**
	 * Update the status of the specified payment
	 *
	 * @param Payment $payment Payment.
	 */
	public static function status_update( Payment $payment ) {

		$payment_id = (int) $payment->get_source_id();

		switch ( $payment->get_status() ) {
			case Core_Statuses::CANCELLED:
			case Core_Statuses::EXPIRED:
				wp_update_post(
					[
						'ID'          => $payment_id,
						'post_status' => 'failed',
					]
				);
				adext_payments_log( $payment_id, __( 'Payment Cancelled.', 'knit-pay-lang' ) );

				break;
			case Core_Statuses::FAILURE:
				wp_update_post(
					[
						'ID'          => $payment_id,
						'post_status' => 'failed',
					]
				);
				adext_payments_log( $payment_id, __( 'Payment failed.', 'knit-pay-lang' ) );

				break;
			case Core_Statuses::SUCCESS:
				wp_update_post(
					[
						'ID'          => $payment_id,
						'post_status' => 'completed',
					]
				);
				adext_payments_log( $payment_id, __( 'Payment successful.', 'knit-pay-lang' ) );

				// Perform adverts_payment_completed action.
				do_action( 'adverts_payment_completed', get_post( $payment_id ) );

				break;
			case Core_Statuses::REFUNDED:
				wp_update_post(
					[
						'ID'          => $payment_id,
						'post_status' => 'refunded',
					]
				);
				adext_payments_log( $payment_id, __( 'Payment Refunded.', 'knit-pay-lang' ) );

				break;
			case Core_Statuses::OPEN:
			default:
				wp_update_post(
					[
						'ID'          => $payment_id,
						'post_status' => 'pending',
					]
				);

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
		$text = __( 'WP Adverts', 'knit-pay-lang' ) . '<br />';

		$text .= sprintf(
			'<a href="%s">%s</a>',
			admin_url( sprintf( 'edit.php?post_type=advert&page=adext-payment-history&edit=%d', $payment->source_id ) ),
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
		return __( 'WP Adverts Payment', 'knit-pay-lang' );
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
		return admin_url( sprintf( 'edit.php?post_type=advert&page=adext-payment-history&edit=%d', $payment->source_id ) );
	}

}
