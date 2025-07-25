<?php
/**
 * Plugin Name: Knit Pay
 * Plugin URI: https://www.knitpay.org
 * Description: Seamlessly integrates 500+ payment gateways, including Cashfree, Instamojo, Razorpay, Paypal, Stripe, UPI QR, GoUrl, and SSLCommerz, with over 100 WordPress plugins.
 *
 * Version: 8.96.25.1
 * Requires at least: 6.4
 * Requires PHP: 8.0
 *
 * Author: KnitPay
 * Author URI: https://www.knitpay.org/
 *
 * Text Domain: knit-pay-lang
 * Domain Path: /languages/
 *
 * License: GPL-3.0-or-later
 *
 * @author    KnitPay
 * @license   GPL-3.0-or-later
 * @package   KnitPay
 * @copyright 2020-2025 Knit Pay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'KNIT_PAY_DEBUG' ) ) {
	define( 'KNIT_PAY_DEBUG', false );
}
if ( ! defined( 'PRONAMIC_PAY_DEBUG' ) ) {
	define( 'PRONAMIC_PAY_DEBUG', false );
}

define( 'KNITPAY_URL', plugins_url( '', __FILE__ ) );
define( 'KNITPAY_DIR', plugin_dir_path( __FILE__ ) );
define( 'KNITPAY_PATH', __FILE__ );

/**
 * Autoload.
 */
require_once __DIR__ . '/vendor/autoload_packages.php';

require KNITPAY_DIR . 'include.php';

/**
 * Bootstrap.
 */
$plugin_obj = \Pronamic\WordPress\Pay\Plugin::instance(
	[
		'file'             => __FILE__,
		'rest_base'        => 'knit-pay',
		/*
		'options'          => [
			'about_page_file' => __DIR__ . '/admin/page-about.php',
		]*/
		'action_scheduler' => __DIR__ . '/vendor/woocommerce/action-scheduler/action-scheduler.php',
	]
);
define( 'KNITPAY_VERSION', $plugin_obj->get_version() );

add_filter(
	'pronamic_pay_modules',
	function( $modules ) {
		// $modules[] = 'forms';
		$modules[] = 'reports';

		if ( defined( 'KNIT_PAY_RAZORPAY_SUBSCRIPTION' ) ) {
			$modules[] = 'subscriptions';
		}

		return $modules;
	}
);

add_filter(
	'pronamic_pay_plugin_integrations',
	function( $integrations ) {
		// BookingPress.
		$integrations[] = new \KnitPay\Extensions\BookingPress\Extension();

		// Camptix.
		$integrations[] = new \KnitPay\Extensions\Camptix\Extension();

		// Charitable.
		$integrations[] = new \KnitPay\Extensions\Charitable\Extension();
		
		// Contact Form 7.
		$integrations[] = new \KnitPay\Extensions\ContactForm7\Extension();

		// Easy Digital Downloads.
		$integrations[] = new \Pronamic\WordPress\Pay\Extensions\EasyDigitalDownloads\Extension();

		// Give.
		$integrations[] = new \KnitPay\Extensions\Give\Extension();

		// Gravity Forms.
		if ( ! defined( 'KNIT_PAY_GRAVITY_FORMS' ) ) {
			$integrations[] = new \Pronamic\WordPress\Pay\Extensions\GravityForms\Extension();
		}

		// Knit Pay - Payment Button.
		$integrations[] = new \KnitPay\Extensions\KnitPayPaymentButton\Extension();

		// Knit Pay - Payment Link.
		$integrations[] = new \KnitPay\Extensions\KnitPayPaymentLink\Extension();

		// LearnDash.
		if ( ! defined( 'KNIT_PAY_LEARN_DASH' ) ) {
			$integrations[] = new \KnitPay\Extensions\LearnDash\Extension();
		}

		// LearnPress.
		$integrations[] = new \KnitPay\Extensions\LearnPress\Extension();

		// LifterLMS.
		$integrations[] = new \KnitPay\Extensions\LifterLMS\Extension();

		// NinjaForms.
		$integrations[] = new \Pronamic\WordPress\Pay\Extensions\NinjaForms\Extension();

		// Paid Memberships Pro.
		$integrations[] = new \KnitPay\Extensions\PaidMembershipsPro\Extension();

		// Profile Press.
		$integrations[] = new \KnitPay\Extensions\ProfilePress\Extension();

		// Tourmaster.
		$integrations[] = new \KnitPay\Extensions\TourMaster\Extension();

		// WP Travel.
		$integrations[] = new \KnitPay\Extensions\WPTravel\Extension();

		// WP Travel Engine.
		$integrations[] = new \KnitPay\Extensions\WPTravelEngine\Extension();

		// WooCommerce.
		$integrations[] = new \Pronamic\WordPress\Pay\Extensions\WooCommerce\Extension(
			[
				'db_version_option_name' => 'knit_pay_woocommerce_db_version',
			]
		);

		// Return integrations.
		return $integrations;
	}
);

