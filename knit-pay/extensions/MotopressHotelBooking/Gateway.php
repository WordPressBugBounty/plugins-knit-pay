<?php

namespace KnitPay\Extensions\MotopressHotelBooking;

use MPHB\Admin\Fields;
use MPHB\Admin\Groups;
use MPHB\Entities\Booking;
use MPHB\Entities\Payment as HotelBooking_Payment;
use MPHB\Payments\Gateways\Gateway as HotelBooking_Gateway;
use Pronamic\WordPress\Money\Currency;
use Pronamic\WordPress\Money\Money;
use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Payments\Payment;

/**
 * Title: MotoPress Hotel Booking extension
 * Description:
 * Copyright: 2020-2026 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   3.6.0
 */

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit();

class Gateway extends HotelBooking_Gateway {
	private $config_id;
	private $payment_description;
	private $payment_method;

	public function __construct( $title, $payment_method = 'knit_pay' ) {
		$this->adminTitle     = $title;
		$this->payment_method = $payment_method;
		$this->id             = 'knit_pay' === $payment_method ? 'knit_pay' : 'knit_pay_' . $payment_method;

		add_filter( 'mphb_gateway_has_sandbox', [ $this, 'hide' ], 10, 2 );
		add_filter( 'mphb_gateway_has_instructions', [ $this, 'hide' ], 10, 2 );

		parent::__construct();
	}

	protected function initId() {
		return $this->id;
	}

	/**
	 * @param bool   $show
	 * @param string $gatewayId
	 * @return bool
	 */
	public function hide( $show, $gatewayId ) {
		if ( $gatewayId == $this->id ) {
			$show = false;
		}

		return $show;
	}

	protected function setupProperties() {
		parent::setupProperties();

		if ( empty( $this->title ) ) {
			$this->title = __( 'Online Payment', 'knit-pay-lang' );
		}

		$this->config_id           = sanitize_text_field( $this->getOption( 'config_id' ) );
		$this->payment_description = sanitize_text_field( $this->getOption( 'payment_description' ) );
	}

	protected function initDefaultOptions() {
		$defaults = [
			'title'       => __( 'Online Payment', 'knit-pay-lang' ),
			'description' => '',
			'enabled'     => false,
		];

		return array_merge( parent::initDefaultOptions(), $defaults );
	}

	public function processPayment( Booking $booking, HotelBooking_Payment $hb_payment ) {
		// Initiating Payment.
		$config_id      = $this->config_id;
		$payment_method = $this->payment_method;

		// Use default gateway if no configuration has been set.
		if ( empty( $config_id ) ) {
			$config_id = get_option( 'pronamic_pay_config_id' );
		}

		$gateway = Plugin::get_gateway( $config_id );

		if ( ! $gateway ) {
			return false;
		}

		/**
		 * Build payment.
		 */
		$payment = new Payment();

		$payment->source    = 'motopress-hotel-booking';
		$payment->source_id = $booking->getId();
		$payment->order_id  = $hb_payment->getId();

		$payment->set_description( Helper::get_description( $this->payment_description, $booking, $hb_payment ) );

		$payment->title = Helper::get_title( $booking->getId() );

		// Customer.
		$payment->set_customer( Helper::get_customer( $booking->getCustomer() ) );

		// Address.
		$payment->set_billing_address( Helper::get_address( $booking->getCustomer() ) );

		// Currency.
		$currency = Currency::get_instance( $hb_payment->getCurrency() );

		// Amount.
		$payment->set_total_amount( new Money( $hb_payment->getAmount(), $currency ) );

		// Method.
		$payment->set_payment_method( $payment_method );

		// Configuration.
		$payment->config_id = $config_id;

		// Let Knit Pay's handler run the gateway redirect; HB 6+ captures this URL
		// for its checkout JSON response, so don't call $gateway->redirect() here.
		$payment = Plugin::start_payment( $payment );

		wp_safe_redirect( $payment->get_pay_redirect_url(), 303 );
	}

	/**
	 *
	 * @param \MPHB\Admin\Tabs\SettingsSubTab $subTab
	 */
	public function registerOptionsFields( &$subTab ) {
		parent::registerOptionsFields( $subTab );
		$group = new Groups\SettingsGroup( "mphb_payments_{$this->id}_group2", '', $subTab->getOptionGroupName() );

		$groupFields = [
			Fields\FieldFactory::create(
				"mphb_payment_gateway_{$this->id}_config_id",
				[
					'type'        => 'select',
					'label'       => __( 'Configuration', 'knit-pay-lang' ),
					'description' => 'Configurations can be created in Knit Pay gateway configurations page at <a href="' . admin_url( 'edit.php?post_type=pronamic_gateway' ) . '">"Knit Pay >> Configurations"</a>.',
					'list'        => Plugin::get_config_select_options( $this->payment_method ),
					'default'     => get_option( 'pronamic_pay_config_id' ),
				]
			),
			Fields\FieldFactory::create(
				"mphb_payment_gateway_{$this->id}_payment_description",
				[
					'type'        => 'text',
					'label'       => __( 'Payment Description', 'knit-pay-lang' ),
					'description' => sprintf( __( 'Available tags: %s', 'knit-pay-lang' ), sprintf( '<code>%s</code>', '{booking_id}, {payment_id}' ) ),
					'default'     => __( 'MotoPress Hotel Booking {booking_id}', 'knit-pay-lang' ),
				]
			),
		];

		$group->addFields( $groupFields );

		$subTab->addGroup( $group );
	}
}
