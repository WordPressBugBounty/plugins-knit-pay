<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Show error if dependencies are missing.
add_action( 'admin_notices', 'knit_pay_pro_admin_notice_missing_dependencies' );
function knit_pay_pro_admin_notice_missing_dependencies() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( ! get_option( 'knit_pay_pro_setup_rapidapi_key' ) ) {
		$knit_pay_pro_setup_url = admin_url( 'admin.php?page=knit_pay_pro_setup_page' );
		$link                   = '<a href="' . $knit_pay_pro_setup_url . '">' . __( 'Knit Pay >> Knit Pay Pro Setup', 'knit-pay-pro' ) . '</a>';
		$message                = sprintf( __( '<b>Knit Pay Pro</b> is not set up correctly. Please visit the %s page to configure "Knit Pay - Pro".', 'knit-pay-pro' ), $link );

		wp_admin_notice( $message, [ 'type' => 'error' ] );
	}
}

class KnitPayPro_Setup {
	public function __construct() {
		// Create Knit Pay Pro Setup Menu.
		add_action( 'admin_menu', [ $this, 'admin_menu' ], 1000 );

		// Actions.
		add_action( 'admin_init', [ $this, 'admin_init' ] );
	}

	/**
	 * Create the admin menu.
	 *
	 * @return void
	 */
	public function admin_menu() {
		\add_submenu_page(
			'pronamic_ideal',
			__( 'Knit Pay Pro Setup', 'knit-pay-pro' ),
			__( 'Knit Pay Pro Setup', 'knit-pay-pro' ),
			'manage_options',
			'knit_pay_pro_setup_page',
			function() {
				include KNITPAY_DIR . '/views/page-knit-pay-pro-setup.php';
			}
		);
	}

	/**
	 * Admin initialize.
	 *
	 * @return void
	 */
	public function admin_init() {
		register_setting(
			'knit_pay_pro_setup_page',
			'knit_pay_pro_setup_rapidapi_key',
			[
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			]
		);

		// Settings - General.
		add_settings_section(
			'knit_pay_pro_setup_section',
			__( 'Knit Pay Pro Setup', 'knit-pay-pro' ),
			function () {
			},
			'knit_pay_pro_setup_page'
		);

		// How to setup Knit Pay Pro.
		add_settings_field(
			'knit_pay_pro_setup_instruction',
			__( 'How to setup Knit Pay Pro?', 'knit-pay-pro' ),
			function () {
				require_once KNITPAY_DIR . '/views/template-knit-pay-pro-setup-instruction.php';
			},
			'knit_pay_pro_setup_page',
			'knit_pay_pro_setup_section',
		);

		// Rapid API Key.
		add_settings_field(
			'knit_pay_pro_setup_rapidapi_key',
			__( 'Rapid API Key*', 'knit-pay-pro' ),
			[ $this, 'input_field' ],
			'knit_pay_pro_setup_page',
			'knit_pay_pro_setup_section',
			[
				'label_for'   => 'knit_pay_pro_setup_rapidapi_key',
				'required'    => '',
				'class'       => 'regular-text',
				'description' => '<a target="_blank" href="https://rapidapi.com/knitpay/api/knit-pay-pro1/pricing">Before entering the keys, make sure to subscribe to this Rapid API</a>',
			]
		);
	}

	/**
	 * Input Field.
	 *
	 * @param array $args Arguments.
	 * @return void
	 */
	public function input_field( $args ) {

		$args['id']   = $args['label_for'];
		$args['name'] = $args['label_for'];

		$args['value'] = get_option( $args['name'], '' );

		$element = new \Pronamic\WordPress\Html\Element( 'input', $args );
		$element->output();

		self::print_description( $args );
	}

	public static function print_description( $args ) {
		if ( isset( $args['description'] ) ) {
			printf(
				'<p class="pronamic-pay-description description">%s</p>',
				\wp_kses(
					$args['description'],
					[
						'a'    => [
							'href'   => true,
							'target' => true,
						],
						'br'   => [],
						'code' => [],
					]
				)
			);
		}
	}
}

new KnitPayPro_Setup();
