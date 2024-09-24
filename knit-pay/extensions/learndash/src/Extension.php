<?php

namespace KnitPay\Extensions\LearnDash;

use Pronamic\WordPress\Pay\AbstractPluginIntegration;
use Pronamic\WordPress\Pay\Payments\Payment;

/**
 * Title: Learn Dash LMS extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   2.7.0
 */
class Extension extends AbstractPluginIntegration {
	/**
	 * Slug
	 *
	 * @var string
	 */
	const SLUG = 'learndash';

	/**
	 * Constructs and initialize Learn Dash LMS extension.
	 */
	public function __construct() {
		parent::__construct(
			[
				'name' => __( 'Learn Dash', 'knit-pay-lang' ),
			]
		);

		// Dependencies.
		$dependencies = $this->get_dependencies();

		$dependencies->add( new LearnDashDependency() );
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

		add_filter( 'learndash_payment_gateways', [ $this, 'learndash_payment_gateways' ] );

		add_action(
			'learndash_settings_sections_init',
			[ KnitPaySettingsSection::class, 'add_section_instance' ]
		);
	}

	public function learndash_payment_gateways( $gateways ) {
		$gateways[] = new Gateway();
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
		$gateway = new Gateway();

		$products = $gateway->setup_products_or_fail( (int) ( $payment->get_order_id() ?? 0 ) );

		return $gateway->get_url_success(
			$products,
			''
		);
	}

	/**
	 * Update the status of the specified payment
	 *
	 * @param Payment $payment Payment.
	 */
	public static function status_update( Payment $payment ) {
		$gateway = new Gateway();
		$gateway->status_update( $payment );
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
		$text = __( 'Learn Dash LMS', 'knit-pay-lang' ) . '<br />';

		if ( $payment->get_source_id() === $payment->get_order_id() ) {
			$text .= sprintf(
				'<a href="%s">%s</a>',
				get_edit_post_link( $payment->get_source_id() ),
				/* translators: %s: source id */
				sprintf( __( 'Course %s', 'knit-pay-lang' ), $payment->get_source_id() )
			);
			return $text;
		}

		$text .= sprintf(
			'<a href="%s">%s</a>',
			get_edit_post_link( $payment->get_source_id() ),
			/* translators: %s: source id */
			sprintf( __( 'Transaction %s', 'knit-pay-lang' ), $payment->get_source_id() )
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
		if ( $payment->get_source_id() === $payment->get_order_id() ) {
			return __( 'Learn Dash LMS Course', 'knit-pay-lang' );
		}
		return __( 'Learn Dash LMS Transaction', 'knit-pay-lang' );
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
			return get_edit_post_link( $payment->get_source_id() );
	}
}
