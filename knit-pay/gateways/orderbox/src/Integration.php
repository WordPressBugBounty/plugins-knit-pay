<?php

namespace KnitPay\Gateways\OrderBox;

use Pronamic\WordPress\Pay\AbstractGatewayIntegration;
use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Core\PaymentMethods;
use Pronamic\WordPress\Pay\Core\Util as Core_Util;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;
use WP_Query;

/**
 * Title: Orderbox Integration
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 6.65.0.0
 * @since   6.65.0.0
 */
class Integration extends AbstractGatewayIntegration {
	/**
	 * Construct Orderbox integration.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'id'          => 'orderbox',
				'name'        => 'Order Box',
				'product_url' => '',
				'provider'    => 'orderbox',
			]
		);

		parent::__construct( $args );
	}

	/**
	 * Setup.
	 */
	public function setup() {
		\add_filter(
			'pronamic_gateway_configuration_display_value_' . $this->get_id(),
			[ $this, 'gateway_configuration_display_value' ],
			10,
			2
		);

		// Connect/Disconnect Listener.
		$function = [ __NAMESPACE__ . '\Integration', 'payment_request_listener' ];
		if ( ! has_action( 'wp_loaded', $function ) ) {
			add_action( 'wp_loaded', $function );
		}

		add_filter( 'pronamic_payment_redirect_url_' . self::get_id(), [ $this, 'redirect_url' ], 10, 2 );
	}
	
	/**
	 * Gateway configuration display value.
	 *
	 * @param string $display_value Display value.
	 * @param int    $post_id       Gateway configuration post ID.
	 * @return string
	 */
	public function gateway_configuration_display_value( $display_value, $post_id ) {
		$config = $this->get_config( $post_id );
		
		return $config->payment_type_id;
	}

	/**
	 * Get settings fields.
	 *
	 * @return array
	 */
	public function get_settings_fields() {
		$fields = [];

		// Payment Type ID.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_orderbox_payment_type_id',
			'title'    => __( 'Payment Type ID', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( 'This is the Id assigned to your Payment Gateway. You can see it in the manange Payment Gateway page in orderbox.', 'knit-pay-lang' ),
			'required' => true,
		];

		// Key.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_orderbox_key',
			'title'    => __( 'Order Box Key', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( 'Get your secure key from your Reseller Control panel.', 'knit-pay-lang' ),
			'required' => true,
		];

		// Configuration.
		$fields[] = [
			'section'  => 'general',
			'filter'   => FILTER_SANITIZE_NUMBER_INT,
			'meta_key' => '_pronamic_gateway_orderbox_config_id',
			'title'    => __( 'Configuration', 'knit-pay-lang' ),
			'type'     => 'select',
			'options'  => Plugin::get_config_select_options(),
			'default'  => get_option( 'pronamic_pay_config_id' ),
		];
		
		// Payment Methods.
		$active_payment_methods_options = [ '' => '-- All Methods --' ];
		foreach ( PaymentMethods::get_active_payment_methods() as $payment_method ) {
			$active_payment_methods_options[ $payment_method ] = PaymentMethods::get_name( $payment_method, ucwords( $payment_method ) );
		}
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_orderbox_payment_method',
			'title'    => __( 'Payment Method', 'knit-pay-lang' ),
			'type'     => 'select',
			'options'  => $active_payment_methods_options,
		];

		// Gateway URL.
		$fields[] = [
			'section'  => 'general',
			'title'    => \__( 'Gateway URL', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'large-text', 'code' ],
			'value'    => add_query_arg( 'kp_orderbox_payment_request', '', home_url( '/' ) ),
			'readonly' => true,
			'tooltip'  => sprintf(
				/* translators: %s: PayUmoney */
				__(
					'Copy the Gateway URL to the %s reseller dashboard while creating payment gateway configuration.',
					'knit-pay'
				),
				__( 'Orderbox', 'knit-pay-lang' )
			),
		];
		
		// Checksum Algorithm.
		$fields[] = [
			'section'  => 'general',
			'title'    => \__( 'Checksum Algorithm', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'large-text', 'code' ],
			'value'    => 'MD5',
			'readonly' => true,
		];

