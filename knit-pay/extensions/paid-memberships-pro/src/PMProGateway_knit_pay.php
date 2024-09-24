<?php
use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Payments\Payment;
use KnitPay\Extensions\PaidMembershipsPro\Helper;

/**
 * Title: Paid Memberships Pro extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author knitpay
 * @since 2.0.0
 * @version 8.87.10.1
 */

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit();

class PMProGateway_knit_pay extends PMProGateway {


	protected $config_id;

	protected $payment_description;

	/**
	 *
	 * @var string
	 */
	public $id = 'knit_pay';

	/**
	 * Payment method.
	 *
	 * @var string
	 */
	private $payment_method;

	/**
	 * Bootstrap
	 *
	 * @param array $args
	 *            Gateway properties.
	 */
	public function __construct( $gateway = null ) {
		$this->gateway = $gateway;
		$this->title   = __( 'Knit Pay', 'knit-pay-lang' );
		$this->id      = 'knit_pay';
		return $this->gateway;
	}

	/**
	 * Run on WP init
	 *
	 * @since 1.8
	 */
	function init() {
		$this->config_id = pmpro_getOption( 'knit_pay_config_id' );

		// make sure knit pay is a gateway option
		add_filter( 'pmpro_gateways', [ $this, 'pmpro_gateways' ] );

		// add fields to payment settings
		add_filter( 'pmpro_payment_options', [ $this, 'pmpro_payment_options' ] );
		add_filter( 'pmpro_payment_option_fields', [ $this, 'pmpro_payment_option_fields' ], 10, 2 );

		// code to add at checkout if knit pay is the current gateway
		$gateway = pmpro_getOption( 'gateway' );
		if ( $gateway == $this->id ) {
			add_filter( 'pmpro_include_payment_information_fields', '__return_false' );
			add_filter( 'pmpro_required_billing_fields', [ $this, 'pmpro_required_billing_fields' ] );
			add_action( 'pmpro_checkout_before_change_membership_level', [ $this, 'pmpro_checkout_before_change_membership_level' ], 10, 2 );
			add_action( 'pmpro_checkout_before_form', [ $this, 'hide_checkout_fields' ] );
		}
	}

	/**
	 * Make sure knit_pay is in the gateways list
	 *
	 * @since 1.8
	 */
	function pmpro_gateways( $gateways ) {
		if ( empty( $gateways[ $this->id ] ) ) {
			// TODO: remove hardcode
			$gateways[ $this->id ] = __( 'Knit Pay', 'knit-pay-lang' );
		}
		return $gateways;
	}

	/**
	 * Get a list of payment options that the knit pay gateway needs/supports.
	 *
	 * @since 1.8
	 */
	static function getGatewayOptions() {
		$options = [
			'sslseal',
			'nuclear_HTTPS',
			'gateway_environment',
			'currency',
			'use_ssl',
			'tax_state',
			'tax_rate',
			'accepted_credit_cards',
			'knit_pay_payment_description',
			'knit_pay_config_id',
			'knit_pay_hide_billing_address',
			'knit_pay_hide_phone',
			'knit_pay_title',
		];

		return $options;
	}

	/**
	 * Set payment options for payment settings page.
	 *
	 * @since 1.8
	 */
	function pmpro_payment_options( $options ) {
		// get knit pay options
		$knit_pay_options = $this->getGatewayOptions();

		// merge with others.
		$options = array_merge( $knit_pay_options, $options );

		return $options;
	}

