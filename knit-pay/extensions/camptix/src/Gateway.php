<?php

namespace KnitPay\Extensions\Camptix;

use Pronamic\WordPress\Html\Element;
use Pronamic\WordPress\Money\Currency;
use Pronamic\WordPress\Money\Money;
use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Util;
use Pronamic\WordPress\Pay\Core\PaymentMethods;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Refunds\Refund;
use CampTix_Addon;
use CampTix_Payment_Method;
use CampTix_Plugin;

/**
 * Title: CampTix Gateway
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   8.74.0.0
 */

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit();

class Gateway extends CampTix_Payment_Method {
	/**
	 * The following variables are required for every payment method.
	 */
	public $id                   = '';
	public $name                 = 'Pay Online';
	public $description          = ' ';
	public $supported_currencies = [
		'AED',
		'AFN',
		'ALL',
		'AMD',
		'ANG',
		'AOA',
		'ARS',
		'AUD',
		'AWG',
		'AZN',
		'BAM',
		'BBD',
		'BDT',
		'BGN',
		'BMD',
		'BND',
		'BOB',
		'BRL',
		'BSD',
		'BWP',
		'BZD',
		'CAD',
		'CDF',
		'CHF',
		'CNY',
		'COP',
		'CRC',
		'CVE',
		'CZK',
		'DKK',
		'DOP',
		'DZD',
		'EGP',
		'ETB',
		'EUR',
		'FJD',
		'FKP',
		'GBP',
		'GEL',
		'GIP',
		'GMD',
		'GTQ',
		'GYD',
		'HKD',
		'HNL',
		'HRK',
		'HTG',
		'HUF',
		'IDR',
		'ILS',
		'INR',
		'ISK',
		'JMD',
		'KES',
		'KGS',
		'KHR',
		'KYD',
		'KZT',
		'LAK',
		'LBP',
		'LKR',
		'LRD',
		'LSL',
		'MAD',
		'MDL',
		'MKD',
		'MMK',
		'MNT',
		'MOP',
		'MRO',
		'MUR',
		'MVR',
		'MWK',
		'MXN',
		'MYR',
		'MZN',
		'NAD',
		'NGN',
		'NIO',
		'NOK',
		'NPR',
		'NZD',
		'PAB',
		'PEN',
		'PGK',
		'PHP',
		'PKR',
		'PLN',
		'QAR',
		'RON',
		'RSD',
		'RUB',
		'SAR',
		'SBD',
		'SCR',
		'SEK',
		'SGD',
		'SHP',
		'SLL',
		'SOS',
		'SRD',
		'STD',
		'SZL',
		'THB',
		'TJS',
		'TOP',
		'TRY',
		'TTD',
		'TWD',
		'TZS',
		'UAH',
		'USD',
		'UYU',
		'UZS',
		'WST',
		'XCD',
		'YER',
		'ZAR',
		'ZMW',
		// Zero decimal currencies (https://stripe.com/docs/currencies#zero-decimal)
		'BIF',
		'CLP',
		'DJF',
		'GNF',
		'JPY',
		'KMF',
		'KRW',
		'MGA',
		'PYG',
		'RWF',
		'UGX',
		'VND',
		'VUV',
		'XAF',
		'XOF',
		'XPF',
	];
	
	public $supported_features = [
		'refund-single' => true,
		'refund-all'    => true,
	];
	
	/**
	 * We can have an array to store our options.
	 * Use $this->get_payment_options() to retrieve them.
	 */
	protected $options = [];
	
	public function __construct( $id, $name ) {
		$this->id          = $id;
		$this->name        = $name;
		$this->description = __( 'Pay via ', 'knit-pay-lang' ) . $name;
		parent::__construct();
	}
	
	/**
	 * Runs during camptix_init, loads our options and sets some actions.
	 *
	 * @see CampTix_Addon
	 */
	function camptix_init() {
		$this->options = array_merge(
			[
				'title'               => '',
				'description'         => '',
				'config_id'           => '',
				'payment_description' => '',
			],
			$this->get_payment_options() 
		);

		// Don't change payment method name for admin interface.
		if ( ! is_admin() ) {
			if ( ! empty( $this->options['title'] ) ) {
				$this->name = $this->options['title'];
			}
			
			if ( ! empty( $this->options['description'] ) ) {
				$this->description = $this->options['description'];
			}
		} else {
			$this->name = __( 'Knit Pay - ', 'knit-pay-lang' ) . $this->name;
		}
	}
	
