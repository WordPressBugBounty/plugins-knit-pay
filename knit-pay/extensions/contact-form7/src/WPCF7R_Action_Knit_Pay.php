<?php

use KnitPay\Extensions\ContactForm7\Helper;
use Pronamic\WordPress\Money\Currency;
use Pronamic\WordPress\Money\Money;
use Pronamic\WordPress\Number\Number;
use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Payments\Payment;

/**
 * Title: Contact Form 7 Gateway
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   5.60.0.0
 */

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit();

class WPCF7R_Action_Knit_Pay extends WPCF7R_Action {
	
	/**
	 * Init the parent action class
	 *
	 * @param $post
	 */
	public function __construct( $post ) {
		parent::__construct( $post );
		
	}
	
	/**
	 * Get the action admin fields
	 */
	public function get_action_fields() {
		$this->payment_method = 'knit_pay';
		
		return array_merge(
			[
				[
					'name'    => 'config_id',
					'type'    => 'select',
					'label'   => __( 'Configuration', 'knit-pay-lang' ),
					'options' => Plugin::get_config_select_options( $this->payment_method ),
					'footer'  => 'Configurations can be created in Knit Pay gateway configurations page at <a href="' . admin_url() . 'edit.php?post_type=pronamic_gateway">"Knit Pay >> Configurations"</a>.',
					'value'   => $this->get( 'config_id' ),
				],
				[
					'name'        => 'payment_description',
					'type'        => 'text',
					'label'       => __( 'Payment Description', 'knit-pay-lang' ),
					'placeholder' => __( 'Payment Description', 'knit-pay-lang' ),
					'footer'      => '<div>' . $this->get_formatted_mail_tags() . '</div>',
					'value'       => $this->get( 'payment_description' ),
				],
				'open_new_window'   => [
					'name'        => 'open_new_window',
					'type'        => 'checkbox',
					'label'       => __( 'Open in new window', 'knit-pay-lang' ),
					'sub_title'   => '',
					'placeholder' => '',
					'value'       => $this->get( 'open_new_window' ),
				],
				'redirection_pages' => [
					'name'        => 'general-alert',
					'type'        => 'notice',
					'label'       => __( 'Redirection Pages!', 'knit-pay-lang' ),
					'sub_title'   => __( 'Redirection pages can be configured on the <a href="' . add_query_arg( 'page', 'pronamic_pay_settings', get_admin_url( null, 'admin.php' ) ) . '" target="_blank">"Knit Pay >> Settings"</a> page.', 'knit-pay-lang' ),
					'placeholder' => '',
					'class'       => 'field-notice-alert',
				],
				'pricing_details'   => [
					'name'   => $this->payment_method . '_pricing_details',
					'type'   => 'section',
					'title'  => __( 'Pricing Details (Required)', 'knit-pay-lang' ),
					'class'  => '',
					'footer' => __( '<div class="qs-col qs-col-12">' . $this->get_formatted_mail_tags() . '</div>', 'knit-pay-lang' ),
					'fields' => [
						'currency' => [
							'name'        => 'currency',
							'type'        => 'text',
							'label'       => __( 'Currency', 'knit-pay-lang' ),
							'placeholder' => __( 'Currency (eg. INR)', 'knit-pay-lang' ),
							'value'       => $this->get( 'currency', 'INR' ),
							'class'       => 'qs-col qs-col-6',
						],
						'amount'   => [
							'name'        => 'amount',
							'type'        => 'text',
							'label'       => __( 'Amount', 'knit-pay-lang' ),
							'placeholder' => __( 'Amount', 'knit-pay-lang' ),
							'value'       => $this->get( 'amount', 0 ),
							'input_attr'  => ' required ',
							'class'       => 'qs-col qs-col-6',
							'footer'      => __( 'Enter fixed amount or choose from "Available mail tags"', 'knit-pay-lang' ),
						],
					],
				],
				'user_details'      => [
					'name'   => 'user_details',
					'type'   => 'section',
					'title'  => __( 'User details (Optional)', 'knit-pay-lang' ),
					'footer' => __( '<div>' . $this->get_formatted_mail_tags() . '</div>', 'knit-pay-lang' ),
					'class'  => '',
					'fields' => [
						'merhcant_account_alert' => [
							'name'          => 'general-alert',
							'type'          => 'notice',
							'label'         => __( 'Notice!', 'knit-pay-lang' ),
							'sub_title'     => __( 'Some fields are mandatory for some payment gateways', 'knit-pay-lang' ),
							'placeholder'   => '',
							'class'         => 'field-notice-alert',
							'show_selector' => '',
						],
						'first_name'             => [
							'name'        => 'first_name',
							'type'        => 'text',
							'label'       => __( 'First name', 'knit-pay-lang' ),
							'placeholder' => __( 'First name', 'knit-pay-lang' ),
							'value'       => $this->get( 'first_name' ),
							'class'       => 'qs-col qs-col-6 ',
						],
						'last_name'              => [
							'name'        => 'last_name',
							'type'        => 'text',
							'label'       => __( 'Last name', 'knit-pay-lang' ),
							'placeholder' => __( 'Last name', 'knit-pay-lang' ),
							'value'       => $this->get( 'last_name' ),
							'class'       => 'qs-col qs-col-6 ',
						],
						'buyer_phone'            => [
							'name'        => 'buyer_phone',
							'type'        => 'text',
							'label'       => __( 'Phone number', 'knit-pay-lang' ),
							'placeholder' => __( 'Phone number', 'knit-pay-lang' ),
							'value'       => $this->get( 'buyer_phone' ),
							'class'       => 'qs-col qs-col-6 ',
						],
						'buyer_email'            => [
							'name'        => 'buyer_email',
							'type'        => 'text',
							'label'       => __( 'Buyer email', 'knit-pay-lang' ),
							'placeholder' => __( 'Buyer email', 'knit-pay-lang' ),
							'value'       => $this->get( 'buyer_email' ),
							'class'       => 'qs-col qs-col-6 ',
						],
						'billing_address_1'      => [
							'name'        => 'billing_address_1',
							'type'        => 'text',
							'label'       => __( 'Street name of the billing address.', 'knit-pay-lang' ),
							'placeholder' => __( 'Billing Address 1', 'knit-pay-lang' ),
							'value'       => $this->get( 'billing_address_1' ),
							'class'       => 'qs-col qs-col-6 ',
						],
						'billing_address_2'      => [
							'name'        => 'billing_address_2',
							'type'        => 'text',
							'label'       => __( 'Street name of the billing address.', 'knit-pay-lang' ),
							'placeholder' => __( 'Billing Address 2', 'knit-pay-lang' ),
							'value'       => $this->get( 'billing_address_2' ),
							'class'       => 'qs-col qs-col-6 ',
						],
						'billing_city'           => [
							'name'        => 'billing_city',
							'type'        => 'text',
							'label'       => __( 'City name of the billing address.', 'knit-pay-lang' ),
							'placeholder' => __( 'Billing City', 'knit-pay-lang' ),
							'value'       => $this->get( 'billing_city' ),
							'class'       => 'qs-col qs-col-6 ',
						],
						'billing_state'          => [
							'name'        => 'billing_state',
							'type'        => 'text',
							'label'       => __( 'State name of the billing address.', 'knit-pay-lang' ),
							'placeholder' => __( 'Billing State', 'knit-pay-lang' ),
							'value'       => $this->get( 'billing_state' ),
							'class'       => 'qs-col qs-col-6 ',
						],
						'billing_country'        => [
							'name'        => 'billing_country',
							'type'        => 'text',
							'label'       => __( 'Country Code of the billing address.', 'knit-pay-lang' ),
							'placeholder' => __( 'Billing Country (ISO 3166 country code)', 'knit-pay-lang' ),
							'value'       => $this->get( 'billing_country' ),
							'class'       => 'qs-col qs-col-6 ',
						],
						'billing_zip'            => [
							'name'        => 'billing_zip',
							'type'        => 'text',
							'label'       => __( 'Zip code of the billing address.', 'knit-pay-lang' ),
							'placeholder' => __( 'Billing Zip', 'knit-pay-lang' ),
							'value'       => $this->get( 'billing_zip' ),
							'class'       => 'qs-col qs-col-6 ',
						],
					],
				],
			],
			parent::get_default_fields()
		);
	}
	
