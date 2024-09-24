<?php
namespace BooklyKnitPay\Lib\ProxyProviders;

use Bookly\Lib as BooklyLib;
use KnitPay\Extensions\BooklyPro\Extension;

/**
 * Class Shared
 *
 * @package BooklyKnitPay\Lib\ProxyProviders
 */
class Shared extends BooklyLib\Proxy\Shared {
	/**
	 * @inheritDoc
	 */
	public static function doDailyRoutine() {
		// TODO Check if it is necessary to run this query daily.

		// Added support for Knit Pay Payment methods if not supported.
		/** @global \wpdb $wpdb */
		global $wpdb;
		
		$active_payment_methods = Extension::get_active_payment_methods();
		
		$payment_method_string = implode( '", "', $active_payment_methods );
		$payment_method_string = ', "' . $payment_method_string . '"';

		$table_name = $wpdb->prefix . 'bookly_payments';
		$query      = 'ALTER TABLE `%s` CHANGE `type` `type` ENUM("local", "free", "paypal", "authorize_net", "stripe", "2checkout", "payu_biz", "payu_latam", "payson", "mollie", "woocommerce", "cloud_stripe", "cloud_square" %s) NOT NULL DEFAULT "local"';
		
		$wpdb->query( sprintf( $query, $table_name, $payment_method_string ) );
	}
}