	/**
	 * Display fields for Knit Pay options.
	 *
	 * @since 1.8
	 */
	function pmpro_payment_option_fields( $values, $gateway ) {
		$configurations       = Plugin::get_config_select_options( $this->id );
		$payment_description  = pmpro_getOption( 'knit_pay_payment_description' );
		$hide_billing_address = pmpro_getOption( 'knit_pay_hide_billing_address' );
		$hide_phone_field     = pmpro_getOption( 'knit_pay_hide_phone' );
		$title                = pmpro_getOption( 'knit_pay_title' );

		if ( empty( $this->config_id ) ) {
			$this->config_id = get_option( 'pronamic_pay_config_id' );
			pmpro_setOption( 'knit_pay_config_id', $this->config_id );
		}
		if ( empty( $payment_description ) ) {
			$payment_description = 'Paid Memberships Pro {order_id}';
			pmpro_setOption( 'knit_pay_payment_description', $payment_description );
		}
		if ( empty( $title ) ) {
			$title = 'Knit Pay';
			pmpro_setOption( 'knit_pay_title', $title );
		}

		// Knit Pay Settings Heading.
		// TODO add message that recurring payments are not supported.
		$form  = '';
		$form .= '<tr class="pmpro_settings_divider gateway gateway_' . $this->id . '"';
		if ( $gateway != $this->id ) {
			$form .= ' style="display: none;"';
		}
		$form .= '><td colspan="2">	<hr /><h2 class="title">Knit Pay Settings</h2></td></tr>';

		// Title.
		$form .= '<tr class="gateway gateway_' . $this->id . '"';
		if ( $gateway != $this->id ) {
			$form .= '	style="display: none;"';
		}
		$form .= '>
            <th scope="row" valign="top"><label for="knit_pay_title">Title:</label>	</th>
            <td>
                <input type="text" id="knit_pay_title" name="knit_pay_title"	value="' . $title . '" class="regular-text code" />
            	<p class="description">' . __( 'Payment Method title visible on the payment confirmation page and invoice page.', 'knit-pay-lang' ) . '</p>
            </td>
        </tr>';

		// Configuration.
		$form .= '<tr class="gateway gateway_' . $this->id . '"';
		if ( $gateway != $this->id ) {
			$form .= '	style="display: none;"';
		}
		$form .= '>
            <th scope="row" valign="top"><label for="knit_pay_config_id">Configuration:</label></th>
        	<td><select id="knit_pay_config_id" name="knit_pay_config_id">';
		foreach ( $configurations as $key => $configuration ) {
			$form .= '<option value="' . $key . '"' . selected( pmpro_getOption( 'knit_pay_config_id' ), $key, false ) . '>' . $configuration . '</option>';
		}
				$form .= '	</select>
        		<p class="description">' . __( 'Configurations can be created in Knit Pay gateway configurations page at <a href="' . admin_url() . 'edit.php?post_type=pronamic_gateway">"Knit Pay >> Configurations"</a>.', 'knit-pay-lang' ) . '</p>
        	</td>
        </tr>';

		// Payment Description.
		$form .= '<tr class="gateway gateway_' . $this->id . '"';
		if ( $gateway != $this->id ) {
			$form .= '	style="display: none;"';
		}
		$form .= '>
            <th scope="row" valign="top"><label for="knit_pay_payment_description">Payment Description:</label>	</th>
            <td>
                <input type="text" id="knit_pay_payment_description" name="knit_pay_payment_description"	value="' . $payment_description . '" class="regular-text code" />
            	<p class="description">' . sprintf( __( 'Available tags: %s', 'knit-pay-lang' ), sprintf( '<code>%s</code>', '{order_id}, {code}, {invoice_id}, {membership_name}' ) ) . '</p>
            </td>
        </tr>';

		// Hide Billing Address.
		$hide_options = [
			'0' => 'No',
			'1' => 'Yes',
		];
		$form        .= '<tr class="gateway gateway_' . $this->id . '"';
		if ( $gateway != $this->id ) {
			$form .= '	style="display: none;"';
		}
		$form .= '>
            <th scope="row" valign="top"><label for="knit_pay_hide_billing_address">Hide Billing Address Fields:</label></th>
        	<td><select id="knit_pay_hide_billing_address" name="knit_pay_hide_billing_address">';
		foreach ( $hide_options as $key => $hide_option ) {
			$form .= '<option value="' . $key . '"' . selected( $hide_billing_address, $key, false ) . '>' . $hide_option . '</option>';
		}
		$form .= '	</select>
        		<p class="description">' . __( 'Hide not required billing address fields on the checkout page.', 'knit-pay-lang' ) . '</p>
        	</td>
        </tr>';

		// Hide Phone Field.
		$hide_options = [
			'0' => 'No',
			'1' => 'Yes',
		];
		$form        .= '<tr class="gateway gateway_' . $this->id . '"';
		if ( $gateway != $this->id ) {
			$form .= '	style="display: none;"';
		}
		$form .= '>
            <th scope="row" valign="top"><label for="knit_pay_hide_phone">Hide Phone Field:</label></th>
        	<td><select id="knit_pay_hide_phone" name="knit_pay_hide_phone">';
		foreach ( $hide_options as $key => $hide_option ) {
			$form .= '<option value="' . $key . '"' . selected( $hide_phone_field, $key, false ) . '>' . $hide_option . '</option>';
		}
		$form .= '	</select>
        		<p class="description">' . __( 'Hide Phone field on the checkout page.', 'knit-pay-lang' ) . '</p>
        	</td>
        </tr>';

		// Display Currency Fields.
		$form .= '<script>window.onload = function() {pmpro_changeGateway();}</script>';

		echo $form;
	}

	/**
	 * Remove required billing fields
	 *
	 * @since 1.8
	 */
	static function pmpro_required_billing_fields( $fields ) {
		global $pmpro_required_billing_fields;
		$fields = $pmpro_required_billing_fields;

		unset( $fields['baddress1'] );
		unset( $fields['bcity'] );
		unset( $fields['bstate'] );
		unset( $fields['bzipcode'] );
		unset( $fields['bcountry'] );

		unset( $fields['CardType'] );
		unset( $fields['AccountNumber'] );
		unset( $fields['ExpirationMonth'] );
		unset( $fields['ExpirationYear'] );
		unset( $fields['CVV'] );

		if ( pmpro_getOption( 'knit_pay_hide_phone' ) ) {
			unset( $fields['bphone'] );
		}

		return $fields;
	}

