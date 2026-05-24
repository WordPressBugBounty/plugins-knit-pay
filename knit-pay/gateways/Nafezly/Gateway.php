<?php

namespace KnitPay\Gateways\Nafezly;

use KnitPay\Gateways\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;
use Pronamic\WordPress\Pay\Payments\FailureReason;
use Nafezly\Payments\Compat\Config as NafezlyConfig;
use Illuminate\Http\Request;
use Nafezly\Payments\Factories\PaymentFactory as NafezlyPaymentFactory;

/**
 * Title: Nafezly Gateway
 * Copyright: 2020-2025 Knit Pay
 *
 * @author  Knit Pay
 * @version 1.0.0
 * @since   1.0.0
 */
class Gateway extends Core_Gateway {

	/**
	 * @var Config
	 */
	private $config;

	/**
	 * @var array
	 */
	private $args;

	/**
	 * @var \Nafezly\Payments\Interfaces\PaymentInterface|\Nafezly\Payments\Classes\BaseController|null
	 */
	private $driver;

	/**
	 * Initialise the gateway.
	 *
	 * @param Config $config
	 * @param array  $args
	 */
	public function init( Config $config, $args = [] ) {
		$this->config = $config;
		$this->args   = $args;

		$this->set_method( self::METHOD_HTTP_REDIRECT );

		$this->supports = [
			'payment_status_request',
		];

		// Load the secondary autoloader first so Nafezly vendor classes resolve.
		$secondary_autoload = KNITPAY_DIR . 'secondary-packages/vendor/autoload.php';
		if ( file_exists( $secondary_autoload ) ) {
			require_once $secondary_autoload;
		}

		// Load the compatibility shim so Nafezly classes can run.
		require_once KNITPAY_DIR . 'gateways/Nafezly/Compat/bootstrap.php';

		if ( isset( $this->args['supported_currencies'] ) ) {
			$this->supported_currencies = $this->args['supported_currencies'];
			$this->default_currency     = $this->supported_currencies[0];
			if ( ! empty( $this->args['default_currency'] ) ) {
				$this->default_currency = $this->args['default_currency'];
			}
		}
	}

	private function get_provider_name() {
		return $this->args['name'] ?? __( 'Payment Gateway', 'knit-pay-lang' );
	}

	/**
	 * Scoped configuration instance pushed onto the stack for this driver.
	 *
	 * @var NafezlyConfig|null
	 */
	private ?NafezlyConfig $scoped_config = null;

	private function push_driver( $extra_config = [] ) {
		// Build a fresh, isolated config scope.
		$this->scoped_config = new NafezlyConfig();
		$this->scoped_config->merge( $this->config->nafezly_config );

		// Inject runtime mode for gateways that require it (e.g. Paylink).
		if ( ! empty( $this->args['mode_key'] ) ) {
			$this->scoped_config->merge( [ $this->args['mode_key'] => $this->config->mode ] );
		}

		$this->scoped_config->merge( $extra_config );
		NafezlyConfig::push( $this->scoped_config );

		// Instantiate the Nafezly driver via the factory.
		$nafezly_class = $this->args['nafezly_class'] ?? '';
		if ( ! empty( $nafezly_class ) ) {
			$factory      = new NafezlyPaymentFactory();
			$this->driver = $factory->get( $nafezly_class );
		}
	}

	private function pop_driver() {
		if ( null !== $this->scoped_config ) {
			NafezlyConfig::pop();
			$this->scoped_config = null;
		}
	}

	/**
	 * @throws \Exception
	 */
	private function set_driver( $extra_config = [] ) {
		$this->push_driver( $extra_config );
	}

