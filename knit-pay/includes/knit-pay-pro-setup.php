<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class KnitPayPro_Setup {
	private $setup_page_name;
	private $pro_plugin_name;

	public function __construct() {
		// Create Knit Pay Pro Setup Menu.
		add_action( 'admin_menu', [ $this, 'admin_menu' ], 1000 );

		// Actions.
		add_action( 'admin_init', [ $this, 'admin_init' ] );

		// Show error if dependencies are missing.
		add_action( 'admin_notices', [ $this, 'admin_notice_missing_dependencies' ] );

		$this->setup_page_name = __( 'Knit Pay Pro Setup', 'knit-pay-lang' );
		$this->pro_plugin_name = 'Knit Pay - Pro';
		if ( ! defined( 'KNIT_PAY_PRO' ) ) {
			$this->setup_page_name = __( 'Knit Pay UPI Setup', 'knit-pay-lang' );
			$this->pro_plugin_name = 'Knit Pay - UPI';
		}
	}

	/**
	 * Create the admin menu.
	 *
	 * @return void
	 */
	public function admin_menu() {
		\add_submenu_page(
			'pronamic_ideal',
			$this->setup_page_name,
			$this->setup_page_name,
			'manage_options',
			'knit_pay_pro_setup_page',
			function () {
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
			$this->setup_page_name,
			function () {
			},
			'knit_pay_pro_setup_page'
		);

		// How to setup.
		add_settings_field(
			'knit_pay_pro_setup_instruction',
			__( 'How to Setup?', 'knit-pay-lang' ),
			function () {
				require_once KNITPAY_DIR . '/views/template-knit-pay-pro-setup-instruction.php';
			},
			'knit_pay_pro_setup_page',
			'knit_pay_pro_setup_section',
		);

		// Rapid API Key.
		add_settings_field(
			'knit_pay_pro_setup_rapidapi_key',
			__( 'Rapid API Key*', 'knit-pay-lang' ),
			[ $this, 'input_field' ],
			'knit_pay_pro_setup_page',
			'knit_pay_pro_setup_section',
			[
				'label_for'   => 'knit_pay_pro_setup_rapidapi_key',
				'required'    => '',
				'class'       => 'regular-text',
				'description' => 'Before entering the keys, make sure that you subscribe to the above Rapid API.',
			]
		);
	}

	public function admin_notice_missing_dependencies() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! get_option( 'knit_pay_pro_setup_rapidapi_key' ) ) {
			$knit_pay_pro_setup_url = admin_url( 'admin.php?page=knit_pay_pro_setup_page' );
			$link                   = '<a href="' . $knit_pay_pro_setup_url . '">' . sprintf( __( '%1$s >> %2$s', 'knit-pay-lang' ), 'Knit Pay', $this->setup_page_name ) . '</a>';
			$message                = sprintf( __( '<b>%1$s</b> is not set up correctly. Please visit the %2$s page to configure "%3$s".', 'knit-pay-lang' ), $this->pro_plugin_name, $link, $this->pro_plugin_name );

			wp_admin_notice( $message, [ 'type' => 'error' ] );
		}
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