	/**
	 * Instead of change membership levels, send users to Payment Gateway to pay.
	 *
	 * @param int          $user_id
	 * @param \MemberOrder $morder
	 *
	 * @since 1.8
	 */
	static function pmpro_checkout_before_change_membership_level( $user_id, $morder ) {
		global $wpdb, $discount_code_id, $knit_pay_redirect_url;

		// if no order, no need to pay
		if ( empty( $morder ) || empty( $knit_pay_redirect_url ) ) {
			return;
		}

		$morder->user_id = $user_id;
		$morder->saveOrder();

		// save discount code use
		if ( ! empty( $discount_code_id ) ) {
			$wpdb->query( "INSERT INTO $wpdb->pmpro_discount_codes_uses (code_id, user_id, order_id, timestamp) VALUES('" . $discount_code_id . "', '" . $user_id . "', '" . $morder->id . "', now())" );
		}

		do_action( 'pmpro_before_send_to_knit_pay', $user_id, $morder );
		do_action( 'pmpro_after_checkout', $user_id, $morder );

		wp_redirect( $knit_pay_redirect_url );
		exit();
	}

	/**
	 * Send Paramenters to payment gateway to generate the payment link
	 *
	 * @param \MemberOrder $morder
	 */
	private function sendToGateway( &$morder ) {
		// TODO add recuring option
		global $knit_pay_redirect_url;

		$config_id      = $this->config_id = pmpro_getOption( 'knit_pay_config_id' );
		$payment_method = $this->id;

		// Use default gateway if no configuration has been set.
		if ( empty( $config_id ) ) {
			$config_id = get_option( 'pronamic_pay_config_id' );
		}

		$gateway = Plugin::get_gateway( $config_id );

		if ( ! $gateway ) {
			return false;
		}

		// Data.
		// $data = new PaymentData( $morder );

		/**
		 * Build payment.
		 */
		$payment = new Payment();

		$payment->source    = 'paid-memberships-pro';
		$payment->source_id = $morder->id;
		$payment->order_id  = $morder->id;

		$payment->set_description( Helper::get_description( $payment_method, $morder ) );

		$payment->title = Helper::get_title( $morder->id );

		// Customer.
		$payment->set_customer( Helper::get_customer( $morder ) );

		// Address.
		$payment->set_billing_address( Helper::get_address( $morder ) );

		// Amount.
		$payment->set_total_amount( Helper::get_amount( $morder ) );

		// Method.
		$payment->set_payment_method( $payment_method );

		// Configuration.
		$payment->config_id = $config_id;

		try {
			$payment = Plugin::start_payment( $payment );

			$knit_pay_redirect_url = $payment->get_pay_redirect_url();
			// $morder->knit_pay_redirect_url = $payment->get_pay_redirect_url();
			return true;
		} catch ( \Exception $e ) {
			$morder->error = __( $e->getMessage(), 'knit-pay-lang' ) . '<br>' . __( Plugin::get_default_error_message(), 'knit-pay-lang' );
			return false;
		}
	}

	function process( &$order ) {
		if ( empty( $order->code ) ) {
			$order->code = $order->getRandomCode();
		}

		$title = pmpro_getOption( 'knit_pay_title' );
		if ( empty( $title ) ) {
			$title = 'Knit Pay';
		}

		if ( pmpro_getOption( 'knit_pay_hide_billing_address' ) ) {
			$order->billing->country = '';
		}

		// clean up a couple values
		$order->payment_type = $title;

		// just save, the user will go to Knit Pay to pay
		$order->status = 'pending';
		$order->saveOrder();

		return $order->Gateway->sendToGateway( $order );
	}

	function hide_checkout_fields() {
		$style = '';

		if ( pmpro_getOption( 'knit_pay_hide_billing_address' ) ) {
			$style .= '.pmpro_form_field.pmpro_form_field-baddress1,';
			$style .= '.pmpro_form_field.pmpro_form_field-baddress2,';
			$style .= '.pmpro_form_field.pmpro_form_field-bcity,';
			$style .= '.pmpro_form_field.pmpro_form_field-bstate,';
			$style .= '.pmpro_form_field.pmpro_form_field-bzipcode,';
			$style .= '.pmpro_form_field.pmpro_form_field-bcountry,';

			// TODO remove deprecated classes which were getting used in pmpro 2 after Jan 2026.
			$style .= '.pmpro_checkout-field-baddress1,';
			$style .= '.pmpro_checkout-field-baddress2,';
			$style .= '.pmpro_checkout-field-bcity,';
			$style .= '.pmpro_checkout-field-bstate,';
			$style .= '.pmpro_checkout-field-bzipcode,';
			$style .= '.pmpro_checkout-field-bcountry,';
		}

		if ( pmpro_getOption( 'knit_pay_hide_phone' ) ) {
			$style .= '.pmpro_form_field.pmpro_form_field-bphone,';

			// TODO remove deprecated classes which were getting used in pmpro 2 after Jan 2026.
			$style .= '.pmpro_checkout-field-bphone,';
		}

		$style = rtrim( $style, ',' );

		if ( ! empty( $style ) ) {
			$style = '<style>' . $style . '{display: none;}</style>';
		}

		echo $style;
	}
}
