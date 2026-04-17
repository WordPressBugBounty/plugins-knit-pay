<?php

namespace KnitPay\Extensions\UncannyAutomator\Triggers;

use KnitPay\Extensions\UncannyAutomator\KnitPayTokens;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;

/**
 * Title: Uncanny Automator Payment Status Update Trigger
 * Description:
 * Copyright: 2020-2026 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   8.99.0.0
 */
class PaymentStatusUpdateTrigger extends \Uncanny_Automator\Recipe\Trigger {
 
	protected function setup_trigger() {
 
		// Define the Trigger's info
		$this->set_integration( 'KNIT_PAY' );
		$this->set_trigger_code( 'PAYMENT_STATUS_UPDATE' );
		$this->set_trigger_meta( 'KNIT_PAY_PAYMENT_STATUS' );
 
		// Trigger sentence
		$this->set_sentence( sprintf( esc_attr__( 'Status of the payment is updated to {{a new status:%1$s}}.', 'knit-pay-lang' ), 'KNIT_PAY_PAYMENT_STATUS' ) );
		$this->set_readable_sentence( esc_attr__( 'Status of the payment is updated to {{a new status}}.', 'knit-pay-lang' ) );

		$this->set_is_login_required( false );
		$this->set_trigger_type( 'anonymous' );

		// Trigger wp hook
		$this->add_action( 'knit_pay_payment_status_update', 10, 4 );
	}

	public function options() {
 
		$payment_status_dropdown = [
			'input_type'               => 'select',
			'supports_multiple_values' => true,
			'option_code'              => 'KNIT_PAY_PAYMENT_STATUS',
			'label'                    => __( 'Knit Pay Payment Status', 'knit-pay-lang' ),
			'options'                  => [
				[
					'value' => PaymentStatus::OPEN,
					'text'  => __( 'Pending', 'knit-pay-lang' ),
				],
				[
					'value' => PaymentStatus::CANCELLED,
					'text'  => __( 'Cancelled', 'knit-pay-lang' ),
				],
				[
					'value' => PaymentStatus::FAILURE,
					'text'  => __( 'Failed', 'knit-pay-lang' ),
				],
				[
					'value' => PaymentStatus::SUCCESS,
					'text'  => __( 'Success', 'knit-pay-lang' ),
				],
				[
					'value' => PaymentStatus::AUTHORIZED,
					'text'  => __( 'Authorized', 'knit-pay-lang' ),
				],
				[
					'value' => PaymentStatus::EXPIRED,
					'text'  => __( 'Expired', 'knit-pay-lang' ),
				],
				[
					'value' => PaymentStatus::REFUNDED,
					'text'  => __( 'Refunded', 'knit-pay-lang' ),
				],
				[
					'value' => PaymentStatus::ON_HOLD,
					'text'  => __( 'On Hold', 'knit-pay-lang' ),
				],
			],
			'placeholder'              => __( 'Please select the payment status for which you want to trigger this recipe.', 'knit-pay-lang' ),
		];

		return [
			$payment_status_dropdown,
		];
	}

	public function validate( $trigger, $hook_args ) {
		// don't filter if particular payment statuses are not provided.
		if ( ! isset( $trigger['meta']['KNIT_PAY_PAYMENT_STATUS'] ) ) {
			return true;
		}

		// Get the dropdown value
		$selected_payment_status = $trigger['meta']['KNIT_PAY_PAYMENT_STATUS'];
		$selected_payment_status = json_decode( $selected_payment_status, true );

		// Parse the args from the pronamic_payment_status_update hook
		list( $payment, $can_redirect, $previous_status, $updated_status ) = $hook_args;

		if ( empty( $selected_payment_status ) || in_array( $payment->get_status(), $selected_payment_status ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Returns the trigger's tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {

		$tokens[] = [
			'tokenId'   => 'KNIT_PAY_PAYMENT_ID',
			'tokenName' => __( 'Knit Pay Payment ID', 'knit-pay-lang' ),
			'tokenType' => 'text',
		];
		
		$tokens[] = [
			'tokenId'   => 'KNIT_PAY_PAYMENT_STATUS_PREVIOUS',
			'tokenName' => __( 'Knit Pay Payment Previous Status', 'knit-pay-lang' ),
			'tokenType' => 'text',
		];

		$tokens[] = [
			'tokenId'   => 'KNIT_PAY_TRANSACTION_ID',
			'tokenName' => __( 'Knit Pay Transaction ID', 'knit-pay-lang' ),
			'tokenType' => 'text',
		];

		$tokens[] = [
			'tokenId'   => 'KNIT_PAY_ORDER_ID',
			'tokenName' => __( 'Knit Pay Order ID', 'knit-pay-lang' ),
			'tokenType' => 'text',
		];

		$tokens[] = [
			'tokenId'   => 'KNIT_PAY_SOURCE_ID', 
			'tokenName' => __( 'Knit Pay Source ID', 'knit-pay-lang' ),
			'tokenType' => 'text',
		];

		$tokens[] = [
			'tokenId'   => 'KNIT_PAY_SOURCE',
			'tokenName' => __( 'Knit Pay Source', 'knit-pay-lang' ),
			'tokenType' => 'text',
		];

		$tokens[] = [
			'tokenId'   => 'KNIT_PAY_PAYMENT_DESCRIPTION',
			'tokenName' => __( 'Knit Pay Payment Description', 'knit-pay-lang' ),
			'tokenType' => 'text',
		];

		$tokens[] = [
			'tokenId'   => 'KNIT_PAY_REDIRECT_URL',
			'tokenName' => __( 'Knit Pay Redirect URL', 'knit-pay-lang' ),
			'tokenType' => 'url',
		];

		$tokens[] = [
			'tokenId'   => 'KNIT_PAY_PAYMENT_METHOD',
			'tokenName' => __( 'Knit Pay Payment Method', 'knit-pay-lang' ),
			'tokenType' => 'text', 
		];

		$tokens[] = [
			'tokenId'   => 'KNIT_PAY_PAYMENT_AMOUNT',
			'tokenName' => __( 'Knit Pay Payment Amount', 'knit-pay-lang' ),
			'tokenType' => 'text',
		];

		$tokens[] = [
			'tokenId'   => 'KNIT_PAY_PAYMENT_CURRENCY',
			'tokenName' => __( 'Knit Pay Payment Currency', 'knit-pay-lang' ),
			'tokenType' => 'text',
		];

		$tokens[] = [
			'tokenId'   => 'KNIT_PAY_CUSTOMER_NAME',
			'tokenName' => __( 'Knit Pay Customer Name', 'knit-pay-lang' ),
			'tokenType' => 'text',
		];

		$tokens[] = [
			'tokenId'   => 'KNIT_PAY_CUSTOMER_EMAIL',
			'tokenName' => __( 'Knit Pay Customer Email', 'knit-pay-lang' ),
			'tokenType' => 'text',
		];

		$tokens[] = [
			'tokenId'   => 'KNIT_PAY_BILLING_PHONE',
			'tokenName' => __( 'Knit Pay Billing Phone', 'knit-pay-lang' ),
			'tokenType' => 'text',
		];

		return $tokens;
	}

	public function hydrate_tokens( $trigger, $hook_args ) {
		list( $payment, $can_redirect, $previous_status, $updated_status ) = $hook_args;

		$token_values = KnitPayTokens::hydrate_payment_tokens( $payment );

		return array_merge(
			$token_values, 
			[
				'KNIT_PAY_PAYMENT_STATUS_PREVIOUS' => $previous_status,
			]
		);
	}
}
