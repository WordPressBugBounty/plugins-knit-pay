<?php

namespace KnitPay\Extensions\Camptix;

use Pronamic\WordPress\Pay\AbstractPluginIntegration;
use Pronamic\WordPress\Pay\Core\PaymentMethods;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;
use CampTix_Plugin;

/**
 * Title: CampTix extension
 * Description:
 * Copyright: 2020-2025 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   8.74.0.0
 */
// Plugin available at https://github.com/WordPress/wordcamp.org/tree/production/public_html/wp-content/plugins/camptix
class Extension extends AbstractPluginIntegration {
	/**
	 * Slug
	 *
	 * @var string
	 */
	const SLUG = 'camptix';

	/**
	 * Constructs and initialize CampTix extension.
	 */
	public function __construct() {
		parent::__construct(
			[
				'name' => __( 'Camptix', 'knit-pay-lang' ),
			]
		);

		// Dependencies.
		$dependencies = $this->get_dependencies();

		$dependencies->add( new CamptixDependency() );
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

		/** @var CampTix_Plugin $camptix */
		global $camptix;
		$camptix->addons_loaded[] = new Gateway( 'knit_pay', 'Default' );
		foreach ( PaymentMethods::get_active_payment_methods() as $payment_method ) {
			$camptix->addons_loaded[] = new Gateway( 'knit_pay_' . $payment_method, PaymentMethods::get_name( $payment_method, ucwords( $payment_method ) ) );
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
		/** @var CampTix_Plugin $camptix */
		global $camptix;
		
		$attendee_id = $payment->get_order_id();
		
		switch ( $payment->get_status() ) {
			case Core_Statuses::CANCELLED:
				$url = self::get_error_url( 'payment_cancelled' );
				break;

			case Core_Statuses::FAILURE:
				$url = self::get_error_url( 'payment_failed' );
				break;
					
			case Core_Statuses::REFUNDED:
				break;

			case Core_Statuses::SUCCESS:
			case Core_Statuses::OPEN:
			default:
				$access_token = get_post_meta( $attendee_id, 'tix_access_token', true );
				$url          = $camptix->get_access_tickets_link( $access_token );
				break;
		}
		
		return $url;
	}

	/**
	 * Update the status of the specified payment
	 *
	 * @param Payment $payment Payment.
	 */
	public static function status_update( Payment $payment ) {
		/** @var CampTix_Plugin $camptix */
		global $camptix;
		
		$payment_data = [
			'transaction_id'      => $payment->transaction_id,
			'transaction_details' => [ 'knit_pay_payment_id' => $payment->get_id() ],
		];
		
		$camptix_payment_token = $payment->get_meta( 'camptix_payment_token' );
		if ( empty( $camptix_payment_token ) ) {
			return;
		}       

		switch ( $payment->get_status() ) {
			case Core_Statuses::CANCELLED:
				$camptix->payment_result( $camptix_payment_token, CampTix_Plugin::PAYMENT_STATUS_CANCELLED, $payment_data );
				
				break;
			case Core_Statuses::EXPIRED:
				$camptix->payment_result( $camptix_payment_token, CampTix_Plugin::PAYMENT_STATUS_TIMEOUT, $payment_data );
				
				break;
			case Core_Statuses::FAILURE:
				$camptix->payment_result( $camptix_payment_token, CampTix_Plugin::PAYMENT_STATUS_FAILED, $payment_data );

				break;
			case Core_Statuses::SUCCESS:
				$camptix->payment_result( $camptix_payment_token, CampTix_Plugin::PAYMENT_STATUS_COMPLETED, $payment_data );

				break;
			case Core_Statuses::OPEN:
			default:
				$camptix->payment_result( $camptix_payment_token, CampTix_Plugin::PAYMENT_STATUS_PENDING, $payment_data );

				break;
		}
	}
	
	private static function get_error_url( $error_code ) {
		/** @var CampTix_Plugin $camptix */
		global $camptix;
		
		$camptix->error_flag( $error_code );
		
		$query_args['tix_error']     = 1;
		$query_args['tix_errors[0]'] = $error_code;
		
		return esc_url_raw( add_query_arg( $query_args, $camptix->get_tickets_url() ) . '#tix' );
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
		$text = __( 'Camptix Ticket', 'knit-pay-lang' ) . '<br />';

		$text .= sprintf(
			'<a href="%s">%s</a>',
			get_edit_post_link( $payment->source_id ),
			/* translators: %s: source id */
			sprintf( __( 'Booking %s', 'knit-pay-lang' ), $payment->source_id )
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
		return __( 'Camptix Ticket Booking', 'knit-pay-lang' );
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
