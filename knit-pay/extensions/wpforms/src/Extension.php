<?php

namespace KnitPay\Extensions\WPForms;

use Pronamic\WordPress\Pay\AbstractPluginIntegration;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: WPForms extension
 * Description:
 * Copyright: 2020-2025 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   5.9.0.0
 * @version 8.96.22.0
 */
class Extension extends AbstractPluginIntegration {
	private $gateway;

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
			'name'                => esc_html__( 'Indian Rupee', 'knit-pay-lang' ),
			'symbol'              => '&#8377;',
			'symbol_pos'          => 'left',
			'thousands_separator' => ',',
			'decimal_separator'   => '.',
			'decimals'            => 2,
		];
		$currencies['NPR'] = [
			'name'                => esc_html__( 'Nepalese Rupee', 'knit-pay-lang' ),
			'symbol'              => '&#8360;',
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
		$entry_id    = (int) $payment->get_source_id();
		$wpf_payment = wpforms()->obj( 'payment' )->get_by( 'entry_id', $entry_id );

		$payment_data = [
			'transaction_id' => $payment->get_transaction_id(),
			'title'          => $payment->get_description(),
		];

		switch ( $payment->get_status() ) {
			case Core_Statuses::CANCELLED:
			case Core_Statuses::EXPIRED:
			case Core_Statuses::FAILURE:
				$payment_data['status'] = 'failed';

				break;
			case Core_Statuses::SUCCESS:
				$payment_data['status'] = 'completed';

				self::send_email( $entry_id, $wpf_payment );
				break;
			case Core_Statuses::OPEN:
			default:
				$payment_data['status'] = 'pending';

				break;
		}

		wpforms()->obj( 'payment' )->update( $wpf_payment->id, $payment_data, '', '', [ 'cap' => false ] );
		wpforms()->obj( 'entry' )->update( $entry_id, [ 'type' => 'payment' ], '', '', [ 'cap' => false ] );
	}

	/**
	 * Referred from: wpforms-paypal-standard/src/Plugin.php method:process_ipn
	 * Send email notification for the completed payment.
	 *
	 * @param int                                $entry_id    Entry ID.
	 * @param $wpf_payment WPForms Payment object.
	 * @return void
	 */
	private static function send_email( $entry_id, $wpf_payment ) {
		$gateway_slug = 'knit_pay_knit_pay'; // Slug of Gateway, remomve hardcoded slug later if required.
		$form_data    = wpforms()->obj( 'form' )->get( $wpf_payment->form_id, [ 'content_only' => true ] );

		if ( empty( $form_data['settings']['notifications'] ) ) {
			return $form_data;
		}

		foreach ( $form_data['settings']['notifications'] as $id => $notification ) {
			if ( empty( $notification[ $gateway_slug ] ) ) {
				unset( $form_data['settings']['notifications'][ $id ] );
			}
		}

		$entry = wpforms()->obj( 'entry' )->get( $entry_id );
		if ( ! empty( $entry ) ) {
			// Send notification emails if configured.
			wpforms()->obj( 'process' )->entry_email( wpforms_decode( $entry->fields ), [], $form_data, $entry_id, $gateway_slug );
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
