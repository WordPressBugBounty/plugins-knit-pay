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

require_once 'includes/PaymentRestController.php';

require_once 'includes/hooks_mapping.php';

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

// Show Google Price Hike Notice.
// require_once 'includes/google-workspace-price-hike-notice.php';

// Show notice to write review.
// require_once 'includes/review-request-notice.php';

// Global Defines
define( 'KNITPAY_GLOBAL_GATEWAY_LIST_URL', 'https://wordpress.org/plugins/knit-pay/#tab-description' );

if ( ! function_exists( 'ppp' ) ) {
	function ppp( $a = '' ) {
		echo '<pre>';
		print_r( $a );
		echo '</pre><br><br>';
	}
}

if ( ! function_exists( 'ddd' ) ) {
	function ddd( $a = '' ) {
		echo nl2br( '<pre>' . $a . PHP_EOL . PHP_EOL . PHP_EOL . PHP_EOL . PHP_EOL . PHP_EOL );
		debug_print_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );
		echo '</pre>';
		die();
	}
}

if ( ! function_exists( 'knitpay_getDomain' ) ) {
	function knitpay_getDomain( $host ) {
		$domain = isset( $host ) ? $host : '';
		if ( preg_match( '/(?P<domain>[a-z0-9][a-z0-9-]{1,63}.[a-z.]{2,6})$/i', $domain, $regs ) ) {
			return $regs['domain'];
		}
		return false;
	}
}
