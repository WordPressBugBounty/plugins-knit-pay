<?php
use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;
use Pronamic\WordPress\Http\Facades\Http;
use Pronamic\WordPress\Pay\Payments\FailureReason;
use Pronamic\WordPress\Pay\Payments\Payment;

class KnitPayPro {
	function __construct() {
		add_action( 'pronamic_payment_status_update', [ $this, 'knit_pay_pro_payment_status_update' ] );
		add_action( 'knit_pay_pro_payment_success', [ $this, 'knit_pay_pro_payment_success' ] );
	}

	public static function check_knit_pay_pro_setup() {
		if ( ! get_option( 'knit_pay_pro_setup_rapidapi_key' ) ) {
			throw new Exception( 'The "Knit Pay - Pro" configuration is not set up correctly. Please visit the "Knit Pay >> Knit Pay Pro Setup" page to configure "Knit Pay - Pro".' );
		}
	}

	public static function knit_pay_pro_payment_status_update( Payment $payment ) {
		self::check_knit_pay_pro_setup();

		$invalid_gateways = [ 'instamojo', 'manual', 'go-url', 'razorpay', 'sslcommerz', 'upi-qr', 'test' ];
		$invalid_sources  = [ 'woocommerce', 'camptix', 'charitable', 'contact-form-7', 'easydigitaldownloads', 'give', 'gravityformsideal', 'knit-pay-payment-button', 'knit-pay-payment-link', 'learndash', 'learnpress', 'lifterlms', 'ninja-forms', 'paid-memberships-pro', 'profile-press', 'tourmaster', 'wp-travel', 'wp-travel-engine' ];

		$gateway = \get_post_meta( $payment->get_config_id(), '_pronamic_gateway_id', true );
		$source  = $payment->get_source();

		if ( empty( $payment->get_subscriptions() ) && in_array( $gateway, $invalid_gateways, true ) && in_array( $source, $invalid_sources, true ) ) {
			return;
		}

		if ( 'test' === $payment->get_mode() ) {
			return;
		}

		if ( ! empty( $payment->get_meta( 'kpp_charge_id' ) ) ) {
			return;
		}

		switch ( $payment->get_status() ) {
			case Core_Statuses::COMPLETED:
			case Core_Statuses::SUCCESS:
			case Core_Statuses::AUTHORIZED:
				\as_enqueue_async_action(
					'knit_pay_pro_payment_success',
					[
						'payment_id' => $payment->get_id(),
					],
					'knit-pay-pro'
				);

				break;
			case Core_Statuses::OPEN:
			case Core_Statuses::FAILURE:
				if ( count(
					as_get_scheduled_actions(
						[
							'hook'     => 'knit_pay_pro_payment_success',
							'per_page' => 100,
							'status'   => [
								ActionScheduler_Store::STATUS_RUNNING,
								ActionScheduler_Store::STATUS_PENDING,
							],
						]
					)
				) >= 15 ) {
					$payment->set_status( Core_Statuses::FAILURE );
					
					$error_message = 'An error occurred while establishing a connection with RapidAPI. This issue may arise if the "Knit Pay - Pro" configuration is incorrect or if the RapidAPI plan has expired or been suspended. Kindly recheck the "Knit Pay - Pro" RapidAPI subscription and configure it correctly on the "Knit Pay Pro Setup" page.';
					
					$failure_reason = new FailureReason();
					$failure_reason->set_message( $error_message );
					$payment->set_failure_reason( $failure_reason );
					
					$payment->add_note( $error_message );
					
					$payment->save();
					
					throw new Exception( $error_message );
				}
				break;
			default:
		}
	}

	public function knit_pay_pro_payment_success( $payment_id ) {
		$payment = \get_pronamic_payment( $payment_id );

		// No payment found, unable to check status.
		if ( null === $payment ) {
			return;
		}

		try {
			$response = Http::post(
				KNIT_PAY_PRO_RAPIDAPI_BASE_URL . 'payments/success',
				[
					'body'    => wp_json_encode(
						[
							'knitpay_payment_id' => $payment->get_id(),
							'mode'               => $payment->get_mode(),
							'gateway'            => \get_post_meta( $payment->get_config_id(), '_pronamic_gateway_id', true ),
							'payment_method'     => $payment->get_payment_method(),
							'source'             => $payment->get_source(),
							'amount'             => $payment->get_total_amount()->number_format( null, '.', '' ),
							'currency'           => $payment->get_total_amount()->get_currency()->get_alphabetic_code(),
							'knitpay_version'    => KNITPAY_VERSION,
							'php_version'        => PHP_VERSION,
							'website_url'        => home_url( '/' ),
							'data'               => [],
						]
					),
					'headers' => [
						'X-RapidAPI-Host' => KNIT_PAY_PRO_RAPIDAPI_HOST,
						'X-RapidAPI-Key'  => get_option( 'knit_pay_pro_setup_rapidapi_key' ),
					],
				]
			);

			$result = $response->json();

			if ( '200' !== (string) $response->status() ) {
				$payment->add_note( 'RapidAPI Error: ' . $result->message );

				// Error Occured, retry after some time.
				return $this->schedule_next_retry( $payment );
			}
		} catch ( Exception $e ) {
			// Error Occured, retry after some time.
			return $this->schedule_next_retry( $payment );
		}

		if ( $result->success ) {
			$payment->set_meta( 'kpp_charge_id', $result->id );
			return;
		}

		return $this->schedule_next_retry( $payment );
	}

	private function schedule_next_retry( $payment ) {
		\as_schedule_single_action(
			time() + 30 * MINUTE_IN_SECONDS,
			'knit_pay_pro_payment_success',
			[
				'payment_id' => $payment->get_id(),
			],
			'knit-pay-pro'
		);
	}
}

new KnitPayPro();