		// Gateway Currency.
		$fields[] = [
			'section'  => 'advanced',
			'meta_key' => '_pronamic_gateway_orderbox_gateway_currency',
			'title'    => __( 'Gateway Currency', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( '3 Character Currency Code. (eg. USD) ', 'knit-pay-lang' ),
		];

		// Exchange Rate.
		$fields[] = [
			'section'  => 'advanced',
			'filter'   => FILTER_VALIDATE_FLOAT,
			'meta_key' => '_pronamic_gateway_orderbox_exchange_rate',
			'title'    => __( 'Exchange Rate', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( 'Exchange Rate of Payment Currency.', 'knit-pay-lang' ),
			'default'  => 1,
		];

		// Transaction Fees Percentage.
		$fields[] = [
			'section'     => 'advanced',
			'meta_key'    => '_pronamic_gateway_orderbox_transaction_fees_percentage',
			'title'       => __( 'Transaction Fees Percentage', 'knit-pay-lang' ),
			'type'        => 'text',
			'classes'     => [ 'regular-text', 'code' ],
			'description' => __( 'Percentage of transaction fees you want to collect from the customer. For example: 2.36 for 2% + GST; 3.54 for 3% + GST. Keep it blank for not collecting transaction fees from the customer.', 'knit-pay-lang' ),
		];

		// Transaction Fees Fix Amount.
		$fields[] = [
			'section'     => 'advanced',
			'meta_key'    => '_pronamic_gateway_orderbox_transaction_fees_fix',
			'title'       => __( 'Transaction Fees Fix Amount', 'knit-pay-lang' ),
			'type'        => 'text',
			'classes'     => [ 'regular-text', 'code' ],
			'description' => __( 'Fix amount of transaction fees you want to collect from the customer. For example, 5 for adding 5 in the final amount. Keep it blank for not collecting fixed transaction fees from the customer.', 'knit-pay-lang' ),
		];

		// Return fields.
		return $fields;
	}

	public function get_config( $post_id ) {
		$config = new Config();

		$config->key                         = $this->get_meta( $post_id, 'orderbox_key' );
		$config->config_id                   = $this->get_meta( $post_id, 'orderbox_config_id' );
		$config->payment_method              = $this->get_meta( $post_id, 'orderbox_payment_method' );
		$config->payment_type_id             = $this->get_meta( $post_id, 'orderbox_payment_type_id' );
		$config->gateway_currency            = $this->get_meta( $post_id, 'orderbox_gateway_currency' );
		$config->exchange_rate               = $this->get_meta( $post_id, 'orderbox_exchange_rate' );
		$config->transaction_fees_percentage = $this->get_meta( $post_id, 'orderbox_transaction_fees_percentage' );
		$config->transaction_fees_fix        = $this->get_meta( $post_id, 'orderbox_transaction_fees_fix' );

		if ( empty( $config->exchange_rate ) ) {
			$config->exchange_rate = 1;
		}

		if ( empty( $config->transaction_fees_percentage ) ) {
			$config->transaction_fees_percentage = 0;
		}

		if ( empty( $config->transaction_fees_fix ) ) {
			$config->transaction_fees_fix = 0;
		}

		return $config;
	}

	/**
	 * Get gateway.
	 *
	 * @param int $post_id Post ID.
	 * @return Gateway
	 */
	public function get_gateway( $config_id ) {
		return new Gateway();
	}

	public static function payment_request_listener() {
		if ( ! ( filter_has_var( INPUT_GET, 'paymenttypeid' ) && filter_has_var( INPUT_GET, 'transactiontype' ) ) ) {
			return;
		}

		// Don't cache.
		Core_Util::no_cache();

		// This filter removes data that is potentially harmful for your application. It is used to strip tags and remove or encode unwanted characters.
		$_GET = filter_var_array( $_GET, FILTER_SANITIZE_STRING );

		// Find Gateway Configuration for provided payment type id.
		$query = new WP_Query(
			[
				'post_type'  => 'pronamic_gateway',
				'fields'     => 'ids',
				'nopaging'   => true,
				'meta_query' => [
					[
						'key'   => '_pronamic_gateway_orderbox_payment_type_id',
						'value' => $_GET['paymenttypeid'],
					],
				],
			]
		);
		if ( empty( $query->post_count ) ) {
			echo 'Gateway Configuration not found for provided payment type id.';
			exit;
		}
		if ( 1 < $query->post_count ) {
			echo 'More than 1 Gateway Configurations found for provided payment type id.';
			exit;
		}

		$config_id = reset( $query->posts );

		$integration = new Integration();
		$config      = $integration->get_config( $config_id );

		$integration->init_payment( $_GET, $config, $config_id );
	}

	/**
	 * Initialize Payment.
	 *
	 * @param array  $payment_data Payment Data from Order Box.
	 * @param Config $config
	 * @param int    $parent_config_id
	 *
	 * @return void
	 */
	private function init_payment( $payment_data, $config, $parent_config_id ) {
		unset( $payment_data['kp_orderbox_payment_request'] );
		
		$payment_type_id  = $payment_data['paymenttypeid'];
		$trans_id         = $payment_data['transid'];
		$user_id          = $payment_data['userid'];
		$user_type        = $payment_data['usertype'];
		$transaction_type = $payment_data['transactiontype'];

		$invoice_ids    = $payment_data['invoiceids'];
		$debit_note_ids = $payment_data['debitnoteids'];

		$description = $payment_data['description'];

		$selling_currency_amount    = $payment_data['sellingcurrencyamount'];
		$accounting_currency_amount = $payment_data['accountingcurrencyamount'];

		$checksum = $payment_data['checksum'];

		if ( ! Checksum::verifyChecksum( $payment_type_id, $trans_id, $user_id, $user_type, $transaction_type, $invoice_ids, $debit_note_ids, $description, $selling_currency_amount, $accounting_currency_amount, $config->key, $checksum ) ) {
			echo 'Checksum mismatch !';
			exit;
		}

		$payment_method = $config->payment_method;

		// Initiating Payment.
		$child_config_id = $config->config_id;

		// Use default gateway if no configuration has been set.
		if ( empty( $child_config_id ) ) {
			$child_config_id = get_option( 'pronamic_pay_config_id' );
		}

		$gateway = Plugin::get_gateway( $child_config_id );

		if ( ! $gateway ) {
			echo 'Gateway configuration not found.';
			exit;
		}

		/**
		 * Build payment.
		 */
		$payment = new Payment();

		$payment->source    = 'orderbox';
		$payment->source_id = $trans_id;
		$payment->order_id  = $trans_id;

		$payment->set_description( $trans_id );

		$payment->title = $trans_id;

		// Customer.
		$customer = Helper::get_customer( $payment_data );
		$customer->set_locale( \get_locale() );
		$payment->set_customer( $customer );

		// Address.
		$payment->set_billing_address( Helper::get_address( $payment_data ) );

		// Amount.
		$payment->set_total_amount( Helper::get_amount( $payment_data, $config ) );

		// Method.
		$payment->set_payment_method( $payment_method );

		// Configuration.
		$payment->config_id = $child_config_id;

		try {
			$payment = Plugin::start_payment( $payment );

			$payment->set_meta( 'orderbox_payment_data', $payment_data );
			$payment->set_meta( 'knit_pay_parent_config_id', $parent_config_id );

			$payment->save();

			echo '<meta http-equiv="refresh" content="0; URL=' . $payment->get_pay_redirect_url() . '" />';
			echo '<div style="display: flex;justify-content: center;align-items: center;height: 100vh;">Please wait while the browser redirects you.</div>';
			exit;
		} catch ( \Exception $e ) {
			echo $e->getMessage();
			exit();
		}
	}

	/**
	 * Payment redirect URL filter.
	 *
	 * @param string  $url     Redirect URL.
	 * @param Payment $payment Payment.
	 *
	 * @return void
	 */
	public static function redirect_url( $url, $payment ) {
		$integration = new Integration();
		$config      = $integration->get_config( $payment->get_meta( 'knit_pay_parent_config_id' ) );

		$key          = $config->key;
		$payment_data = $payment->get_meta( 'orderbox_payment_data' );
		
		if ( ! $payment_data ) {
			return $url;
		}

		$redirect_url               = $payment_data->redirecturl;
		$trans_id                   = $payment_data->transid;
		$selling_currency_amount    = $payment_data->sellingcurrencyamount;
		$accounting_currency_amount = $payment_data->accountingcurrencyamount;

		switch ( $payment->get_status() ) {
			case PaymentStatus::CANCELLED:
			case PaymentStatus::EXPIRED:
			case PaymentStatus::FAILURE:
				$status = 'N';
				break;

			case PaymentStatus::SUCCESS:
				$status = 'Y';
				break;

			case PaymentStatus::AUTHORIZED:
			case PaymentStatus::OPEN:
			default:
				$status = 'P';
		}

		$rkey     = wp_rand( 1000000, 9999999 );
		$checksum = Checksum::generateChecksum( $trans_id, $selling_currency_amount, $accounting_currency_amount, $status, $rkey, $key );

		return add_query_arg(
			[
				'transid'          => $trans_id,
				'status'           => $status,
				'rkey'             => $rkey,
				'checksum'         => $checksum,
				'sellingamount'    => $selling_currency_amount,
				'accountingamount' => $accounting_currency_amount,
			],
			$redirect_url 
		);
	}
}