	/**
	 * Start a new payment.
	 *
	 * @param Payment $payment
	 * @throws \Exception
	 */
	public function start( Payment $payment ) {
		if ( PaymentStatus::SUCCESS === $payment->get_status() ) {
			return;
		}

		$customer        = $payment->get_customer();
		$billing_address = $payment->get_billing_address();

		// Extract all runtime values up front.
		$amount         = $payment->get_total_amount()->get_value();
		$currency       = $payment->get_total_amount()->get_currency()->get_alphabetic_code();
		$transaction_id = $payment->key . '_' . $payment->get_id();
		$language       = $customer ? $customer->get_language() : '';

		$user_id        = $customer ? $customer->get_user_id() : null;
		$first_name     = $customer ? $customer->get_name()->get_first_name() : null;
		$last_name      = $customer ? $customer->get_name()->get_last_name() : null;
		$email          = $customer ? $customer->get_email() : null;
		$customer_phone = $billing_address ? $billing_address->get_phone() : null;

		// Inject the return URL into config so route() resolves to it directly.
		$extra_config                      = [];
		$extra_config['VERIFY_ROUTE_NAME'] = $payment->get_return_url();

		// Inject runtime currency and language from the Payment object.
		if ( ! empty( $this->args['currency_key'] ) ) {
			$extra_config[ $this->args['currency_key'] ] = $currency;
		}
		if ( ! empty( $this->args['language_key'] ) ) {
			$extra_config[ $this->args['language_key'] ] = $language;
		}
		$this->set_driver( $extra_config );

		try {
			if ( null === $this->driver ) {
				throw new \Exception( 'Payment gateway not configured.' );
			}

			// Set every parameter on the driver symmetrically, just like language.
			$this->driver->setAmount( $amount );
			$this->driver->setCurrency( $currency );
			$this->driver->setPaymentId( $transaction_id );
			$this->driver->setLanguage( $language );
			$this->driver->setUserId( $user_id );
			$this->driver->setUserFirstName( $first_name );
			$this->driver->setUserLastName( $last_name );
			$this->driver->setUserEmail( $email );
			$this->driver->setUserPhone( $customer_phone );

			$payment->set_transaction_id( $transaction_id );

			// Call the Nafezly pay() method — arguments are already set on the driver.
			$response = $this->driver->pay();

			if ( ! is_array( $response ) ) {
				throw new \Exception( 'Unexpected response from payment gateway.' );
			}

			if ( empty( $response['payment_id'] ) ) {
				$raw           = \Illuminate\Http\Response::$lastRawBody ?? 'no raw body captured';
				$provider_name = $this->get_provider_name();
				throw new \Exception( $provider_name . ' returned empty payment_id. Parsed response: ' . wp_json_encode( $response ) . ' | Raw API body: ' . $raw );
			}

			// Some drivers return a view ('html') instead of a redirect URL.
			if ( ! empty( $response['redirect_url'] ) ) {
				$payment->set_action_url( $response['redirect_url'] );
			} elseif ( ! empty( $response['html'] ) ) {
				$payment->set_action_url( $payment->get_return_url() );
				$payment->set_meta( 'nafezly_html', $response['html'] );
			} else {
				// Fallback: return_url.
				$payment->set_action_url( $payment->get_return_url() );
			}

			if ( ! empty( $response['payment_id'] ) ) {
				$payment->set_meta( 'nafezly_payment_id', $response['payment_id'] );
			}
		} finally {
			$this->pop_driver();
		}
	}

	/**
	 * Update payment status on return / webhook.
	 *
	 * @param Payment $payment
	 * @throws \Exception
	 */
	public function update_status( Payment $payment ) {
		if ( PaymentStatus::SUCCESS === $payment->get_status() ) {
			return;
		}

		$this->set_driver();

		try {
			if ( null === $this->driver ) {
				$provider_name = $this->get_provider_name();
				throw new \Exception( $provider_name . ' is missing during status update.' );
			}

			// Resolve payment_id regardless of source.
			$payment_id = $payment->get_meta( 'nafezly_payment_id' );

			// Read the real HTTP request.
			$json         = json_decode( file_get_contents( 'php://input' ), true );
			$request_data = array_merge( $_GET, $_POST, $json ?: [] );

			// Detect webhook via optional per-gateway callback.
			$is_webhook = false;
			if ( ! empty( $this->args['webhook_detector'] ) && is_callable( $this->args['webhook_detector'] ) ) {
				$is_webhook = call_user_func( $this->args['webhook_detector'], $request_data );
			}

			if ( ! empty( $this->args['verify_key'] ) ) {
				$request_data[ $this->args['verify_key'] ] = $payment_id;
			} else {
				$request_data['payment_id'] = $payment_id;
			}

			$request = new Request( $request_data );

			$verified = $this->driver->verify( $request );

			if ( ! is_array( $verified ) || ! isset( $verified['success'] ) ) {
				$provider_name = $this->get_provider_name();
				throw new \Exception( 'Unexpected verify() response from ' . $provider_name . '.' );
			}

			$nafezly_message = sanitize_text_field( $verified['message'] ?? '' );
			$nafezly_status  = $verified['success'] ? 'SUCCESS' : 'FAILURE';

			$provider_name = $this->get_provider_name();
			$note          = sprintf(
				'%s status check: %s (payment_id: %s)',
				$provider_name,
				$nafezly_status,
				$payment_id
			);
			if ( $nafezly_message ) {
				$note .= ' — ' . $nafezly_message;
			}
			$payment->add_note( $note );

			if ( $verified['success'] ) {
				$payment->set_status( PaymentStatus::SUCCESS );
				if ( ! empty( $verified['payment_id'] ) ) {
					$payment->set_transaction_id( $verified['payment_id'] );
				}
			} else {
				$payment->set_status( PaymentStatus::FAILURE );
				$failure_reason = new FailureReason();
				$failure_reason->set_message( $nafezly_message ?: __( 'Payment failed.', 'knit-pay-lang' ) );
				$payment->set_failure_reason( $failure_reason );
			}

			// Store raw response data for debugging.
			$debug_data   = $verified['process_data'] ?? $verified;
			$process_data = is_string( $debug_data )
				? $debug_data
				: wp_json_encode( $debug_data, JSON_PRETTY_PRINT );
			$payment->add_note(
				'<details><summary>' . $provider_name . ' response</summary><pre>' . esc_html( $process_data ) . '</pre></details>'
			);

			// Webhook: save and exit before handle_returns() redirects.
			if ( $is_webhook ) {
				$payment->save();
				$payment->add_note( 'Received ' . $provider_name . ' webhook.' );
				\do_action( 'pronamic_pay_webhook_log_payment', $payment );
				exit;
			}
		} finally {
			$this->pop_driver();
		}
	}
}
