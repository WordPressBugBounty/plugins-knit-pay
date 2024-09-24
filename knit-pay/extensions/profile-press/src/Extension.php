<?php

namespace KnitPay\Extensions\ProfilePress;

use ProfilePress\Core\Membership\Models\Order\OrderFactory;
use ProfilePress\Core\Membership\Models\Order\OrderStatus;
use Pronamic\WordPress\Pay\AbstractPluginIntegration;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: ProfilePress extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   8.79.0.0
 */
class Extension extends AbstractPluginIntegration {
	/**
	 * Slug
	 *
	 * @var string
	 */
	const SLUG = 'profile-press';

	/**
	 * Constructs and initialize ProfilePress extension.
	 */
	public function __construct() {
		parent::__construct(
			[
				'name' => __( 'ProfilePress', 'knit-pay-lang' ),
			]
		);

		// Dependencies.
		$dependencies = $this->get_dependencies();

		$dependencies->add( new ProfilePressDependency() );
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

		add_filter( 'ppress_payment_methods', [ $this, 'ppress_payment_methods' ] );
	}

	public static function ppress_payment_methods( $methods ) {
		$methods = [ Gateway::get_instance() ] + $methods;

		return $methods;
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
		$source_id = (int) $payment->get_source_id();
		$order     = OrderFactory::fromId( $source_id );

		switch ( $payment->get_status() ) {
			case Core_Statuses::CANCELLED:
			case Core_Statuses::EXPIRED:
			case Core_Statuses::FAILURE:
				$url = ppress_get_cancel_url( $order->order_key );                

				break;

			case Core_Statuses::SUCCESS:
				$url = ppress_get_success_url( $order->order_key, $order->payment_method );
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
		$source_id    = (int) $payment->get_source_id();
		$order        = OrderFactory::fromId( $source_id );
		$subscription = $order->get_subscription();

		switch ( $payment->get_status() ) {
			case Core_Statuses::CANCELLED:
			case Core_Statuses::EXPIRED:
			case Core_Statuses::FAILURE:
				$order->fail_order();

				break;
			case Core_Statuses::SUCCESS:
				$order->complete_order( $payment->get_transaction_id() );
				$subscription->activate_subscription();
				
				break;
			case Core_Statuses::OPEN:
			default:
				$order->set_status( OrderStatus::PENDING );
				
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
		$text = __( 'ProfilePress', 'knit-pay-lang' ) . '<br />';

		$order_text = sprintf( __( 'Order %s', 'knit-pay-lang' ), $payment->source_id );
		
		if ( defined( 'PPRESS_MEMBERSHIP_ORDERS_SETTINGS_PAGE' ) ) {  
			$order_text = sprintf(
				'<a href="%s">%s</a>',
				add_query_arg(
					[
						'ppress_order_action' => 'edit',
						'id'                  => $payment->source_id,
					],
					PPRESS_MEMBERSHIP_ORDERS_SETTINGS_PAGE 
				),
				/* translators: %s: source id */
				$order_text
			);
		}

		return $text . $order_text;
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
		return __( 'ProfilePress Order', 'knit-pay-lang' );
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
		if ( defined( 'PPRESS_MEMBERSHIP_ORDERS_SETTINGS_PAGE' ) ) {
			return add_query_arg(
				[
					'ppress_order_action' => 'edit',
					'id'                  => $payment->source_id,
				],
				PPRESS_MEMBERSHIP_ORDERS_SETTINGS_PAGE 
			);
		}
		return $url;
	}

}
