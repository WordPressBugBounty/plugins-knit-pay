<?php
/**
 * Knit Pay – WordPress Abilities API integration.
 *
 * Registers two abilities so that AI agents and automation tools running on
 * WordPress 6.9+ can discover and invoke Knit Pay payment functionality in a
 * standardised, machine-readable way.
 *
 * Abilities registered:
 *  - knit-pay/create-payment  – create a new payment request and get a payment link
 *  - knit-pay/get-payment     – retrieve a payment and check its current status
 *
 * All schema definitions and execution logic are in PaymentApiHelper so they
 * are shared with PaymentRestController without duplication.
 *
 * @package KnitPay
 */

namespace KnitPay;

// Bail early if the Abilities API is not available (WordPress < 6.9).
if ( ! function_exists( 'wp_register_ability' ) ) {
	return;
}

// -------------------------------------------------------------------------
// Register ability category.
// -------------------------------------------------------------------------
add_action( 'wp_abilities_api_categories_init', __NAMESPACE__ . '\knit_pay_register_ability_categories' );

/**
 * Register the "Knit Pay" ability category.
 */
function knit_pay_register_ability_categories() {
	wp_register_ability_category(
		'knit-pay',
		[
			'label'       => __( 'Knit Pay', 'knit-pay-lang' ),
			'description' => __( 'Payment processing abilities provided by the Knit Pay plugin. Use these to create payment requests and check payment status.', 'knit-pay-lang' ),
		]
	);
}

// -------------------------------------------------------------------------
// Register abilities.
// -------------------------------------------------------------------------
add_action( 'wp_abilities_api_init', __NAMESPACE__ . '\knit_pay_register_abilities' );

/**
 * Register all Knit Pay abilities.
 */
function knit_pay_register_abilities() {
	$permission_callback = function () {
		$post_type = get_post_type_object( 'pronamic_payment' );
		if ( null === $post_type ) {
			return false;
		}
		return current_user_can( $post_type->cap->edit_posts );
	};

	// -----------------------------------------------------------------
	// Ability: create-payment
	// -----------------------------------------------------------------
	wp_register_ability(
		'knit-pay/create-payment',
		[
			'label'               => __( 'Create Payment', 'knit-pay-lang' ),
			'description'         => __( 'Creates a new payment request using Knit Pay. Returns a payment link (pay_redirect_url) that you can share with the customer so they can complete the payment. After the customer pays, use the knit-pay/get-payment ability with the returned payment id to check the payment status.', 'knit-pay-lang' ),
			'category'            => 'knit-pay',
			'input_schema'        => PaymentApiHelper::get_input_schema(),
			'output_schema'       => PaymentApiHelper::get_output_schema(),
			'execute_callback'    => __NAMESPACE__ . '\knit_pay_ability_create_payment',
			'permission_callback' => $permission_callback,
			'meta'                => [ 'show_in_rest' => true ],
		]
	);

	// -----------------------------------------------------------------
	// Ability: get-payment
	// -----------------------------------------------------------------
	wp_register_ability(
		'knit-pay/get-payment',
		[
			'label'               => __( 'Get Payment', 'knit-pay-lang' ),
			'description'         => __( 'Retrieves a Knit Pay payment by its ID and returns its current status and details. Use this after sharing the payment link with a customer to check whether they have completed, failed, or not yet attempted the payment. Common status values: open (not yet paid), success (payment completed), failure (payment failed), cancelled (customer cancelled).', 'knit-pay-lang' ),
			'category'            => 'knit-pay',

			'input_schema'        => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [
					'id' => [
						'type'        => 'integer',
						'description' => __( 'The Knit Pay payment ID returned by knit-pay/create-payment.', 'knit-pay-lang' ),
					],
				],
			],

			'output_schema'       => PaymentApiHelper::get_output_schema(),
			'execute_callback'    => __NAMESPACE__ . '\knit_pay_ability_get_payment',
			'permission_callback' => $permission_callback,
			'meta'                => [ 'show_in_rest' => true ],
		]
	);
}

// -------------------------------------------------------------------------
// Execute callbacks (thin wrappers around PaymentApiHelper).
// -------------------------------------------------------------------------

/**
 * Execute callback for knit-pay/create-payment.
 *
 * @param array $input Validated input from the Abilities API.
 * @return array|\WP_Error
 */
function knit_pay_ability_create_payment( $input ) {
	return PaymentApiHelper::create_payment( (array) $input );
}

/**
 * Execute callback for knit-pay/get-payment.
 *
 * @param array $input Validated input from the Abilities API.
 * @return array|\WP_Error
 */
function knit_pay_ability_get_payment( $input ) {
	return PaymentApiHelper::get_payment( (int) $input['id'] );
}
