<?php

namespace KnitPay\Extensions\KnitPayPaymentButton;

use Pronamic\WordPress\Pay\AbstractPluginIntegration;
use Pronamic\WordPress\Pay\Payments\Payment;

/**
 * Title: Knit Pay - Payment Button extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   8.75.0.0
 */
class Extension extends AbstractPluginIntegration {
	/**
	 * Slug
	 *
	 * @var string
	 */
	const SLUG = 'knit-pay-payment-button';

	/**
	 * Constructs and initialize Lifter LMS extension.
	 */
	public function __construct() {
		parent::__construct(
			[
				'name' => __( 'Knit Pay - Payment Button', 'knit-pay-lang' ),
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

		Gateway::instance();
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
			$text = __( 'Knit Pay - Payment Button', 'knit-pay-lang' ) . '<br />';

			/* translators: %s: source id */
			$text .= sprintf( __( '<strong>Order Id:</strong> %s', 'knit-pay-lang' ), $payment->order_id );
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
		return __( 'Knit Pay - Payment Button', 'knit-pay-lang' );
	}
}
