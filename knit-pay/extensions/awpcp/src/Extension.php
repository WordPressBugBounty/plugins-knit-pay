<?php

namespace KnitPay\Extensions\AWPCP;

use Pronamic\WordPress\Pay\AbstractPluginIntegration;
use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Core\PaymentMethods;
use Pronamic\WordPress\Pay\Payments\Payment;
use AWPCP_Payment_Transaction;
use AWPCP_SettingsManager;
use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;


/**
 * Title: AWP Classifieds extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   2.5
 */
class Extension extends AbstractPluginIntegration {
	/**
	 * Slug
	 *
	 * @var string
	 */
	const SLUG = 'awp-classifieds';

	/**
	 * Constructs and initialize AWP Classifieds extension.
	 */
	public function __construct() {
		parent::__construct(
			[
				'name' => __( 'AWP Classifieds', 'knit-pay-lang' ),
			]
		);

		// Dependencies.
		$dependencies = $this->get_dependencies();

		$dependencies->add( new AWPCPDependency() );
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

		add_action( 'awpcp-register-payment-methods', [ $this, 'register_payment_methods' ] );
		add_action( 'awpcp-load-modules', [ $this, 'awpcp_load_modules' ], 10, 2 );

	}

	function awpcp_load_modules( $manager ) {
		add_action( 'awpcp_register_settings', [ $this, 'register_settings' ] );
	}

	public function register_settings( AWPCP_SettingsManager $settings_manager ) {
		$this->payment_method = 'knit_pay';
		$key                  = 'knit_pay-settings';

		$settings_manager->add_settings_subgroup(
			[
				'id'       => $key,
				'name'     => __( 'Knit Pay', 'awpcp-authorize.net' ),
				'parent'   => 'payment-settings',
				'priority' => 30,
			]
		);

		$settings_manager->add_settings_section(
			[
				'subgroup' => $key,
				'name'     => __( 'Knit Pay Settings', 'knit_pay' ),
				'id'       => $key,
				'priority' => 20,
			]
		);

		$label = __( 'Activate Knit Pay', 'awpcp-authorize.net' );
		$settings_manager->add_setting( $key, 'activate_knit_pay', $label, 'checkbox', 0, $label );

		$settings_manager->add_setting(
			[
				'id'         => 'knit_pay_title',
				'name'       => _x( 'Title', 'authorize.net', 'awpcp-authorize.net' ),
				'type'       => 'textfield',
				'default'    => PaymentMethods::get_name( $this->payment_method, __( 'Knit Pay', 'knit-pay-lang' ) ),
				'behavior'   => [
					'enabledIf' => 'activate_knit_pay',
				],
				'validation' => [
					'required' => [
						'depends' => 'activate_knit_pay',
					],
				],
				'section'    => $key,
			]
		);

		$settings_manager->add_setting(
			[
				'id'          => 'knit_pay_config_id',
				'name'        => __( 'Configuration', 'knit-pay-lang' ),
				'type'        => 'select',
				'description' => 'Configurations can be created in Knit Pay gateway configurations page at <a href="' . admin_url() . 'edit.php?post_type=pronamic_gateway">"Knit Pay >> Configurations"</a>.',
				'default'     => get_option( 'pronamic_pay_config_id' ),
				'behavior'    => [
					'enabledIf' => 'activate_knit_pay',
				],
				'validation'  => [
					'required' => [
						'depends' => 'activate_knit_pay',
					],
				],
				'section'     => $key,
				'options'     => Plugin::get_config_select_options( $this->payment_method ),
			]
		);

		$settings_manager->add_setting(
			[
				'id'          => 'knit_pay_payment_description',
				'name'        => __( 'Payment Description', 'knit-pay-lang' ),
				'type'        => 'textfield',
				'description' => sprintf( __( 'Available tags: %s', 'knit-pay-lang' ), sprintf( '<code>%s</code>', '{listing_id}' ) ),
				'default'     => 'AWP Classified Ad {listing_id}',
				'behavior'    => [
					'enabledIf' => 'activate_knit_pay',
				],
				'validation'  => [
					'required' => [
						'depends' => 'activate_knit_pay',
					],
				],
				'section'     => $key,
			]
		);
	}

	public function register_payment_methods( $payments ) {
		if ( get_awpcp_option( 'activate_knit_pay' ) ) {
			$name = get_awpcp_option( 'knit_pay_title' );
			$payments->register_payment_method( new Gateway( 'knit_pay', $name, '', '' ) );
		}

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
		$order_id    = $payment->get_order_id();
		$transaction = AWPCP_Payment_Transaction::find_by_id( $order_id );

		$awpcp_payments = awpcp_payments_api();

		return $awpcp_payments->get_return_url( $transaction );
	}

	/**
	 * Update the status of the specified payment
	 *
	 * @param Payment $payment Payment.
	 */
	public static function status_update( Payment $payment ) {

		// Don't update status if error occured while creating payment link, otherwise user will have to fill all the details again.
		// This is issue at AWP Classified end. This is workaround.
		if ( empty( $payment->get_transaction_id() ) ) {
			return;
		}

		$order_id    = $payment->get_order_id();
		$transaction = AWPCP_Payment_Transaction::find_by_id( $order_id );

		$awpcp_payments = awpcp_payments_api();

		wp_remote_get( $awpcp_payments->get_notify_url( $transaction ) );
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
		$text = __( 'AWP Classifieds', 'knit-pay-lang' ) . '<br />';

		$text .= sprintf(
			'<a href="%s">%s</a>',
			get_edit_post_link( $payment->source_id ),
			/* translators: %s: source id */
			sprintf( __( 'Ad %s', 'knit-pay-lang' ), $payment->source_id )
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
		return __( 'AWP Classified Ad', 'knit-pay-lang' );
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
