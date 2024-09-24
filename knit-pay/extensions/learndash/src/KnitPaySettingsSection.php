<?php

namespace KnitPay\Extensions\LearnDash;

use Pronamic\WordPress\Pay\Plugin;
use LearnDash_Settings_Section;

/**
 * LearnDash Settings Section for Knit Pay Metabox.
 *
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   2.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ( class_exists( 'LearnDash_Settings_Section' ) ) && ( ! class_exists( 'LearnDash_Settings_Section_KnitPay' ) ) ) {
	/**
	 * Class to create the settings section.
	 */
	class KnitPaySettingsSection extends LearnDash_Settings_Section {

		/**
		 * Protected constructor for class
		 */
		protected function __construct() {
			$this->id             = 'knit_pay';
			$this->payment_method = $this->id;

			$this->settings_page_id = 'learndash_lms_payments';

			// This is the 'option_name' key used in the wp_options table.
			$this->setting_option_key = 'learndash_settings_' . $this->id;

			// This is the HTML form field prefix used.
			$this->setting_field_prefix = 'learndash_settings_' . $this->id;

			// Used within the Settings API to uniquely identify this section.
			$this->settings_section_key = 'settings_' . $this->id;

			// Section label/header.
			$this->settings_section_label = esc_html__( 'Knit Pay Settings', 'knit-pay-lang' );

			// Used to associate this section with the parent section.
			$this->settings_parent_section_key = 'settings_payments_list';

			$this->settings_section_listing_label = esc_html__( 'Knit Pay', 'knit-pay-lang' );

			parent::__construct();
		}

		/**
		 * Initialize the metabox settings fields.
		 */
		public function load_settings_fields() {

			$this->setting_option_fields = [
				'enabled'             => [
					'name'      => 'enabled',
					'type'      => 'checkbox-switch',
					'label'     => esc_html__( 'Enable Knit Pay', 'knit-pay-lang' ),
					'help_text' => esc_html__( 'Check to enable the Knit Pay Payments.', 'knit-pay-lang' ),
					'value'     => isset( $this->setting_option_values['enabled'] ) ? $this->setting_option_values['enabled'] : 'no',
					'options'   => [
						'yes' => '',
						''    => '',
					],
				],
				'login_required'      => [
					'name'      => 'login_required',
					'type'      => 'checkbox',
					'label'     => esc_html__( 'User Login Required to Make Payment', 'knit-pay-lang' ),
					'help_text' => esc_html__( 'In some payment gateways, it is mandatory to have a user account to initiate payment. If any payment gateway doesn\'t work while keeping this option OFF, try making it ON.', 'knit-pay-lang' ),
					'value'     => isset( $this->setting_option_values['login_required'] ) ? $this->setting_option_values['login_required'] : 'no',
					'options'   => [
						'yes' => esc_html__( 'Make it mandatory for users to login before they can make payment.', 'knit-pay-lang' ),
					],
				],
				'title'               => [
					'name'      => 'title',
					'type'      => 'text',
					'label'     => esc_html__( 'Title', 'knit-pay-lang' ),
					'help_text' => esc_html__( 'This controls the title which the user sees during checkout.', 'knit-pay-lang' ),
					'value'     => ( ( isset( $this->setting_option_values['title'] ) ) && ( ! empty( $this->setting_option_values['title'] ) ) ) ? $this->setting_option_values['title'] : 'Pay Online',
					'class'     => 'regular-text',
				],
				'config_id'           => [
					'name'      => 'config_id',
					'type'      => 'select',
					'label'     => esc_html__( 'Configuration', 'knit-pay-lang' ),
					'help_text' => __( 'Configurations can be created in Knit Pay gateway configurations page at <a href="' . admin_url() . 'edit.php?post_type=pronamic_gateway">"Knit Pay >> Configurations"</a>.', 'knit-pay-lang' ),
					'value'     => ( ( isset( $this->setting_option_values['config_id'] ) ) && ( ! empty( $this->setting_option_values['config_id'] ) ) ) ? $this->setting_option_values['config_id'] : get_option( 'pronamic_pay_config_id' ),
					'options'   => Plugin::get_config_select_options( $this->payment_method ),
				],
				'payment_description' => [
					'name'      => 'payment_description',
					'type'      => 'text',
					'label'     => __( 'Payment Description', 'knit-pay-lang' ),
					'help_text' => sprintf( __( 'Available tags: %s', 'knit-pay-lang' ), sprintf( '<code>%s</code>', '{course_id}, {course_name}' ) ),
					'value'     => ( ( isset( $this->setting_option_values['payment_description'] ) ) && ( ! empty( $this->setting_option_values['payment_description'] ) ) ) ? $this->setting_option_values['payment_description'] : '{course_name}',
					'class'     => 'regular-text',
				],
			];

			/** This filter is documented in includes/settings/settings-metaboxes/class-ld-settings-metabox-course-access-settings.php */
			$this->setting_option_fields = apply_filters( 'learndash_settings_fields', $this->setting_option_fields, $this->settings_section_key );

			parent::load_settings_fields();
		}

		/**
		 * Filter the section saved values.
		 *
		 * @param array  $value An array of setting fields values.
		 * @param array  $old_value An array of setting fields old values.
		 * @param string $settings_section_key Settings section key.
		 * @param string $settings_screen_id Settings screen ID.
		 *
		 * @return array
		 */
		public function filter_section_save_fields( $value, $old_value, $settings_section_key, $settings_screen_id ): array {
			if ( $settings_section_key !== $this->settings_section_key ) {
				return $value;
			}
			if ( ! isset( $value['enabled'] ) ) {
				$value['enabled'] = '';
			}

			if ( isset( $_POST['learndash_settings_payments_list_nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				if ( ! is_array( $old_value ) ) {
					$old_value = [];
				}

				foreach ( $value as $value_idx => $value_val ) {
					$old_value[ $value_idx ] = $value_val;
				}

				$value = $old_value;
			}


			return $value;
		}
	}
}
