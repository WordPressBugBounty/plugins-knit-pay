<?php

namespace KnitPay\Extensions\Give;

use Pronamic\WordPress\Pay\Extensions\Give\Extension as Pronamic_Give_Extension;
use Pronamic\WordPress\Pay\Core\PaymentMethods;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;
use Pronamic\WordPress\Pay\Payments\Payment;
use Give\Framework\PaymentGateways\PaymentGatewayRegister;

/**
 * Title: Give extension
 * Description:
 * Copyright: 2020-2026 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   8.96.23.0
 * @version 8.96.23.0
 */
class Extension extends Pronamic_Give_Extension {
	/**
	 * Setup.
	 *
	 * @return void
	 */
	public function setup() {
		parent::setup();

		// Check if dependencies are met and integration is active.
		if ( ! $this->is_active() ) {
			return;
		}

		spl_autoload_register( [ $this, 'kp_give_dependency_autoload' ] );

		add_action( 'givewp_register_payment_gateway', [ __CLASS__, 'givewp_register_payment_gateway' ] );
	}

	
	/**
	 * Payment redirect URL filter.
	 *
	 * @param string  $url     Redirect URL.
	 * @param Payment $payment Payment.
	 *
	 * @return string
	 */
	public function redirect_url( $url, $payment ) {
		$gatewayData = $payment->get_meta( 'gateway_data', [] );

		// if no gateway data is set, it means it's a legacy payment.
		if ( ! isset( $gatewayData ) ) {
			$donation_id = (int) $payment->get_source_id();
			return add_query_arg(
				[
					'donation_id' => $donation_id,
				],
				parent::redirect_url( $url, $payment )
			);
		}
		
		switch ( $payment->get_status() ) {
			case PaymentStatus::CANCELLED:
			case PaymentStatus::FAILURE:
				$url = rawurldecode( $gatewayData->cancelUrl );

				break;
			case PaymentStatus::SUCCESS:
				$url = rawurldecode( $gatewayData->successUrl );

				break;
		}

		return $url;
	}

	public static function givewp_register_payment_gateway( PaymentGatewayRegister $paymentGatewayRegister ) {
		$paymentGatewayRegister->registerGateway( Gateway::class );

		$payment_methods = PaymentMethods::get_active_payment_methods();
		foreach ( $payment_methods as $payment_method ) {
			$gateway_class = Gateway::class . '_' . $payment_method;
			$paymentGatewayRegister->registerGateway( $gateway_class );
		}
	}

	private function kp_give_dependency_autoload( $class ) {
		if ( strpos( $class, 'KnitPay\\Extensions\\Give\\Gateway' ) === 0 ) {
			$after_namespace = substr( $class, strlen( 'KnitPay\\Extensions\\Give\\' ) );

			$after_namespace = sanitize_text_field( $after_namespace );

			// TODO: This is a temporary solution to load the class. Implement a better solution.
			eval(
				"namespace KnitPay\\Extensions\\Give;
				class $after_namespace extends Gateway {
					public function enqueueScript(int \$formId){
						parent::enqueueScript(\$formId);
					}
					public function getLegacyFormFieldMarkup(int \$formId, array \$args): string {
						return parent::getLegacyFormFieldMarkup(\$formId, \$args);
					}
				}"
			);
		}
	}
}
