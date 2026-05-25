<?php

namespace KnitPay\Extensions\EasyDigitalDownloads;

use Pronamic\WordPress\Pay\Extensions\EasyDigitalDownloads\Extension as Pronamic_Edd_Extension;
use Pronamic\WordPress\Pay\Core\PaymentMethods;
use Pronamic\WordPress\Pay\Plugin;

/**
 * Title: Easy Digital Downloads extension
 * Description:
 * Copyright: 2020-2026 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   9.4.0.0
 */
class Extension extends Pronamic_Edd_Extension {
	/**
	 * Gateway ID aliases for backward compatibility.
	 *
	 * @var array<string, string>
	 */
	private static $aliases = [
		'pronamic_pay_mister_cash' => PaymentMethods::BANCONTACT,
	];

	/**
	 * Construct and initialize Easy Digital Downloads extension.
	 */
	public function plugins_loaded() {
		parent::plugins_loaded();

		// Add support for SVG in EDD.
		add_filter(
			'edd_string_is_image',
			function ( $is_image, $filename ) {
				if ( 'svg' === 	edd_get_file_extension( $filename ) ) {
					return true;
				}
				return $is_image;
			},
			10,
			2
		);
	}

	/**
	 * Get payment methods.
	 *
	 * Overrides the parent’s hard-coded list so every active Knit Pay
	 * payment method gets its own EDD gateway.
	 *
	 * @return array<string, string>
	 */
	protected static function get_payment_methods() {
		$payment_methods = [];

		foreach ( PaymentMethods::get_active_payment_methods() as $payment_method ) {
			$gateway_id = self::get_gateway_id( $payment_method );

			$payment_methods[ $gateway_id ] = $payment_method;
		}

		\uasort(
			$payment_methods,
			function ( $a, $b ) {
				return \strnatcasecmp(
					(string) PaymentMethods::get_name( $a ),
					(string) PaymentMethods::get_name( $b )
				);
			}
		);

		return $payment_methods;
	}

	/**
	 * Get gateway ID for a payment method.
	 *
	 * @param string $payment_method Payment method.
	 * @return string
	 */
	private static function get_gateway_id( $payment_method ) {
		$map = array_flip( self::$aliases );

		if ( \array_key_exists( $payment_method, $map ) ) {
			return $map[ $payment_method ];
		}

		return 'pronamic_pay_' . $payment_method;
	}

	/**
	 * Accepted payment icons.
	 *
	 * Overrides the parent to support SVG icons and a generic Knit Pay
	 * fallback instead of the parent’s PNG-only logic.
	 *
	 * @param array<string, string> $icons Icons.
	 * @return array<string, string>
	 */
	public static function accepted_payment_icons( $icons ) {
		$payment_methods = self::get_payment_methods();

		// Generic Knit Pay gateway icon fallback.
		$generic_icon_url = self::get_icon_url( 'knit-pay' );
		if ( null !== $generic_icon_url ) {
			$icons[ $generic_icon_url ] = __( 'Knit Pay', 'pronamic_ideal' );
		}

		foreach ( $payment_methods as $gateway_id => $payment_method ) {
			$icon_url = self::get_icon_url( $payment_method );

			if ( null !== $icon_url ) {
				$icon_name = PaymentMethods::get_name( $payment_method );

				$icons[ $icon_url ] = $icon_name;
			}
		}

		return $icons;
	}

	/**
	 * Get icon URL for a payment method.
	 *
	 * @param string $payment_method Payment method identifier.
	 * @return string|null Icon URL or null if not found.
	 */
	private static function get_icon_url( $payment_method ) {
		$base_path = plugin_dir_path( Plugin::$file );
		$base_url  = plugins_url( '/', Plugin::$file );
		$image_dir = str_replace( '_', '-', $payment_method );

		foreach ( [ 'icon', 'icon-64x48' ] as $preferred ) {
			foreach ( [ 'svg', 'png', 'jpg', 'jpeg' ] as $ext ) {
				$file = $base_path . 'images/' . $image_dir . '/' . $preferred . '.' . $ext;

				if ( is_readable( $file ) ) {
					return $base_url . 'images/' . $image_dir . '/' . $preferred . '.' . $ext;
				}
			}
		}

		return null;
	}
}
