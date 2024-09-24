<?php

namespace KnitPay\Extensions\Tickera;

use Pronamic\WordPress\Pay\AbstractPluginIntegration;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;
use TC_Order;

/**
 * Title: Tickera extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   8.84.0.0
 */
class Extension extends AbstractPluginIntegration {
	/**
	 * Slug
	 *
	 * @var string
	 */
	const SLUG = 'tickera';

	/**
	 * Constructs and initialize Tickera extension.
	 */
	public function __construct() {
		parent::__construct(
			[
				'name' => __( 'Tickera', 'knit-pay-lang' ),
			]
		);

		// Dependencies.
		$dependencies = $this->get_dependencies();

		$dependencies->add( new TickeraDependency() );
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

		// TODO: Implement using tc_load_gateway_plugins action
		add_filter( 'tc_gateway_plugins', [ $this, 'tc_gateway_plugins' ], 10, 2 );
	}

	public function tc_gateway_plugins( $gateway_plugins, $gateway_plugins_originals ) {
		$gateway_plugins[] = plugin_dir_path( __FILE__ ) . 'Gateway.php';

		return $gateway_plugins;
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
		global $tc;

		$order_id = tc_get_order_id_by_name( $payment->get_source_id() )->ID;
		$order    = new TC_Order( $order_id );

		return $tc->tc_order_status_url( $order, '', '', false );
	}

	/**
	 * Update the status of the specified payment
	 *
	 * @param Payment $payment Payment.
	 */
	public static function status_update( Payment $payment ) {
		global $tc;

		$order = $tc->get_order( $payment->get_source_id() );
		if ( ! $order ) {
			return false;
		}

		switch ( $payment->get_status() ) {
			case Core_Statuses::CANCELLED:
			case Core_Statuses::EXPIRED:
			case Core_Statuses::FAILURE:
				$tc->update_order_status( $order->ID, 'order_cancelled' );

				break;
			case Core_Statuses::SUCCESS:
				$tc->update_order_payment_status( $order->ID, true );

				break;
			case Core_Statuses::OPEN:
			default:
				$tc->update_order_status( $order->ID, 'order_received' );

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
		$order = tc_get_order_id_by_name( $payment->get_source_id() );

		$text = __( 'Tickera', 'knit-pay-lang' ) . '<br />';

		$text .= sprintf(
			'<a href="%s">%s</a>',
			get_edit_post_link( $order->ID ),
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
		return __( 'Tickera Order', 'knit-pay-lang' );
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
		$order = tc_get_order_id_by_name( $payment->get_source_id() );

		return get_edit_post_link( $order->ID );
	}

}
