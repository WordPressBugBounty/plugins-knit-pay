<?php

namespace KnitPay\Gateways\UpiQR;

use Pronamic\WordPress\Pay\Core\GatewayConfig;
use ReflectionObject;

/**
 * Title: UPI QR Config
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 1.0.0
 * @since   4.1.0
 */
class Config extends GatewayConfig {
	public $payee_name;

	public $vpa;

	public $payment_template;

	public $merchant_category_code;

	public $payment_instruction;

	public $mobile_payment_instruction;

	public $payment_success_status;

	public $transaction_id_field;

	public $hide_mobile_qr;

	public $hide_pay_button;

	public $show_download_qr_button;

	public function copy_properties( $source ) {
		$reflection_source      = new ReflectionObject( $source );
		$reflection_destination = new ReflectionObject( $this );

		foreach ( $reflection_source->getProperties() as $property ) {
			$property_name = $property->getName();

			if ( $reflection_destination->hasProperty( $property_name ) ) {
				$destination_property = $reflection_destination->getProperty( $property_name );
				$destination_property->setAccessible( true );

				$source_property = $property->getValue( $source );
				$destination_property->setValue( $this, $source_property );
			}
		}
	}
}
