<?php

namespace KnitPay\Extensions\FluentForms;

use Pronamic\WordPress\Pay\AbstractPluginIntegration;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: Fluent Forms extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   8.92.0.0
 */
class Extension extends AbstractPluginIntegration {
	/**
	 * Slug
	 *
	 * @var string
	 */
	const SLUG = 'fluent-forms';

	/**
	 * Constructs and initialize Fluent Forms extension.
	 */
	public function __construct() {
		parent::__construct(
			[
				'name' => __( 'Fluent Forms', 'knit-pay-lang' ),
			]
		);

		// Dependencies.
		$dependencies = $this->get_dependencies();

		$dependencies->add( new FluentFormsDependency() );
	}

	/**
	 * Setup plugin integration.
	 *
	 * @return void
	 */
	public function setup() {
		add_filter( 'pronamic_payment_source_description_' . self::SLUG, [ $this, 'source_description' ], 10, 2 );

		// Check if dependencies are met and integration is active.
		if ( ! $this->is_active() ) {
			return;
		}

		// Add this filter only if Fluent Forms is active. Because it has dependency on Fluent Forms.
		add_filter( 'pronamic_payment_source_text_' . self::SLUG, [ $this, 'source_text' ], 10, 2 );
		add_filter( 'pronamic_payment_source_url_' . self::SLUG, [ $this, 'source_url' ], 10, 2 );

		add_filter( 'pronamic_payment_redirect_url_' . self::SLUG, [ $this, 'redirect_url' ], 10, 2 );
		add_action( 'pronamic_payment_status_update_' . self::SLUG, [ $this, 'status_update' ], 10 );

		( new KnitPayPaymentMethod( 'knit_pay' ) )->init();
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
		return add_query_arg(
			[
				'fluentform_payment' => $payment->get_source_id(),
				'payment_method'     => 'knit_pay',
				'transaction_hash'   => $payment->get_meta( 'fluentforms_transaction_hash' ),
			],
			site_url( '/' )
		);
	}

	/**
	 * Update the status of the specified payment
	 *
	 * @param Payment $payment Payment.
	 */
	public static function status_update( Payment $payment ) {
		$processor = new GatewayProcessor();
		$processor->setSubmissionId( $payment->get_source_id() );

		// Check if actions are fired
		if ( $processor->getMetaData( 'is_form_action_fired' ) == 'yes' ) {
			return $processor->completePaymentSubmission( false );
		}

		$transaction_hash = $payment->get_meta( 'fluentforms_transaction_hash' );
		$transaction      = $processor->getTransaction( $transaction_hash, 'transaction_hash' );

		switch ( $payment->get_status() ) {
			case Core_Statuses::CANCELLED:
			case Core_Statuses::EXPIRED:
			case Core_Statuses::FAILURE:
				$status = 'failed';

				break;
			case Core_Statuses::SUCCESS:
				$status = 'paid';
				$processor->setMetaData( 'is_form_action_fired', 'yes' );


				break;
			case Core_Statuses::OPEN:
			default:
				$status = 'pending';

				break;
		}

		// Let's make the payment as paid
		$updateData = [
			'payment_note' => maybe_serialize(
				[
					'knitpay_payment_id' => $payment->get_id(),
				]
			),
			'charge_id'    => sanitize_text_field( $payment->get_transaction_id() ),
		];

		$processor->updateTransaction( $transaction->id, $updateData );
		$processor->changeSubmissionPaymentStatus( $status );
		$processor->changeTransactionStatus( $transaction->id, $status );
		$processor->recalculatePaidTotal();
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
		$text = __( 'Fluent Forms', 'knit-pay-lang' ) . '<br />';

		$text .= sprintf(
			'<a href="%s">%s</a>',
			$this->source_url( '', $payment ),
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
		return __( 'Fluent Forms Entry', 'knit-pay-lang' );
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
		$processor = new GatewayProcessor();
		$processor->setSubmissionId( $payment->get_source_id() );

		$submission = $processor->getSubmission();
		$entry_url  = admin_url( 'admin.php?page=fluent_forms&route=entries&form_id=' . $submission->form_id . '#/entries/' . $submission->id );

		return $entry_url;
	}

}
