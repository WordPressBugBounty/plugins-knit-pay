<?php

// TODO add review notice similar to wpforms

function knit_pay_dependency_autoload( $class ) {
	if ( preg_match( '/^KnitPay\\\\(.+)?([^\\\\]+)$/U', ltrim( $class, '\\' ), $match ) ) {
		$extension_dir = KNITPAY_DIR . strtolower( str_replace( '\\', DIRECTORY_SEPARATOR, preg_replace( '/([a-z])([A-Z])/', '$1-$2', $match[1] ) ) );
		if ( ! is_dir( $extension_dir ) ) {
			$extension_dir = KNITPAY_DIR . strtolower( str_replace( '\\', DIRECTORY_SEPARATOR, preg_replace( '/([a-z])([A-Z])/', '$1$2', $match[1] ) ) );
		}

		$file = $extension_dir
		. 'src' . DIRECTORY_SEPARATOR
		. $match[2]
		. '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
spl_autoload_register( 'knit_pay_dependency_autoload' );

// Load dependency for get_plugins;
require_once ABSPATH . 'wp-admin/includes/plugin.php';

// Gateway.
require_once KNITPAY_DIR . 'gateways/IntegrationModeTrait.php';
require_once KNITPAY_DIR . 'gateways/Gateway.php';
require_once KNITPAY_DIR . 'gateways/Integration.php';
require_once KNITPAY_DIR . 'gateways/IntegrationOAuthClient.php';
require_once KNITPAY_DIR . 'gateways/PaymentMethods.php';

// Add Knit Pay Deactivate Confirmation Box on Plugin Page
require_once 'includes/plugin-deactivate-confirmation.php';

// Add Supported Extension and Gateways Sub-menu in Knit Pay Menu
require_once 'includes/supported-extension-gateway-submenu.php';

// Load Util class.
require_once 'includes/Utils.php';

// Add custom Knit Pay Custom Payment Methods.
require_once 'includes/custom-payment-methods.php';

// Add custom Knit Pay Custom Settings.
require_once 'includes/KnitPay_CustomSettings.php';

// Currency Converter.
require_once 'includes/CurrencyConverter.php';

// Including Knit Pay OmniPay PayPal for better compatibility.
require_once 'vendor/knit-pay/omnipay-paypal/src/RestGateway.php';
require_once 'vendor/knit-pay/omnipay-paypal/src/Message/AbstractRestRequest.php';

require_once 'includes/PaymentRestController.php';

require_once 'includes/hooks_mapping.php';

require_once 'includes/temp_code.php';

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
