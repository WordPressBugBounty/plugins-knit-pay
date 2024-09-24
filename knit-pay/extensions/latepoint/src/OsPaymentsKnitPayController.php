<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Payments\Payment;
use KnitPay\Extensions\LatePoint\Helper;
use Pronamic\WordPress\Money\Currency;
use Pronamic\WordPress\Money\Money;


if ( ! class_exists( 'OsPaymentsKnitPayController' ) ) :


	class OsPaymentsKnitPayController extends OsController {
		function __construct() {
			parent::__construct();

			$this->action_access['customer'] = array_merge( $this->action_access['customer'], [ 'get_payment_options' ] );
			// $this->views_folder              = plugin_dir_path( __FILE__ ) . '../views/payments_razorpay/';
		}

		/* Generates payment options for Knit Pay payment modal */
		public function get_payment_options() {
			OsStepsHelper::set_booking_object( $this->params['booking'] );
			OsStepsHelper::set_restrictions( $this->params['restrictions'] );
			$customer = OsAuthHelper::get_logged_in_customer();

			$booking = OsStepsHelper::$booking_object;
			$amount  = $booking->specs_calculate_price_to_charge();

			if ( 0 >= $amount && $this->get_return_format() === 'json' ) {
				// free booking, nothing to pay (probably coupon was applied)
				$this->send_json(
					[
						'status'  => LATEPOINT_STATUS_SUCCESS,
						'message' => __( 'Nothing to pay', 'knit-pay-lang' ),
						'amount'  => $amount,
					]
				);
				return;
			}

			$config_id      = OsSettingsHelper::get_settings_value( 'knit_pay_config_id', get_option( 'pronamic_pay_config_id' ) );
			$payment_method = 'knit_pay';

			// Use default gateway if no configuration has been set.
			if ( empty( $config_id ) ) {
				$config_id = get_option( 'pronamic_pay_config_id' );
			}

			$gateway = Plugin::get_gateway( $config_id );

			if ( ! $gateway ) {
				$this->send_json(
					[
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => __(
							'Gateway not found for provided configuration.',
							'knit-pay'
						),
					]
				);
				return;
			}

			/**
			 * Build payment.
			 */
			$payment = new Payment();

			$payment->source = 'latepoint';

			$payment->source_id = $booking->generated_form_id;
			$payment->order_id  = $booking->generated_form_id;

			$payment->set_description( Helper::get_description( $booking ) );

			$payment->title = Helper::get_title( $booking->generated_form_id );

			// Customer.
			$payment->set_customer( Helper::get_customer( $customer ) );

			// Address.
			$payment->set_billing_address( Helper::get_address( $customer ) );

			// Currency.
			$currency = Currency::get_instance( OsSettingsHelper::get_settings_value( 'knit_pay_currency_iso_code' ) );

			// Amount.
			$payment->set_total_amount( new Money( $amount, $currency ) );

			// Method.
			$payment->set_payment_method( $payment_method );

			// Configuration.
			$payment->config_id = $config_id;

			try {
				$payment = Plugin::start_payment( $payment );

				$this->send_json(
					[
						'status'              => LATEPOINT_STATUS_SUCCESS,
						'message'             => __( 'Payment Link Created.', 'knit-pay-lang' ),
						'knitpay_payment_id'  => $payment->get_id(),
						'knitpay_payment_url' => $payment->get_pay_redirect_url(),
						'amount'              => $amount,
					]
				);
				return;
			} catch ( \Exception $e ) {
				$this->send_json(
					[
						'status'  => LATEPOINT_STATUS_ERROR,
						'message' => Plugin::get_default_error_message() . '<br>' . $e->getMessage(),
					]
				);
				return;
			}
		}
	}


endif;
