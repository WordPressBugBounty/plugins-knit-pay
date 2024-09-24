<?php

namespace KnitPay\Extensions\LearnPress;

use Exception;
use Pronamic\WordPress\Pay\AbstractPluginIntegration;
use Pronamic\WordPress\Pay\Core\PaymentMethods;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;
use LP_Gateway_Offline_Payment;
use LP_Order;
use LP_Request;

/**
 * Title: Learn Press extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   1.6.0
 */
class Extension extends AbstractPluginIntegration {
	/**
	 * Slug
	 *
	 * @var string
	 */
	const SLUG = 'learnpress';

	/**
	 * Constructs and initialize Learn Press extension.
	 */
	public function __construct() {
		parent::__construct(
			[
				'name' => __( 'Learn Press', 'knit-pay-lang' ),
			]
		);

		// Dependencies.
		$dependencies = $this->get_dependencies();

		$dependencies->add( new LearnPressDependency() );
	}

	/**
	 * Setup plugin integration.
	 *
	 * @return void
	 */
	public function setup() {
		add_filter( 'pronamic_payment_source_text_' . self::SLUG, [ $this, 'source_text' ], 10, 2 );
		add_filter( 'pronamic_payment_source_description_' . self::SLUG, [ $this, 'source_description' ], 10, 2 );
		add_filter( 'pronamic_payment_source_url_' . self::SLUG, [ $this, 'source_url' ], 10, 2 );

		// Check if dependencies are met and integration is active.
		if ( ! $this->is_active() ) {
			return;
		}

		add_filter( 'pronamic_payment_redirect_url_' . self::SLUG, [ $this, 'redirect_url' ], 10, 2 );
		add_action( 'pronamic_payment_status_update_' . self::SLUG, [ $this, 'status_update' ], 10 );

		// Add Custom Billing Fields.
		add_filter( 'learn-press/profile-settings-fields/general', [ __CLASS__, 'billing_custom_fields_mapping' ] );
		add_filter( 'learnpress_metabox_settings_sanitize_option', [ __CLASS__, 'save_billing_options' ], 10, 2 );
		add_action( 'lp/before_create_new_customer', [ __CLASS__, 'validate_new_customer_fields' ], 10, 6 );

		// Add Knit Pay Payment Methods.
		add_filter( 'learn-press/payment-methods', [ $this, 'add_payment' ] );

		// Add Custom Order Statuses.
		add_filter( 'learn-press/valid-order-statuses-for-payment-complete', [ $this, 'valid_order_statuses' ], 10, 2 );
	}

	/*
	 * Statuses which can be converted to complete.
	 */
	public function valid_order_statuses( $statuses, LP_Order $order ) {
		if ( substr( $order->get_data( 'payment_method' ), 0, 8 ) !== 'knit_pay' ) {
			return $statuses;
		}
		$statuses[] = 'cancelled';
		$statuses[] = 'failed';
		return $statuses;
	}

	public static function billing_custom_fields_mapping( $options ) {
		for ( $i = count( $options ) - 1; $i >= 0; $i-- ) {
			if ( 'register_profile_fields' === $options[ $i ]['id'] ) {
				$options[ $i ]['options']['knit_pay_register_profile_fields'] = [
					'title'   => esc_html__( 'Knit Pay Billing Field', 'knit-pay-lang' ),
					'type'    => 'select',
					'options' => [
						'0'                   => esc_html__( 'Select if Applicable', 'knit-pay-lang' ),
						'phone'               => esc_html__( 'Phone', 'knit-pay-lang' ),
						'billing_line_1'      => esc_html__( 'Billing Address Line 1', 'knit-pay-lang' ),
						'billing_line_2'      => esc_html__( 'Billing Address Line 2', 'knit-pay-lang' ),
						'billing_postal_code' => esc_html__( 'Billing Postal Code', 'knit-pay-lang' ),
						'billing_city'        => esc_html__( 'Billing City', 'knit-pay-lang' ),
						'billing_region'      => esc_html__( 'Billing Region', 'knit-pay-lang' ),
						'billing_country'     => esc_html__( 'Billing Country Code', 'knit-pay-lang' ),
					],
				];
			}
		}

		return $options;
	}

	public static function save_billing_options( $value, $option ) {
		if ( 'learn_press_register_profile_fields' === $option['id'] ) {
			$knit_pay_register_profile_fields = [];
			foreach ( $value as $field ) {
				if ( array_key_exists( 'knit_pay_register_profile_fields', $field ) && '0' !== $field['knit_pay_register_profile_fields'] ) {
					$knit_pay_register_profile_fields[ $field['knit_pay_register_profile_fields'] ] = $field['id'];
				}
			}
			update_option( 'learn_press_knit_pay_register_profile_fields', $knit_pay_register_profile_fields );
		}
		return $value;
	}

