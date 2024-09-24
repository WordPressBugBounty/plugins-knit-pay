<?php

namespace KnitPay\Extensions\EngineThemes;

use Pronamic\WordPress\Pay\AbstractPluginIntegration;
use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;
use ET_Order;
use AE_Options;
use AE_section;
use AE_container;
use AE_Order;

/**
 * Title: Engine Themes extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   4.7.0
 */
class Extension extends AbstractPluginIntegration {
	/**
	 * Slug
	 *
	 * @var string
	 */
	const SLUG = 'enginethemes';

	/**
	 * Constructs and initialize Engine Themes extension.
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'name' => __( 'Engine Themes', 'knit-pay-lang' ),
			]
		);
		
		parent::__construct( $args );

		// Dependencies.
		$dependencies = $this->get_dependencies();

		$dependencies->add( new EngineThemesDependency() );
	}

	/**
	 * Setup plugin integration.
	 *
	 * @return void
	 */
	public function setup() {
		add_filter( 'pronamic_payment_source_text_' . self::SLUG, [ $this, 'source_text' ], 10, 2 );
		add_filter( 'pronamic_payment_source_description_' . self::SLUG, [ $this, 'source_description' ], 10, 2 );
		add_filter( 'pronamic_payment_source_url_' . self::SLUG, [ $this, 'source_url' ], 10, 2 );

		// Check if dependencies are met and integration is active.
		if ( ! $this->is_active() ) {
			return;
		}

		add_filter( 'pronamic_payment_redirect_url_' . self::SLUG, [ $this, 'redirect_url' ], 10, 2 );
		add_action( 'pronamic_payment_status_update_' . self::SLUG, [ $this, 'status_update' ], 10 );

		add_action( 'after_setup_theme', [ $this, 'require_plugin_files' ] );
		
		add_filter( 'et_build_payment_visitor', [ $this, 'build_payment_visitor' ], 10, 3 );
		
		add_filter( 'ae_support_gateway', [ $this, 'ae_support_gateway' ], 10, 3 );
	}

	public function require_plugin_files() {
		$child_name_space    = join( '\\', array_slice( explode( '\\', get_class( $this ) ), 0, -1 ) );
		$child_gateway_class = $child_name_space . '\\Gateway';
		new $child_gateway_class();
	}

	/**
	 * Add payment visitor for STRIPE
	 *
	 * @param object   $class
	 * @param string   $payment_type
	 * @param ET_Order $order
	 * @return object $class
	 */
	public function build_payment_visitor( $class, $payment_type, $order ) {
		if ( 'KNIT_PAY' === $payment_type ) {
			$class = new KnitPayVisitor( $order );
		}

		return $class;
	}
	
	public function ae_support_gateway( $gateways ) {
		$gateways['knit_pay'] = 'Knit Pay';
		return $gateways;
	}
	
