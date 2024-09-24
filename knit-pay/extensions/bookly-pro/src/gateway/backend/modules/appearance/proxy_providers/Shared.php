<?php
namespace BooklyKnitPay\Backend\Modules\Appearance\ProxyProviders;

use Bookly\Backend\Modules\Appearance\Proxy;
use BooklyKnitPay\Lib\Plugin;
use Pronamic\WordPress\Pay\Core\PaymentMethods;
use KnitPay\Extensions\BooklyPro\Extension;

/**
 * Class Shared
 *
 * @package BooklyKnitPay\Backend\Modules\Appearance\ProxyProviders
 */
class Shared extends Proxy\Shared {

	/** @inheritDoc */
	public static function paymentGateways( $data ) {
		$active_payment_methods = Extension::get_active_payment_methods();
		foreach ( $active_payment_methods as $key => $payment_method ) {
			$data[ $payment_method ] = [
				'label_option_name' => 'bookly_l10n_label_pay_' . $payment_method,
				'title'             => PaymentMethods::get_name( $key ),
				'with_card'         => true,
				'logo_url'          => 'default',
			];
		}

		return $data;
	}

	/**
	 * @inheritDoc
	 */
	public static function prepareOptions( array $options_to_save, array $options ) {
		$options_to_save = array_merge(
			$options_to_save,
			array_intersect_key(
				$options,
				array_flip(
					[
						'bookly_l10n_label_pay_knit_pay',
					]
				)
			)
		);

		return $options_to_save;
	}

}