	public static function validate_new_customer_fields( $email, $username, $password, $confirm_password, $args, $update_meta ) {
		if ( array_key_exists( 'first_name', $args ) && '' === $args['first_name'] ) {
			throw new Exception( __( 'Please enter a valid first name.', 'knit-pay-lang' ) );
		} elseif ( array_key_exists( 'last_name', $args ) && '' === $args['last_name'] ) {
			throw new Exception( __( 'Please enter a valid last name.', 'knit-pay-lang' ) );
		}
	}

	public static function add_payment( $methods ) {
		$new_method['knit_pay'] = new Gateway(
			[
				'id'                 => 'knit_pay',
				'payment_method'     => 'knit_pay',
				'method_title'       => __( 'Knit Pay', 'knit-pay-lang' ),
				'method_description' => __( "This payment method does not use a predefined payment method for the payment. Some payment providers list all activated payment methods for your account to choose from. Use payment method specific gateways (such as 'Instamojo') to let customers choose their desired payment method at checkout.", 'knit-pay-lang' ),
				'icon'               => '',
				'title'              => 'Pay Online',
			]
		);

		$active_payment_methods = PaymentMethods::get_active_payment_methods();
		foreach ( $active_payment_methods as $payment_method ) {
			$id                = 'knit_pay_' . $payment_method;
			$method_name       = PaymentMethods::get_name( $payment_method );
			$new_method[ $id ] = new Gateway(
				[
					'id'                 => $id,
					'payment_method'     => $payment_method,
					'method_title'       => __( 'Knit Pay', 'knit-pay-lang' ) . ' - ' . $method_name,
					'method_description' => '',
					'icon'               => '',
					'title'              => $method_name,
				]
			);
		}

		return $new_method + $methods;
	}

	/**
	 * Payment redirect URL filter.
	 *
	 * @param string  $url     Redirect URL.
	 * @param Payment $payment Payment.
	 *
	 * @return string
	 */
	public static function redirect_url( $url, $payment ) {
		$source_id = (int) $payment->get_source_id();
		$order     = learn_press_get_order( $source_id );
		if ( ! $order ) {
			return $url;
		}

		$gateway = new LP_Gateway_Offline_Payment();

		switch ( $payment->get_status() ) {
			case Core_Statuses::CANCELLED:
			case Core_Statuses::EXPIRED:
			case Core_Statuses::FAILURE:
				learn_press_add_message(
					sprintf(
						'Payment with Payment ID: %s, Transaction ID: %s Failed.',
						$payment->get_id(),
						$payment->transaction_id
					),
					'error'
				);
				$url = esc_url( get_permalink( get_option( 'learn_press_checkout_page_id' ) ) );
				break;

			case Core_Statuses::SUCCESS:
				$url = esc_url( $gateway->get_return_url( $order ) );
				break;

			case Core_Statuses::AUTHORIZED:
			case Core_Statuses::OPEN:
			default:
		}

		return $url;
	}

	/**
	 * Update the status of the specified payment
	 *
	 * @param Payment $payment Payment.
	 */
	public static function status_update( Payment $payment ) {
		$source_id = (int) $payment->get_source_id();

		$order = learn_press_get_order( $source_id );
		if ( ! $order ) {
			return;
		}

		switch ( $payment->get_status() ) {
			case Core_Statuses::CANCELLED:
			case Core_Statuses::EXPIRED:
				$order->update_status( 'cancelled' );

				break;
			case Core_Statuses::FAILURE:
				$order->update_status( 'failed' );

				break;
			case Core_Statuses::SUCCESS:
				$order->payment_complete( $payment->get_transaction_id() );
				$order->add_note(
					sprintf(
						"%s payment completed with Transaction Id of '%s'",
						$payment->get_payment_method(),
						$payment->transaction_id
					)
				);

				break;
			case Core_Statuses::OPEN:
			default:
				$order->update_status( 'pending' );

				break;
		}
	}

	/**
	 * Source column
	 *
	 * @param string  $text    Source text.
	 * @param Payment $payment Payment.
	 *
	 * @return string $text
	 */
	public function source_text( $text, Payment $payment ) {
		$text = __( 'Learn Press', 'knit-pay-lang' ) . '<br />';

		$text .= sprintf(
			'<a href="%s">%s</a>',
			get_edit_post_link( $payment->source_id ),
			/* translators: %s: source id */
			sprintf( __( 'Order %s', 'knit-pay-lang' ), $payment->source_id )
		);

		return $text;
	}

	/**
	 * Source description.
	 *
	 * @param string  $description Description.
	 * @param Payment $payment     Payment.
	 *
	 * @return string
	 */
	public function source_description( $description, Payment $payment ) {
		return __( 'Learn Press Order', 'knit-pay-lang' );
	}

	/**
	 * Source URL.
	 *
	 * @param string  $url     URL.
	 * @param Payment $payment Payment.
	 *
	 * @return string
	 */
	public function source_url( $url, Payment $payment ) {
		return get_edit_post_link( $payment->source_id );
	}

}
