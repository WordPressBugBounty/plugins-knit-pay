<?php

namespace KnitPay\Extensions\LifterLMS;

use Pronamic\WordPress\Pay\AbstractPluginIntegration;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;
use LLMS_Order;

/**
 * Title: Lifter LMS extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   1.8.0
 */
class Extension extends AbstractPluginIntegration {
	/**
	 * Slug
	 *
	 * @var string
	 */
	const SLUG = 'lifterlms';

	/**
	 * Constructs and initialize Lifter LMS extension.
	 */
	public function __construct() {
		parent::__construct(
			[
				'name' => __( 'Lifter LMS', 'knit-pay-lang' ),
			]
		);

		// Dependencies.
		$dependencies = $this->get_dependencies();

		$dependencies->add( new LifterLMSDependency() );
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

		add_filter( 'lifterlms_payment_gateways', [ $this, 'add_core_gateways' ] );
	}

	public static function add_core_gateways( $gateways ) {
		$gateways[] = '\KnitPay\Extensions\LifterLMS\Gateway';

		return $gateways;
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
		$order     = new LLMS_Order( $source_id );

		switch ( $payment->get_status() ) {
			case Core_Statuses::CANCELLED:
			case Core_Statuses::EXPIRED:
			case Core_Statuses::FAILURE:
				llms_add_notice(
					sprintf(
						'Payment with Payment ID: %s, Transaction ID: %s Failed.',
						$payment->get_id(),
						$payment->transaction_id
					),
					'error'
				);
				return esc_url( get_permalink( get_option( 'lifterlms_checkout_page_id' ) ) );

				break;

			case Core_Statuses::SUCCESS:
				$order->get_gateway()->complete_transaction( $order );
				break;

			case Core_Statuses::AUTHORIZED:
			case Core_Statuses::OPEN:
			default:
		}

		return $url;
	}

	/**
	 * Update the status of the specified payment
	 *
	 * @param Payment $payment Payment.
	 */
	public static function status_update( Payment $payment ) {

		$source_id = (int) $payment->get_source_id();
		$order     = new LLMS_Order( $source_id );

		switch ( $payment->get_status() ) {
			case Core_Statuses::CANCELLED:
			case Core_Statuses::EXPIRED:
				$order->set_status( 'llms-cancelled' );

				break;
			case Core_Statuses::FAILURE:
				$order->set_status( 'llms-failed' );
				// TODO: card getting empty after failed payment. fix it

				break;
			case Core_Statuses::SUCCESS:
				$order->record_transaction(
					[
						'amount'         => $payment->get_total_amount()->number_format( null, '.', '' ),
						'completed_date' => $payment->get_date()->getTimestamp(),
						'transaction_id' => $payment->get_transaction_id(),
						'status'         => 'llms-txn-succeeded',
						'payment_type'   => 'single',
					]
				);
				$order->set_status( 'llms-completed' );

				break;
			case Core_Statuses::OPEN:
			default:
				$order->set_status( 'llms-pending' );

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
		$text = __( 'Lifter LMS', 'knit-pay-lang' ) . '<br />';

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
		return __( 'Lifter LMS Order', 'knit-pay-lang' );
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
