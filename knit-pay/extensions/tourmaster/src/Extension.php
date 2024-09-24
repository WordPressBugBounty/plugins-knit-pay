<?php

namespace KnitPay\Extensions\TourMaster;

use Pronamic\WordPress\Pay\AbstractPluginIntegration;
use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Core\PaymentMethods;

/**
 * Title: Tour Master extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   2.1.0
 * @version 8.85.13.0
 */
class Extension extends AbstractPluginIntegration {
	/**
	 * Slug
	 *
	 * @var string
	 */
	const SLUG = 'tourmaster';

	/**
	 * Constructs and initialize Tour Master extension.
	 */
	public function __construct() {
		parent::__construct(
			[
				'name' => __( 'Tour Master', 'knit-pay-lang' ),
			]
		);

		// Dependencies.
		$dependencies = $this->get_dependencies();

		$dependencies->add( new TourMasterDependency() );
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

		Gateway::instance( 'knit_pay', 'Default', 'knit_pay' );
		$active_payment_methods = PaymentMethods::get_active_payment_methods();
		foreach ( $active_payment_methods as $payment_method ) {
			Gateway::instance( 'knit-pay-' . $payment_method, PaymentMethods::get_name( $payment_method, ucwords( $payment_method ) ), $payment_method );
		}

		add_filter( 'tourmaster_additional_payment_method', [ $this, 'tourmaster_additional_payment_method' ] );

		add_filter( 'tourmaster_custom_payment_enable', [ $this, 'tourmaster_custom_payment_enable' ], 10, 2 );
	}

	public function tourmaster_additional_payment_method( $ret ) {
		$tourmaster_payment_option = tourmaster_get_option( 'payment' );

		if ( empty( $tourmaster_payment_option ) ) {
			return $ret;
		}

		$tourmaster_payment_option['payment-method'] = array_diff( $tourmaster_payment_option['payment-method'], [ 'booking' ] );
		if ( 1 < count( $tourmaster_payment_option['payment-method'] ) ) {
			$ret .= '<script type="text/javascript">';
			$ret .= 'jQuery(".tourmaster-payment-method-wrap ").removeClass("tourmaster-none-online-payment");';
			$ret .= 'jQuery(".tourmaster-payment-method-wrap ").addClass("tourmaster-both-online-payment");';
			$ret .= '</script>';
		}

		return $ret;
	}

	public function tourmaster_custom_payment_enable( $custom_enabled, $payment_method ) {
		// FIXME check if knit pay activated before returning true.
		return true;
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
		$tid = $payment->get_source_id();

		switch ( $payment->get_status() ) {
			case Core_Statuses::CANCELLED:
			case Core_Statuses::EXPIRED:
			case Core_Statuses::FAILURE:
				$url = add_query_arg( [], tourmaster_get_template_url( 'payment' ) );

				break;

			case Core_Statuses::SUCCESS:
				$url = add_query_arg(
					[
						'tid'            => $tid,
						'step'           => 4,
						'payment_method' => 'paypal', // TODO Tourmaster not showing correct success page with $payment->get_payment_method(),
					],
					tourmaster_get_template_url( 'payment' )
				);
				break;

			case Core_Statuses::AUTHORIZED:
			case Core_Statuses::OPEN:
			default:
				$url = home_url( '/' );
		}

		return $url;
	}

	/**
	 * Update the status of the specified payment
	 *
	 * @param Payment $payment Payment.
	 */
	public static function status_update( Payment $payment ) {

		switch ( $payment->get_status() ) {
			case Core_Statuses::CANCELLED:
			case Core_Statuses::EXPIRED:
			case Core_Statuses::FAILURE:
				self::payment_fail_action( $payment );
				break;
			case Core_Statuses::SUCCESS:
				self::payment_success_action( $payment );

				break;
			case Core_Statuses::OPEN:
			default:
				break;
		}
	}

	private static function payment_fail_action( $payment ) {
		$tid                             = $payment->get_source_id();
		$payment_info['payment_method']  = $payment->get_payment_method();
		$payment_info['submission_date'] = current_time( 'mysql' );
		$payment_info['error']           = 'Payment ' . $payment->get_status();

		$payment_info['transaction_id'] = $payment->get_transaction_id();

		tourmaster_update_booking_data(
			[
				'payment_info' => wp_json_encode( $payment_info ),
			],
			[
				'id'           => $tid,
				'payment_date' => '0000-00-00 00:00:00',
			],
			[ '%s' ],
			[ '%d', '%s' ]
		);
	}

	private static function payment_success_action( Payment $payment ) {
		// collect payment information
		$payment_info = [
			'payment_method'  => $payment->get_payment_method(),
			'amount'          => $payment->get_total_amount()->get_value(),
			'transaction_id'  => $payment->get_transaction_id(),
			'payment_status'  => $payment->get_status(),
			'submission_date' => current_time( 'mysql' ),
		];

		$tid          = $payment->get_source_id();
		$result       = tourmaster_get_booking_data( [ 'id' => $tid ], [ 'single' => true ] );
		$pricing_info = json_decode( $result->pricing_info, true );

		if ( ! empty( $pricing_info['deposit-price'] ) && tourmaster_compare_price( $pricing_info['deposit-price'], $payment_info['amount'] ) ) {
			if ( ! empty( $pricing_info['deposit-price-raw'] ) ) {
				$payment_info['deposit_amount'] = $pricing_info['deposit-price-raw'];
			}
		} elseif ( tourmaster_compare_price( $pricing_info['pay-amount'], $payment_info['amount'] ) ) {
			if ( ! empty( $pricing_info['pay-amount-raw'] ) ) {
				$payment_info['pay_amount'] = $pricing_info['pay-amount-raw'];
			}
		}

		// update data
		ob_start();
		do_action( 'goodlayers_set_payment_complete', $tid, $payment_info );

		// Clean warnings from above action.
		ob_end_clean();
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
		$text = __( 'Tour Master', 'knit-pay-lang' ) . '<br />';

		$text .= sprintf(
			'<a href="%s">%s</a>',
			$this->source_url( '', $payment ),
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
		return __( 'Tour Master Order', 'knit-pay-lang' );
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
		return add_query_arg(
			[
				'single' => $payment->source_id,
				'page'   => 'tourmaster_order',
			],
			get_admin_url( null, 'admin.php' )
		);
	}

}
