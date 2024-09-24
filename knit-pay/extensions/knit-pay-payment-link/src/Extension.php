<?php

namespace KnitPay\Extensions\KnitPayPaymentLink;

use Pronamic\WordPress\Pay\AbstractPluginIntegration;
use Pronamic\WordPress\Pay\Payments\Payment;

/**
 * Title: Knit Pay - Payment Link extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   4.6.0
 */
class Extension extends AbstractPluginIntegration {
	/**
	 * Slug
	 *
	 * @var string
	 */
	const SLUG = 'knit-pay-payment-link';

	/**
	 * Constructs and initialize Lifter LMS extension.
	 */
	public function __construct() {
		parent::__construct(
			[
				'name' => __( 'Knit Pay - Payment Link', 'knit-pay-lang' ),
			]
		);
	}

	/**
	 * Setup plugin integration.
	 *
	 * @return void
	 */
	public function setup() {
		add_filter( 'pronamic_payment_source_text_' . self::SLUG, [ $this, 'source_text' ], 10, 2 );
		add_filter( 'pronamic_payment_source_description_' . self::SLUG, [ $this, 'source_description' ], 10, 2 );

		// Create Payment Link Menu.
		add_action( 'admin_menu', [ $this, 'admin_menu' ] );

		Gateway::instance();
	}

	/**
	 * Create the admin menu.
	 *
	 * @return void
	 */
	public function admin_menu() {
		\add_submenu_page(
			'pronamic_ideal',
			__( 'Payment Link', 'knit-pay-lang' ),
			__( 'Create Payment Link', 'knit-pay-lang' ),
			'edit_payments',
			'knit_pay_payment_link',
			function() {
				include 'views/page-payment-link.php';
			},
			2
		);
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
		if ( ! empty( $payment->source_id ) ) {
			$text = __( 'Knit Pay - Payment Link', 'knit-pay-lang' ) . '<br />';

			/* translators: %s: source id */
			$text .= sprintf( __( '<strong>Ref Id:</strong> %s', 'knit-pay-lang' ), $payment->source_id );
		}

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
		return __( 'Knit Pay - Payment Link', 'knit-pay-lang' );
	}
}
