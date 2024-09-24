<?php
/**
 * Knit Pay Admin Pages
 *
 * This file contains function to handle Knit Pay config logic in wp-admin
 * and config form.
 *
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   1.8.0
 */

use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Core\PaymentMethods;
$module = filter_input( INPUT_GET, 'module', FILTER_SANITIZE_STRING );

/**
 * Renders Knit Pay config form.
 *
 * The page is rendered in wp-admin / Classifieds / Options / Knit Pay panel
 *
 * @return void
 */
function adext_knit_pay_page_options() {
	$module = filter_input( INPUT_GET, 'module', FILTER_SANITIZE_STRING );

	wp_enqueue_style( 'adverts-admin' );
	$flash = Adverts_Flash::instance();
	$error = [];

	$scheme = Adverts::instance()->get( 'form_' . $module . '_config' );
	$form   = new Adverts_Form( $scheme );
	$form->bind( get_option( 'adext_' . $module . '_config', [] ) );

	$button_text         = __( 'Update Options', 'knit-pay-lang' );
	$payment_method_name = PaymentMethods::get_name( $module );

	if ( isset( $_POST ) && ! empty( $_POST ) ) {
		$form->bind( $_POST );
		$valid = $form->validate();

		if ( $valid ) {

			update_option( 'adext_' . $module . '_config', $form->get_values() );
			$flash->add_info( __( 'Settings updated.', 'adverts' ) );
		} else {
			$flash->add_error( __( 'There are errors in your form.', 'adverts' ) );
		}
	}

	include dirname( __DIR__ ) . '/admin/options.php';
}

foreach ( Plugin::get_config_select_options( $module ) as $value => $label ) {
	$configurations[] = [
		'text'  => $label,
		'value' => $value,
	];
}

// Knit Pay config form
Adverts::instance()->set(
	'form_' . $module . '_config',
	[
		'name'   => '',
		'action' => '',
		'field'  => [
			[
				'name'      => 'custom_title',
				'type'      => 'adverts_field_text',
				'label'     => __( 'Payment Name', 'knit-pay-lang' ),
				'value'     => '',
				'order'     => 10,
				'validator' => [
					[ 'name' => 'is_required' ],
				],
			],
			[
				'name'    => 'config_id',
				'type'    => 'adverts_field_select',
				'label'   => __( 'Configuration', 'knit-pay-lang' ),
				'order'   => 15,
				'options' => $configurations,
				'hint'    => __( 'Configurations can be created in Knit Pay gateway configurations page at <a href="' . admin_url() . 'edit.php?post_type=pronamic_gateway">"Knit Pay >> Configurations"</a>.', 'knit-pay-lang' ),
			],
			[
				'name'        => 'payment_description',
				'type'        => 'adverts_field_text',
				'label'       => __( 'Payment Description', 'knit-pay-lang' ),
				'order'       => 20,
				'placeholder' => '',
				'validator'   => [
					[ 'name' => 'is_required' ],
				],
				'hint'        => sprintf( __( 'Available tags: %s', 'knit-pay-lang' ), sprintf( '<code>%s</code>', '{payment_id}, {listing_type}' ) ),
			],
		],
	]
);