	/**
	 * Add payment settings fields
	 *
	 * This runs during settings field registration in CampTix for the
	 * payment methods configuration screen. If your payment method has
	 * options, this method is the place to add them to. You can use the
	 * helper function to add typical settings fields. Don't forget to
	 * validate them all in validate_options.
	 */
	function payment_settings_fields() {
		// Title.
		$description = sprintf(
			__( 'It will be displayed on checkout page for payment method %s', 'knit-pay-lang' ),
			$this->id
		);
		$this->add_settings_field_helper( 'title', __( 'Title - ', 'knit-pay-lang' ) . PaymentMethods::get_name( $this->id ), [ $this, 'input_field' ], $description );


		// Description.
		$description = sprintf(
			/* translators: %s: payment method title */
			__( 'Give the customer instructions for paying via %s.', 'knit-pay-lang' ),
			$this->name
		);
		$this->add_settings_field_helper( 'description', __( 'Description', 'knit-pay-lang' ), [ $this, 'input_field' ], $description );


		// Configuration.
		$description = __( 'Configurations can be created in Knit Pay gateway configurations page at <a href="' . admin_url() . 'edit.php?post_type=pronamic_gateway">"Knit Pay >> Configurations"</a>.', 'knit-pay-lang' ) . '<br>' . __( 'Visit the "Knit Pay >> Settings" page to set Default Gateway Configuration.', 'knit-pay-lang' );
		$this->add_settings_field_helper( 'config_id', __( 'Configuration', 'knit-pay-lang' ), [ $this, 'select_configuration' ], $description );


		// Payment Description.
		$description = sprintf(
			'%s<br />%s',
			/* translators: %s: default code */
			sprintf( __( 'Default: <code>%s</code>', 'knit-pay-lang' ), __( 'Ticket Booking {attendee_id}', 'knit-pay-lang' ) ),
			/* translators: %s: tags */
			sprintf( __( 'Tags: %s', 'knit-pay-lang' ), sprintf( '<code>%s</code> <code>%s</code> <code>%s</code> <code>%s</code>', '{attendee_id}', '{event_name}', '{first_ticket_name}', '{ticket_names}' ) )
		);
		$this->add_settings_field_helper( 'payment_description', __( 'Payment Description', 'knit-pay-lang' ), [ $this, 'input_field' ], $description );
	}
	
	/**
	 * Input Field.
	 *
	 * @param array $args Arguments.
	 * @return void
	 */
	public function input_field( $args ) {
		$args['id']        = $args['name'];
		$args['label_for'] = $args['name'];
		$args['class']     = 'regular-text';
		
		$element = new Element( 'input', $args );
		$element->output();
		
		if ( isset( $args['description'] ) ) {
			printf(
				'<p class="pronamic-pay-description description">%s</p>',
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$args['description']
			);
		}
	}
	
	/**
	 * Show Configuration Select Field
	 *
	 * @param array $args
	 */
	function select_configuration( $args ) {
		$args['id']        = $args['name'];
		$args['label_for'] = $args['name'];
		$args['class']     = 'regular-text';
		
		$configurations    = Plugin::get_config_select_options();
		$configurations[0] = __( '— Default Gateway —', 'knit-pay-lang' );
		
		$configuration_options              = [];
		$configuration_options[]['options'] = $configurations;

		printf(
			'<select %s>%s</select>',
            // @codingStandardsIgnoreStart
            Util::array_to_html_attributes($args),
            Util::select_options_grouped( $configuration_options, $this->options['config_id'] )
            // @codingStandardsIgnoreEnd
		);
		
		if ( isset( $args['description'] ) ) {
			printf(
				'<p class="pronamic-pay-description description">%s</p>',
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$args['description']
			);
		}
	}

	/**
	 * Validate options
	 *
	 * @param array $input
	 *
	 * @return array
	 */
	function validate_options( $input ) {
		$output = $this->options;

		if ( isset( $input['title'] ) ) {
			$output['title'] = $input['title'];
		}
		
		if ( isset( $input['description'] ) ) {
			$output['description'] = $input['description'];
		}

		if ( isset( $input['config_id'] ) ) {
			$output['config_id'] = $input['config_id'];
		}

		if ( isset( $input['payment_description'] ) ) {
			$output['payment_description'] = $input['payment_description'];
		}

		return $output;
	}

