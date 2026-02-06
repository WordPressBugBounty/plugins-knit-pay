<?php

namespace KnitPay\Extensions\TutorLMS;

use Pronamic\WordPress\Pay\AbstractPluginIntegration;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;
use Ollyo\PaymentHub\Core\Support\System;
use Tutor\Models\OrderModel;
use Tutor\Ecommerce\CheckoutController;

/**
 * Title: Tutor LMS extension
 * Description:
 * Copyright: 2020-2026 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   8.97.0.0
 */
class Extension extends AbstractPluginIntegration {
	/**
	 * Slug
	 *
	 * @var string
	 */
	const SLUG = 'tutor-lms';

	/**
	 * Constructs and initialize Tutor LMS extension.
	 */
	public function __construct() {
		parent::__construct(
			[
				'name' => __( 'Tutor LMS', 'knit-pay-lang' ),
			]
		);

		// Dependencies.
		$dependencies = $this->get_dependencies();

		$dependencies->add( new TutorLMSDependency() );
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

		add_filter( 'tutor_payment_gateways_with_class', [ __CLASS__, 'filter_payment_gateways' ] ); // For Checkout Integration
		
		new Gateway();
	}

	
	/**
	 * Get payment gateways with reference class
	 *
	 * @since 1.0.0
	 *
	 * @param array $gateways Tutor payment gateways.
	 *
	 * @return array|null
	 */
	public static function filter_payment_gateways( $gateways ) {
		
		$gateways['knit_pay'] = [
			'gateway_class' => Gateway::class,
			'config_class'  => TutorConfig::class,
		];

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
		$payment_urls = new TutorConfig();
		$order_id     = (int) $payment->get_source_id();

		switch ( $payment->get_status() ) {
			case Core_Statuses::CANCELLED:
			case Core_Statuses::EXPIRED:
			case Core_Statuses::FAILURE:
				$url = $payment_urls->getCancelUrl();

				break;

			case Core_Statuses::SUCCESS:
				$url = $payment_urls->getSuccessUrl();
				break;

			case Core_Statuses::AUTHORIZED:
			case Core_Statuses::OPEN:
			default:
				$url = CheckoutController::get_page_url();
		}

		$url = add_query_arg( 'order_id', $order_id, $url );

		return $url;
	}

	/**
	 * Update the status of the specified payment
	 *
	 * @param Payment $payment Payment.
	 */
	public static function status_update( Payment $payment ) {
		$order_id   = (int) $payment->get_source_id();
		$order_data = System::defaultOrderData();
		
		// Maps the transaction status from the Knit Pay to Tutor LMS status labels.
		$tutor_lms_status_map = [
			Core_Statuses::CANCELLED => OrderModel::PAYMENT_FAILED,
			Core_Statuses::EXPIRED   => OrderModel::PAYMENT_FAILED,
			Core_Statuses::FAILURE   => OrderModel::PAYMENT_FAILED,
			Core_Statuses::SUCCESS   => OrderModel::PAYMENT_PAID,
			Core_Statuses::REFUNDED  => OrderModel::PAYMENT_REFUNDED,
			Core_Statuses::OPEN      => OrderModel::PAYMENT_UNPAID,
		];

		$order_data->id                   = $order_id;
		$order_data->payment_status       = $tutor_lms_status_map[ $payment->get_status() ] ?? $payment->get_status();
		$order_data->payment_error_reason = $payment->get_failure_reason();
		$order_data->transaction_id       = $payment->get_transaction_id();
		$order_data->payment_method       = $payment->get_payment_method();
		$order_data->payment_payload      = '';
		$order_data->fees                 = '';
		$order_data->earnings             = $payment->get_total_amount()->number_format( null, '.', '' );

		do_action( 'tutor_order_payment_updated', $order_data );
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
		$text = __( 'Tutor LMS', 'knit-pay-lang' ) . '<br />';

		$text .= sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'admin.php?page=tutor_orders&action=edit&id=' . $payment->source_id ),
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
		return __( 'Tutor LMS Order', 'knit-pay-lang' );
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
		return admin_url( 'admin.php?page=tutor_orders&action=edit&id=' . $payment->source_id );
	}
}
