<?php

namespace KnitPay\Extensions\UncannyAutomator\Actions;

use KnitPay\Extensions\UncannyAutomator\KnitPayTokens;
use WP_REST_Request;
use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Money\Currencies;

/**
 * Title: Uncanny Automator Create Payment Action
 * Description:
 * Copyright: 2020-2026 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   8.99.0.0
 */
class CreatePaymentAction extends \Uncanny_Automator\Recipe\Action {
 
	protected function setup_action() {
 
		// Define the Actions's info
		$this->set_integration( 'KNIT_PAY' );
		$this->set_action_code( 'CREATE_PAYMENT' );
		$this->set_action_meta( 'KNIT_PAY_PAYMENT_AMOUNT' );

		$this->set_requires_user( false );
 
		// Define the Action's sentence
		$this->set_sentence( sprintf( esc_attr__( 'Create a Knit Pay payment of amount {{amount:%1$s}} {{currency:%2$s}}.', 'knit-pay-lang' ), $this->get_action_meta(), 'KNIT_PAY_PAYMENT_CURRENCY' ) );
		$this->set_readable_sentence( esc_attr__( 'Create a Knit Pay payment of {{amount}}', 'knit-pay-lang' ) );
	}

	public function options() {
		$payment_configurations = Plugin::get_config_select_options( 'knit_pay' );
		$payment_config_options = [];
		foreach ( $payment_configurations as $key => $payment_config ) {
			$payment_config_options[] = [
				'value' => $key,
				'text'  => $payment_config,
			];
		}

		$currency_options = [];
		foreach ( Currencies::get_currencies() as $key => $currency ) {
			$currency_options[] = [
				'value' => $key,
				'text'  => $currency->get_name() . ' (' . $key . ')',
			];
		}

		return [
			[
				'option_code'           => 'KNIT_PAY_CONFIG_ID',
				'label'                 => __( 'Configuration', 'knit-pay-lang' ),
				'input_type'            => 'select',
				'description'           => 'Configurations can be created in Knit Pay gateway configurations page at <a href="' . admin_url( 'edit.php?post_type=pronamic_gateway' ) . '">"Knit Pay >> Configurations"</a>.',
				'placeholder'           => __( 'Select Configuration', 'knit-pay-lang' ),
				'options'               => $payment_config_options,
				'required'              => false,
				'supports_custom_value' => false,
			],
			Automator()->helpers->recipe->field->float(
				[
					'option_code' => 'KNIT_PAY_PAYMENT_AMOUNT',
					'label'       => __( 'Payment Amount', 'knit-pay-lang' ),
					'description' => __( 'Amount to be collected', 'knit-pay-lang' ),
					'placeholder' => __( 'Enter payment amount', 'knit-pay-lang' ),
					'default'     => 100.00,
				]
			),
			[
				'option_code'              => 'KNIT_PAY_PAYMENT_CURRENCY',
				'label'                    => __( 'Payment Currency', 'knit-pay-lang' ),
				'input_type'               => 'select',
				'description'              => __( 'Currency of the payment', 'knit-pay-lang' ),
				'placeholder'              => __( 'Enter payment currency', 'knit-pay-lang' ),
				'default'                  => 'INR',
				'options'                  => $currency_options,
				'custom_value_description' => __( 'Enter currency code (eg. INR)', 'knit-pay-lang' ),
				'supports_custom_value'    => true,
			],
			Automator()->helpers->recipe->field->text(
				[
					'option_code' => 'KNIT_PAY_PAYMENT_DESCRIPTION',
					'label'       => __( 'Payment Description', 'knit-pay-lang' ),
					'description' => sprintf( __( 'Available tags: %s', 'knit-pay-lang' ), sprintf( '<code>%s</code>', '{action_log_id}' ) ) . 'Default: Action {action_log_id}',
					'placeholder' => __( 'Enter payment currency', 'knit-pay-lang' ),
					'default'     => 'Action {action_log_id}',
				]
			),
		];
	}

	public function define_tokens() {
		return [
			'KNIT_PAY_REDIRECT_URL' => [
				'name' => __( 'Knit Pay Redirect URL', 'knit-pay-lang' ),
				'type' => 'url',
			],
		];
	}

	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$action_meta = $action_data['meta'];

		// Get payment details from action meta
		$amount      = floatval( Automator()->parse->text( $action_meta['KNIT_PAY_PAYMENT_AMOUNT'], $recipe_id, $user_id, $args ) );
		$currency    = Automator()->parse->text( $action_meta['KNIT_PAY_PAYMENT_CURRENCY'], $recipe_id, $user_id, $args );
		$description = Automator()->parse->text( $action_meta['KNIT_PAY_PAYMENT_DESCRIPTION'], $recipe_id, $user_id, $args );
		$config_id   = Automator()->parse->text( $action_meta['KNIT_PAY_CONFIG_ID'], $recipe_id, $user_id, $args );

		// Create Payment Description dynamically.
		if ( empty( $description ) ) {
			$description = 'Action {action_log_id}';
		}

		// Replacements.
		$replacements = [
			'{action_log_id}' => $action_data['action_log_id'],
		];

		$description = strtr( $description, $replacements );

		// Prepare payment data
		$payment_data = [
			'source'       => [
				'key'   => 'uncanny-automator',
				'value' => $action_data['action_log_id'],
			],
			'total_amount' => [
				'value'    => $amount,
				'currency' => $currency,
			],
			'description'  => $description,
			'config_id'    => $config_id,
			// 'customer'        => $customer_array,
			// 'billing_address' => $customer_array,
		];

		// Call REST API endpoint using WP_REST_Request
		$request = new WP_REST_Request( 'POST', '/knit-pay/v1/payments' );
		$request->add_header( 'X-KnitPay-Internal-Nonce', wp_create_nonce( 'knit_pay_internal_api' ) );
		$request->set_body_params( $payment_data );
		
		$response = rest_do_request( $request );
		
		if ( $response->is_error() ) {
			// Convert to a WP_Error object.
			$error = $response->as_error();
			$this->add_log_error( 'Payment creation failed: ' . $error->get_error_message() );
			return false;
		}

		$response_data = $response->get_data();
		
		if ( ! isset( $response_data['id'] ) ) {
			$this->add_log_error( 'Invalid response from payment API' );
			return false;
		}

		// Set the redirect URL token
		$payment = get_pronamic_payment( $response_data['id'] );
		$this->hydrate_tokens( KnitPayTokens::hydrate_payment_tokens( $payment ) );

		return true;
	}
}
