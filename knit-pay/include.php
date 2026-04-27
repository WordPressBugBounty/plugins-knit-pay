<?php

// TODO add review notice similar to wpforms

// Load dependency for get_plugins;
require_once ABSPATH . 'wp-admin/includes/plugin.php';

// Add Knit Pay Deactivate Confirmation Box on Plugin Page
require_once 'includes/plugin-deactivate-confirmation.php';

// Add Supported Extension and Gateways Sub-menu in Knit Pay Menu
require_once 'includes/supported-extension-gateway-submenu.php';

// Add custom Knit Pay Custom Payment Methods.
require_once 'includes/custom-payment-methods.php';

// Add custom Knit Pay Custom Settings on the Settings page of Knit Pay.
require_once 'includes/CustomSettings.php';

// Currency Converter.
require_once 'includes/CurrencyConverter.php';

// Including Knit Pay OmniPay PayPal for better compatibility.
require_once 'secondary-packages/vendor/knit-pay/omnipay-paypal/src/RestGateway.php';
require_once 'secondary-packages/vendor/knit-pay/omnipay-paypal/src/Message/AbstractRestRequest.php';

require_once 'includes/PaymentRestController.php';

// WordPress Abilities API integration (WordPress 6.9+).
require_once 'includes/PaymentAbilities.php';

require_once 'includes/customizations.php';

require_once 'includes/hooks_mapping.php';

/*
 * FIXME: This is workaround for fixing
 * Translation loading for the knit-pay-lang domain was triggered too early.
 * see: https://make.wordpress.org/core/2024/10/21/i18n-improvements-6-7/
 */
add_filter(
	'lang_dir_for_domain',
	function ( $dir, $domain ) {
		if ( 'knit-pay-lang' === $domain ) {
			return '';
		}
		return $dir;
	},
	10,
	2
);

add_action( 'plugins_loaded', 'knit_pay_pro_init', -9 );
function knit_pay_pro_init() {
	if ( ! defined( 'KNIT_PAY_PRO' ) && ! defined( 'KNIT_PAY_UPI' ) ) {
		return;
	}

	if ( ! class_exists( 'KnitPayPro_Setup' ) ) {
		require_once 'includes/knit-pay-pro-setup.php';
	}

	require_once 'includes/pro.php';
}

add_action(
	'in_plugin_update_message-knit-pay/knit-pay.php',
	function ( $plugin_data ) {
		$new_version = implode( '.', array_slice( explode( '.', $plugin_data['new_version'] ), 0, 3 ) );
		if ( version_compare( $new_version, KNITPAY_VERSION, '<=' ) ) {
			return;
		}

		?>
		<hr/>
		<h3>
			<?php echo esc_html__( 'Heads up! Please backup before upgrading!', 'knit-pay-lang' ); ?>
		</h3>
		<div>
			<?php echo esc_html__( 'The latest update includes some substantial changes across different areas of the plugin. We highly recommend you backup your site before upgrading, and make sure you first update in a staging environment', 'knit-pay-lang' ); ?>
		</div>
		<?php
	}
);

// Show notice to write review.
// require_once 'includes/review-request-notice.php';

// Global Defines
define( 'KNITPAY_GLOBAL_GATEWAY_LIST_URL', 'https://wordpress.org/plugins/knit-pay/#:~:text=Supported%20payment%20providers' );

if ( ! function_exists( 'ppp' ) ) {
	function ppp( $a = '' ) {
		echo '<pre>';
		print_r( $a ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		echo '</pre><br><br>';
		do_action( 'qm/info', $a ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
	}
}

if ( ! function_exists( 'ddd' ) ) {
	function ddd( $a = '' ) {
		echo nl2br( '<pre>' . PHP_EOL . PHP_EOL . PHP_EOL . PHP_EOL . PHP_EOL . PHP_EOL );
		debug_print_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_print_backtrace
		echo '</pre>';
		wp_die();
	}
}

if ( ! function_exists( 'ttt' ) ) {
	function ttt( $operation = '', $name = 'kp_speed' ) {
		switch ( $operation ) {
			case 's': // start
				$operation = 'qm/start';
				break;
			case 'e': // end
				$operation = 'qm/stop';
				break;
			case 'l':
			default:
				$operation = 'qm/lap';
				break;
		}

		do_action( $operation, $name );
	}
}
