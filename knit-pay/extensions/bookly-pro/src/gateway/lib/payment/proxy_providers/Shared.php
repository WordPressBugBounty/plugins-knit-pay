<?php
namespace BooklyKnitPay\Lib\Payment\ProxyProviders;

use Bookly\Lib as BooklyLib;
use BooklyKnitPay\Lib\Payment\KnitPayGateway;
use Bookly\Frontend\Modules\Payment;
use Bookly\Lib\CartInfo;
use Bookly\Lib\Payment\Proxy;
use KnitPay\Extensions\BooklyPro\Extension;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;

class Shared extends Proxy\Shared {
	/**
	 * @inerhitDoc
	 */
	public static function getGatewayByName( $gateway, Payment\Request $request ) {
		if ( Extension::is_gateway_enabled( $gateway ) ) {
			return new KnitPayGateway( $gateway, $request );
		}

		return $gateway;
	}

	/**
	 * @inheritdoc
	 */
	public static function applyGateway( CartInfo $cart_info, $gateway ) {        
		if ( Extension::is_gateway_enabled( $gateway ) ) {
			$cart_info->setGateway( $gateway );
		}

		return $cart_info;
	}

	/**
	 * @inheritDoc
	 */
	public static function prepareOutdatedUnpaidPayments( $payments ) {
		$active_payment_methods = Extension::get_active_payment_methods();
		foreach ( $active_payment_methods as $payment_method ) {
			$timeout = (int) get_option( 'bookly_' . $payment_method . '_timeout' );
			if ( $timeout ) {
				$payments = array_merge(
					$payments,
					BooklyLib\Entities\Payment::query()
					->where( 'type', $payment_method )
					->where( 'status', BooklyLib\Entities\Payment::STATUS_PENDING )
					->whereLt( 'created_at', date_create( current_time( 'mysql' ) )->modify( sprintf( '- %s seconds', $timeout ) )->format( 'Y-m-d H:i:s' ) )
					->fetchCol( 'id' )
				);
			}
		}

		// Updating status in Knit Pay Payment.
		foreach ( $payments as $bookly_payment_id ) {
			$payment = get_pronamic_payment_by_meta( '_pronamic_payment_source_id', $bookly_payment_id );

			if ( null === $payment ) {
				continue;
			}

			// Add note.
			$note = __( 'Payment status updated by Bookly.', 'knit-pay-lang' );
			$payment->add_note( $note );

			$payment->set_status( PaymentStatus::EXPIRED );
			$payment->save();
		}

		return $payments;
	}

	/**
	 * @inheritDoc
	 */
	public static function showPaymentSpecificPrices( $show ) {
		$active_payment_methods = Extension::get_active_payment_methods();
		foreach ( $active_payment_methods as $payment_method ) {
			if ( $show ) {
				return $show;
			}

			if ( ! get_option( 'bookly_' . $payment_method . '_enabled' ) ) {
				continue;
			}

			$show = self::paymentSpecificPriceExists( $payment_method );
		}

		return $show;
	}
	
	/**
	 * @inerhitDoc
	 */
	public static function paymentSpecificPriceExists( $gateway ) {
		$active_payment_methods = Extension::get_active_payment_methods();

		if ( in_array( $gateway, $active_payment_methods ) ) {        
			return get_option( 'bookly_' . $gateway . '_increase' ) != 0
			|| get_option( 'bookly_' . $gateway . '_addition' ) != 0;
		}

		return false;
	}
}
