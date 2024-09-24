<?php

namespace KnitPay\Extensions\WPForms;

use Pronamic\WordPress\Pay\AbstractPluginIntegration;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: WPForms extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   5.9.0.0
 */
class Extension extends AbstractPluginIntegration {
	/**
	 * Slug
	 *
	 * @var string
	 */
	const SLUG = 'wpforms';

	/**
	 * Constructs and initialize WPForms LMS extension.
	 */
	public function __construct() {
		parent::__construct(
			[
				'name' => __( 'WPForms', 'knit-pay-lang' ),
			]
		);

		// Dependencies.
		$dependencies = $this->get_dependencies();

		$dependencies->add( new WPFormsDependency() );
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

		add_action( 'plugins_loaded', [ $this, 'load_gateway' ] );
		add_filter( 'wpforms_entry_details_payment_transaction', [ $this, 'display_transaction_id' ], 10, 4 );
		add_filter( 'wpforms_entry_details_payment_gateway', [ $this, 'display_payment_method' ], 10, 4 );
		add_action( 'wpforms_entry_payment_sidebar_actions', [ $this, 'display_payment_details' ], 10, 2 );
		add_filter( 'wpforms_currencies', [ $this, 'wpforms_currencies' ], 10, 1 );
	}
	
	public function wpforms_currencies( $currencies ) {
		$currencies['INR'] = [
			'name'                => esc_html__( 'Indian Rupee', 'wpforms' ),
			'symbol'              => '&#8377;',
			'symbol_pos'          => 'left',
			'thousands_separator' => ',',
			'decimal_separator'   => '.',
			'decimals'            => 2,
		];
		return $currencies;
	}
	
	public function display_payment_details( $entry, $form_data ) {
		$payment_details = json_decode( $entry->meta );
		if ( ! empty( $payment_details->knit_pay_payment_id ) ) {
			echo '<p>' . ( sprintf( __( 'Knit Pay Payment ID: %s', 'knit-pay-lang' ), '<strong>' . $payment_details->knit_pay_payment_id . '</strong>' ) ) . '</p>';
		}
	}
	
	public function display_transaction_id( $null, $entry_meta, $entry, $form_data ) {
		return $entry_meta['payment_transaction'];
	}
	
	public function display_payment_method( $null, $entry_meta, $entry, $form_data ) {
		return $entry_meta['payment_type'];
	}
	
	public function load_gateway() {
		return new Gateway();
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
		if ( ! $payment->get_meta( 'wpforms_return_url' ) ) {
			return $url;
		}
		
		if ( Core_Statuses::CANCELLED === $payment->get_status() ) {
			return $url;
		}
		
		return $payment->get_meta( 'wpforms_return_url' );
	}

	/**
	 * Update the status of the specified payment
	 *
	 * @param Payment $payment Payment.
	 */
	public static function status_update( Payment $payment ) {
		$source_id = (int) $payment->get_source_id();
		
		$payment_meta['payment_transaction'] = $payment->get_transaction_id();
		$payment_meta['payment_total']       = $payment->get_total_amount()->get_value();
		$payment_meta['payment_type']        = $payment->get_payment_method();
		$payment_meta['knit_pay_payment_id'] = $payment->get_id();
		
		$entry_data = [
			'type' => 'payment',
		];
		

		switch ( $payment->get_status() ) {
			case Core_Statuses::CANCELLED:
			case Core_Statuses::EXPIRED:
				$payment_meta['payment_note'] = 'Payment Cancelled.';
				$entry_data['status']         = 'failed';
				break;
			case Core_Statuses::FAILURE:
				$payment_meta['payment_note'] = 'Payment Failed.';
				$entry_data['status']         = 'failed';

				break;
			case Core_Statuses::SUCCESS:
				$entry_data['status'] = 'completed';

				break;
			case Core_Statuses::OPEN:
			default:
				$entry_data['status'] = 'pending';

				break;
		}
		$entry_data['meta'] = wp_json_encode( $payment_meta );
		
		wpforms()->entry->update( $source_id, $entry_data, '', '', [ 'cap' => false ] );
		
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
		$text = __( 'WPForms', 'knit-pay-lang' ) . '<br />';

		$text .= sprintf(
			'<a href="%s">%s</a>',
			add_query_arg(
				[
					'page'     => 'wpforms-entries',
					'view'     => 'details',
					'entry_id' => absint( $payment->source_id ),
				],
				admin_url( 'admin.php' ) 
			),
			/* translators: %s: source id */
			sprintf( __( 'Entry %s', 'knit-pay-lang' ), $payment->source_id )
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
		return __( 'WPForms Entry', 'knit-pay-lang' );
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
				'page'     => 'wpforms-entries',
				'view'     => 'details',
				'entry_id' => absint( $payment->source_id ),
			],
			admin_url( 'admin.php' ) 
		);
	}

}
