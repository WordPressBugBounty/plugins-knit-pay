<?php
namespace BooklyKnitPay\Frontend\Modules\Booking\ProxyProviders;

use Bookly\Lib as BooklyLib;
use Bookly\Frontend\Modules\Booking\Proxy;
use BooklyKnitPay\Lib;
use Pronamic\WordPress\Pay\Core\PaymentMethods;
use KnitPay\Extensions\BooklyPro\Extension;

/**
 * Class Shared
 *
 * @package BooklyKnitPay\Frontend\Modules\Booking\ProxyProviders
 */
class Shared extends Proxy\Shared {

	/**
	 * @inheritdoc
	 */
	public static function preparePaymentOptions( $options, $form_id, $show_price, BooklyLib\CartInfo $cart_info, $payment_status ) {
		$active_payment_methods = Extension::get_active_payment_methods();
		foreach ( $active_payment_methods as $payment_method ) {
			$icon_url = get_option( 'bookly_' . $payment_method . '_icon_url', null );

			if ( ! get_option( 'bookly_' . $payment_method . '_enabled' ) ) {
				continue;
			}

			$cart_info->setGateway( $payment_method );

			$options[ $payment_method ] = [
				'html' => self::renderTemplate(
					'payment_option',
					compact( 'form_id', 'show_price', 'cart_info', 'payment_status', 'payment_method', 'icon_url' ),
					false
				),
				'pay'  => $cart_info->getPayNow(),
			];
		}

		return $options;
	}

	/**
	 * @inheritdoc
	 */
	public static function renderPaymentForms( $form_id, $page_url ) {
		self::renderTemplate( 'payment_form', compact( 'form_id', 'page_url' ) );
	}
}