	/**
	 * Process a checkout request
	 *
	 * @param string $payment_token
	 *
	 * @return int One of the CampTix_Plugin::PAYMENT_STATUS_{status} constants
	 */
	function payment_checkout( $payment_token ) {
		/** @var CampTix_Plugin $camptix */
		global $camptix;
		
		$config_id      = $this->options['config_id'];
		$payment_method = $this->id;
		
		// Use default gateway if no configuration has been set.
		if ( empty( $config_id ) ) {
			$config_id = get_option( 'pronamic_pay_config_id' );
		}
		
		$gateway = Plugin::get_gateway( $config_id );
		
		if ( ! $gateway ) {
			return CampTix_Plugin::PAYMENT_STATUS_FAILED;
		}
		
		$order           = $this->get_order( $payment_token );        
		$attendee_id     = $order['attendee_id'];
		$attendee_detail = get_post_meta( $attendee_id );
		
		/**
		 * Build payment.
		 */
		$payment = new Payment();
		
		$payment->source    = 'camptix';
		$payment->source_id = $attendee_id;
		$payment->order_id  = $attendee_id;
		
		$payment->set_description( Helper::get_description( $this, $order ) );
		
		$payment->title = Helper::get_title( $order['attendee_id'] );
		
		// Customer.
		$payment->set_customer( Helper::get_customer_from_attendee_detail( $attendee_detail ) );
		
		// Address.
		$payment->set_billing_address( Helper::get_address_from_attendee_detail( $order ) );
		
		// Currency.
		$currency = Currency::get_instance( $this->camptix_options['currency'] );
		
		// Amount.
		$payment->set_total_amount( new Money( $order['total'], $currency ) );
		
		// Method.
		$payment->set_payment_method( $payment_method );
		
		// Configuration.
		$payment->config_id = $config_id;
		
		try {           
			$payment = Plugin::start_payment( $payment );
			
			$payment->set_meta( 'camptix_payment_token', $payment_token );
			$payment->save();
			
			// Execute a redirect.
			wp_redirect( $payment->get_pay_redirect_url() );
			exit;
		} catch ( \Exception $e ) {
		    $camptix->error( esc_html($e->getMessage()) );
			
			return $camptix->payment_result(
				$payment_token,
				CampTix_Plugin::PAYMENT_STATUS_FAILED,
				[
					'transaction_id' => $payment->get_id(),
				] 
			);
		}
	}
	
	/**
	 * Submits a single, user-initiated refund request to Stripe and returns the result.
	 *
	 * @param string $payment_token
	 *
	 * @return int One of the CampTix_Plugin::PAYMENT_STATUS_{status} constants
	 */
	public function payment_refund( $payment_token ) {
		/** @var CampTix_Plugin $camptix */
		global $camptix;
		
		$result = $this->send_refund_request( $payment_token );
		
		if ( CampTix_Plugin::PAYMENT_STATUS_REFUND_FAILED === $result['status'] ) {         
			$camptix->error(
				sprintf(
					__( 'Refund Error: %s', 'knit-pay-lang' ),
					esc_html( $result['refund_transaction_details']['error_message'] )
				) 
			);
		}
		
		return $camptix->payment_result( $payment_token, $result['status'], $result );
	}

	/*
	 * Sends a request to Gateway to refund a transaction
	 *
	 * @param string $payment_token
	 *
	 * @return array
	 */
	function send_refund_request( $payment_token ) {
		$order = $this->get_order( $payment_token );
		
		/** @var $camptix CampTix_Plugin */
		global $camptix;
		
		// Check gateway.
		$gateway = Plugin::get_gateway( $this->options['config_id'] );
		
		if ( null === $gateway ) {
			return new \WP_Error(
				'knit-pay-camptix-refund-gateway',
				__( 'Unable to process refund as gateway configuration does not exist.', 'knit-pay-lang' )
			);
		}
		
		$transaction_id = $camptix->get_post_meta_from_payment_token( $payment_token, 'tix_transaction_id' );
		$reason         = filter_input( INPUT_POST, 'tix_refund_request_reason', FILTER_SANITIZE_STRING );
		
		$result = [
			'status'                     => CampTix_Plugin::PAYMENT_STATUS_REFUND_FAILED,
			'transaction_id'             => $transaction_id,
			'refund_transaction_id'      => '',
			'refund_transaction_details' => '',
		];

		// Check payment.
		$payment = \get_pronamic_payment_by_transaction_id( $transaction_id );

		if ( null === $payment ) {
			return;
		}

		try {
			$refund = new Refund( $payment, $payment->get_total_amount() );
			$refund->set_description( $reason );

			Plugin::create_refund( $refund );

			$result['status']                = CampTix_Plugin::PAYMENT_STATUS_REFUNDED;
			$result['refund_transaction_id'] = $refund->psp_id;
		} catch ( \Exception $e ) {
			$result['refund_transaction_id']      = false;
			$result['refund_transaction_details'] = [
				'error_message' => $e->getMessage(),
			];
			$result['status']                     = CampTix_Plugin::PAYMENT_STATUS_REFUND_FAILED;
		}
		
		return $result;
	}
}