add_filter(
	'pronamic_pay_gateways',
	function( $gateways ) {
		// Cashfree.
		$gateways[] = new \KnitPay\Gateways\Cashfree\Integration();

		// Instamojo.
		$gateways[] = new \KnitPay\Gateways\Instamojo\Integration();

		// Manual.
		$gateways[] = new \KnitPay\Gateways\Manual\Integration();

		// Open Money.
		if ( defined( 'KNIT_PAY_OPEN_MONEY' ) ) {
			$gateways[] = new \KnitPay\Gateways\OpenMoney\Integration();
		}

		// Easebuzz.
		if ( defined( 'KNIT_PAY_EASEBUZZ' ) ) {
			$gateways[] = new \KnitPay\Gateways\Easebuzz\Integration();
		}

		// GoURL.
		$gateways[] = new \KnitPay\Gateways\GoUrl\Integration();

		// RazorPay.
		$gateways[] = new \KnitPay\Gateways\Razorpay\Integration();

		// SSLCommerz.
		$gateways[] = new \KnitPay\Gateways\SSLCommerz\Integration();

		// Stripe Connect.
		$gateways['stripe-connect'] = new \KnitPay\Gateways\Stripe\Connect\Integration();

		// Test.
		$gateways[] = new \KnitPay\Gateways\Test\Integration();

		// UPI QR.
		$gateways[] = new \KnitPay\Gateways\UpiQR\Integration();

		// Multi Gateway.
		$gateways[] = new \KnitPay\Gateways\MultiGateway\Integration();

		// Other Gateways.
		$gateways[] = new \KnitPay\Gateways\Integration();

		// PayPal.
		$gateways[] = new \KnitPay\Gateways\Paypal\Integration();

		// Return gateways.
		return $gateways;
	}
);

// Show Error If no configuration Found
function knitpay_admin_no_config_error() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( 0 === wp_count_posts( 'pronamic_gateway' )->publish ) {
		$class              = 'notice notice-error';
		$url                = admin_url() . 'post-new.php?post_type=pronamic_gateway';
		$link               = '<a href="' . $url . '">' . __( 'Knit Pay >> Configurations', 'knit-pay-lang' ) . '</a>';
		$supported_gateways = '<br><a href="https://www.knitpay.org/indian-payment-gateways-supported-in-knit-pay/">' . __( 'Check the list of Supported Payment Gateways', 'knit-pay-lang' ) . '</a>';
		$message            = sprintf( __( '<b>Knit Pay:</b> No Payment Gateway configuration was found. %1$s and visit %2$s to add the first configuration before start using Knit Pay.', 'knit-pay-lang' ), $supported_gateways, $link );

		printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), $message );
	}
}
add_action( 'admin_notices', 'knitpay_admin_no_config_error' );


// Add custom link on plugin page
function knitpay_filter_plugin_action_links( array $actions ) {
	return array_merge(
		[
			'configurations' => '<a href="edit.php?post_type=pronamic_gateway">' . esc_html__( 'Configurations', 'knit-pay-lang' ) . '</a>',
			'payments'       => '<a href="edit.php?post_type=pronamic_payment">' . esc_html__( 'Payments', 'knit-pay-lang' ) . '</a>',
		],
		$actions
	);
}
$plugin = plugin_basename( __FILE__ );
add_filter( "network_admin_plugin_action_links_$plugin", 'knitpay_filter_plugin_action_links' );
add_filter( "plugin_action_links_$plugin", 'knitpay_filter_plugin_action_links' );


// Fix URLs with multiple question marks by converting extras to ampersands.
function knitpay_fix_get_url() {
	if ( ! ( filter_has_var( INPUT_SERVER, 'REQUEST_METHOD' ) && 'GET' === $_SERVER['REQUEST_METHOD'] ) ) {
		return;
	}

	$current_url = home_url( $_SERVER['REQUEST_URI'] );

	// Only process if there are multiple question marks
	if ( substr_count( $current_url, '?' ) > 1 ) {
		// Split URL at first question mark
		list($base, $query) = explode( '?', $current_url, 2 );

		// Replace remaining question marks with ampersands and fix encoded ampersands
		$query = str_replace( [ '?', '&amp;' ], [ '&', '&' ], $query );

		wp_safe_redirect( $base . '?' . $query );
		exit;
	}
}
add_action( 'init', 'knitpay_fix_get_url', 0 );

add_action( 'plugins_loaded', 'knit_pay_engine_themes_init', -10 );
function knit_pay_engine_themes_init() {
	if ( \defined( 'KNIT_PAY_ENGINE_THEMES' ) ) {
		require_once KNITPAY_DIR . 'extensions/enginethemes/init.php';
	}
}