	public static function ae_admin_menu_pages( $pages ) {
		
		$sections = [];
		$options  = AE_Options::get_instance();      
		
		
		/**
		 * ae fields settings
		 */
		$sections = [
			'args'   => [
				'title' => __( 'Knit Pay', 'knit-pay-lang' ),
				'id'    => 'knit-pay',
				'icon'  => 'F',
				'class' => '',
			],
			
			'groups' => [
				[
					'args'   => [
						'title' => __( 'Knit Pay Settings', 'knit-pay-lang' ),
						'id'    => 'knit_pay',
						'class' => '',
						'desc'  => '',
						'name'  => 'knit_pay',
					],
					'fields' => [
						[
							'id'    => 'enable',
							'class' => '',
							'type'  => 'switch',
							'label' => __( 'Enable Knit Pay', 'knit-pay-lang' ),
							'desc'  => '',
							'name'  => 'enable',
						],
						[
							'id'    => 'title',
							'class' => '',
							'type'  => 'text',
							'label' => __( 'Title', 'knit-pay-lang' ),
							'desc'  => __( 'It will be displayed on checkout page.', 'knit-pay-lang' ),
							'name'  => 'title',
						],
						[
							'id'    => 'description',
							'class' => '',
							'type'  => 'text',
							'label' => __( 'Gateway Description', 'knit-pay-lang' ),
							'desc'  => __( 'It will be displayed on checkout page.', 'knit-pay-lang' ),
							'name'  => 'description',
						],
						[
							'id'    => 'config_id',
							'class' => '',
							'type'  => 'select',
							'label' => __( 'Configuration', 'knit-pay-lang' ),
							'desc'  => __( 'Configurations can be created in Knit Pay gateway configurations page at <a href="' . admin_url() . 'edit.php?post_type=pronamic_gateway">"Knit Pay >> Configurations"</a>.', 'knit-pay-lang' ),
							'name'  => 'config_id',
							'data'  => Plugin::get_config_select_options(),
						],
						[
							'id'    => 'payment_description',
							'class' => '',
							'type'  => 'text',
							'label' => __( 'Payment Description', 'knit-pay-lang' ),
							'desc'  => sprintf( __( 'Available tags: %s', 'knit-pay-lang' ), sprintf( '<code>%s</code>', '{order_id}' ) ),
							'name'  => 'payment_description',
						],
						[
							'id'    => 'icon_url',
							'class' => '',
							'type'  => 'text',
							'label' => __( 'Icon URL', 'knit-pay-lang' ),
							'desc'  => __( 'This controls the icon which the user sees during checkout. Keep it blank to use default Icon.', 'knit-pay-lang' ) . '<br>' . __( 'Recommended Size 78x75', 'knit-pay-lang' ),
							'name'  => 'icon_url',
						],
					],
				],
			],
		];
		
		$temp = new AE_section( $sections['args'], $sections['groups'], $options );
		
		$knit_pay_setting = new AE_container(
			[
				'class' => 'field-settings',
				'id'    => 'settings',
			],
			$temp,
			$options
		);
		
		$pages[] = [
			'args'      => [
				'parent_slug' => 'et-overview',
				'page_title'  => __( 'Knit Pay', 'knit-pay-lang' ),
				'menu_title'  => __( 'KNIT PAY', 'knit-pay-lang' ),
				'cap'         => 'administrator',
				'slug'        => 'ae-knit-pay',
				'icon'        => '%',
				// 'icon_class' => 'fa fa-inr',
				'desc'        => '',
			],
			'container' => $knit_pay_setting,
		];
		
		return $pages;
	}