	public function process_validation( $submission ) {
	}
	
	/**
	 * Handle a simple redirect rule
	 *
	 * @param $submission
	 */
	public function process( $submission ) {
		// FIXME https://github.com/pronamic/wp-pay-core/issues/88
		$response = [];   

		$config_id      = $this->get( 'config_id' );
		$payment_method = 'knit_pay';

		// Use default gateway if no configuration has been set.
		if ( empty( $config_id ) ) {
			$config_id = get_option( 'pronamic_pay_config_id' );
		}

		$gateway = Plugin::get_gateway( $config_id );

		if ( ! $gateway ) {
			return false;
		}

		$order_id = $this->get_lead_id();

		$amount = Helper::get_value_from_tag( $this, 'amount' );
		try {
			$amount = new Number( $amount );
		} catch ( \InvalidArgumentException $e ) {
			return [
				'type'          => 'error',
				'error_message' => 'Invalid Amount: ' . $e->getMessage(),
			];
		}

		$unique_id = \time();

		/**
		 * Build payment.
		 */
		$payment = new Payment();

		try {
			$payment->source    = 'contact-form-7';
			$payment->source_id = $order_id;
			$payment->order_id  = $order_id;

			$payment->set_description( Helper::get_description( $this, $unique_id ) );

			$payment->title = Helper::get_title( $this, $unique_id );

			// Customer.
			$payment->set_customer( Helper::get_customer( $this ) );

			// Address.
			$payment->set_billing_address( Helper::get_address( $this ) );

			// Currency.
			$currency = Currency::get_instance( Helper::get_value_from_tag( $this, 'currency' ) );

			// Amount.
			$payment->set_total_amount( new Money( $amount, $currency ) );

			// Method.
			$payment->set_payment_method( $payment_method );

			// Configuration.
			$payment->config_id = $config_id;

			// Start the Payment.
			$payment = Plugin::start_payment( $payment );

			$response = [
				'type'         => $this->get( 'open_new_window' ) ? 'new_tab' : 'redirect',
				'redirect_url' => $payment->get_pay_redirect_url(),
			];
		} catch ( \Exception $e ) {
			$response = [
				'type'          => 'error',
				'error_message' => Plugin::get_default_error_message() . ' ' . $e->getMessage(),
			];
		}
		
		return $response;
	}
}
