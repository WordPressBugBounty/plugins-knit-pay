<?php

namespace KnitPay\Extensions\FluentForms;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use FluentForm\Framework\Helpers\ArrayHelper;
use FluentFormPro\Payments\PaymentMethods\BasePaymentMethod;
use Pronamic\WordPress\Pay\Plugin;

/**
 * Title: Fluent Forms Knit Pay Payment Method
 * Description:
 * Copyright: 2020-2026 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   8.92.0.0
 */

class KnitPayPaymentMethod extends BasePaymentMethod {

	public function init() {
		add_filter( 'fluentform/payment_method_settings_validation_' . $this->key, [ $this, 'validateSettings' ], 10, 2 );

		if ( ! $this->isEnabled() ) {
			return;
		}

		add_filter( 'fluentform/transaction_data_' . $this->key, [ $this, 'modifyTransaction' ], 10, 1 );

		add_filter(
			'fluentform/available_payment_methods',
			[ $this, 'pushPaymentMethodToForm' ]
		);

		( new GatewayProcessor() )->init();
	}

	public function pushPaymentMethodToForm( $methods ) {
		$methods[ $this->key ] = [
			'title'        => __( 'Knit Pay', 'knit-pay-lang' ),
			'enabled'      => 'yes',
			'method_value' => $this->key,
			'settings'     => [
				'option_label' => [
					'type'     => 'text',
					'template' => 'inputText',
					'value'    => 'Online Payment',
					'label'    => __( 'Method Label', 'knit-pay-lang' ),
				],
			],
		];

		return $methods;
	}

	public function validateSettings( $errors, $settings ) {
		if ( ArrayHelper::get( $settings, 'is_active' ) == 'no' ) {
			return [];
		}

		if ( ! ArrayHelper::get( $settings, 'payment_description' ) ) {
			$errors['payment_description'] = __( 'Payment Description is required', 'knit-pay-lang' );
		}

		return $errors;
	}

	public function modifyTransaction( $transaction ) {
		$transaction->action_url = get_edit_post_link( $transaction->payment_note['knitpay_payment_id'], '' );
		
		return $transaction;
	}

	public function isEnabled() {
		$settings = self::getGlobalSettings();
		return $settings['is_active'] == 'yes';
	}

	public function getGlobalFields() {

		return [
			'label'  => 'Knit Pay',
			'fields' => [
				[
					'settings_key'   => 'is_active',
					'type'           => 'yes-no-checkbox',
					'label'          => __( 'Status', 'knit-pay-lang' ),
					'checkbox_label' => __( 'Enable Knit Pay Method', 'knit-pay-lang' ),
				],
				[
					'settings_key' => 'config_id',
					'type'         => 'input-radio',
					'label'        => __( 'Configuration', 'knit-pay-lang' ),
					'options'      => Plugin::get_config_select_options( $this->key ),
					'inline_help'  => 'Configurations can be created in Knit Pay gateway configurations page at <a href="' . admin_url( 'edit.php?post_type=pronamic_gateway' ) . '">"Knit Pay >> Configurations"</a>.',
					'check_status' => 'yes',
				],
				[
					'settings_key' => 'payment_description',
					'type'         => 'input-text',
					'placeholder'  => __( 'Payment Description', 'knit-pay-lang' ),
					'label'        => __( 'Payment Description', 'knit-pay-lang' ),
					'inline_help'  => sprintf( __( 'Available tags: %s', 'knit-pay-lang' ), sprintf( '<code>%s</code> <code>%s</code>', '{submission_id}', '{customer_name}' ) ),
					'check_status' => 'yes',
				],
			],
		];
	}

	public function getGlobalSettings() {
		$defaults = [
			'is_active'           => 'no',
			'config_id'           => 0,
			'payment_description' => 'Submission {submission_id}',
		];

		return wp_parse_args( get_option( 'fluentform_payment_settings_' . $this->key, [] ), $defaults );
	}
}