	public static function add_gateway_setting_fields( $sections ) {

		$sections['knit-pay'] = [
			'args'   => [
				'title' => __( 'Knit Pay', 'knit-pay-lang' ),
				'id'    => 'knit-pay',
				'class' => '',
				'icon'  => '',
			],
			'groups' => [
				[
					'args'   => [
						'title' => __( 'Knit Pay', 'knit-pay-lang' ),
						'id'    => '',
						'class' => '',
						'desc'  => '',
					],
					'fields' => [
						[
							'id'       => 'knit_pay',
							'class'    => '',
							'type'     => 'combine',
							'title'    => __( 'Knit Pay Settings', 'knit-pay-lang' ),
							'desc'     => '',
							'name'     => 'knit_pay',
							'children' => [
								[
									'id'    => 'enable',
									'class' => '',
									'type'  => 'switch',
									'title' => __( 'Enable Knit Pay', 'knit-pay-lang' ),
									'desc'  => '',
									'name'  => 'enable',
								],
								[
									'id'    => 'title',
									'class' => '',
									'type'  => 'text',
									'title' => __( 'Title', 'knit-pay-lang' ),
									'desc'  => __( 'It will be displayed on checkout page.', 'knit-pay-lang' ),
									'name'  => 'title',
								],
								[
									'id'    => 'config_id',
									'class' => '',
									'type'  => 'select',
									'title' => __( 'Configuration', 'knit-pay-lang' ),
									'desc'  => __( 'Configurations can be created in Knit Pay gateway configurations page at <a href="' . admin_url() . 'edit.php?post_type=pronamic_gateway">"Knit Pay >> Configurations"</a>.', 'knit-pay-lang' ),
									'name'  => 'config_id',
									'data'  => Plugin::get_config_select_options(),
								],
								[
									'id'    => 'payment_description',
									'class' => '',
									'type'  => 'text',
									'title' => __( 'Payment Description', 'knit-pay-lang' ),
									'desc'  => sprintf( __( 'Available tags: %s', 'knit-pay-lang' ), sprintf( '<code>%s</code>', '{order_id}' ) ),
									'name'  => 'payment_description',
								],
								[
									'id'    => 'icon_url',
									'class' => '',
									'type'  => 'text',
									'title' => __( 'Icon URL', 'knit-pay-lang' ),
									'desc'  => __( 'This controls the icon which the user sees during checkout. Keep it blank to use default Icon.', 'knit-pay-lang' ) . '<br>' . __( 'Recommended Size 78x75', 'knit-pay-lang' ),
									'name'  => 'icon_url',
								],
							],
						],
					],
				],
			],
		];

		return $sections;
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
		$source_id = (int) $payment->get_source_id();
		switch ( $payment->get_status() ) {
			case Core_Statuses::CANCELLED:
			case Core_Statuses::EXPIRED:
			case Core_Statuses::FAILURE:
				$url = et_get_page_link( 'cancel-payment' );

				break;

			case Core_Statuses::SUCCESS:
				$url = et_get_page_link( 'process-payment', [ 'order-id' => $source_id ] );
				break;

			case Core_Statuses::AUTHORIZED:
			case Core_Statuses::OPEN:
			default:
		}

		return $url;
	}

	/**
	 * Update the status of the specified payment
	 *
	 * @param Payment $payment Payment.
	 */
	public static function status_update( Payment $payment ) {
		$source_id  = (int) $payment->get_source_id();
		$order      = new AE_Order( $source_id );
		$order_data = $order->get_order_data();

		$order->set_payment_code( $payment->get_transaction_id() );

		switch ( $payment->get_status() ) {
			case Core_Statuses::CANCELLED:
			case Core_Statuses::EXPIRED:
			case Core_Statuses::FAILURE:
				$order->set_status( 'draft' );
				$post_status = 'draft';

				break;
			case Core_Statuses::SUCCESS:
				$order->set_status( 'publish' );
				$post_status = 'publish';

				break;
			case Core_Statuses::OPEN:
			default:
				$order->set_status( 'pending' );
				$post_status = 'pending';

				break;
		}
		$order->update_order();
		wp_update_post(
			[
				'ID'          => $order_data['product_id'],
				'post_status' => $post_status,
				'post_title'  => reset( $order_data['products'] )['NAME'],

			]
		);
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
		$mjob_order_id = $this->get_et_order_id( $payment );
		$text          = __( 'Engine Themes', 'knit-pay-lang' ) . '<br />';

		$text .= sprintf(
			'<a href="%s">%s</a>',
			get_edit_post_link( $mjob_order_id ),
			/* translators: %s: source id */
			sprintf( __( 'Order %s', 'knit-pay-lang' ), $mjob_order_id )
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
		return __( 'Engine Themes Order', 'knit-pay-lang' );
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
		return get_edit_post_link( $this->get_et_order_id( $payment ) );
	}

	private function get_et_order_id( Payment $payment ) {
		$source_id = (int) $payment->get_source_id();

		if ( ! class_exists( 'AE_Order' ) ) {
			return $source_id;
		}

		$order      = new AE_Order( $source_id );
		$order_data = $order->get_order_data();
		if ( ! is_null( get_post( $order_data['product_id'] ) ) ) {
			return $order_data['product_id'];
		}
		return $source_id;
	}

}
