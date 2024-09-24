<?php
namespace KnitPay\Gateways\UpiQR;

use KnitPay\Gateways\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Core\PaymentMethod;
use Pronamic\WordPress\Pay\Core\Util as Core_Util;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;
use Exception;

/**
 * Title: UPI QR Gateway
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 1.0.0
 * @since 4.1.0
 */
class Gateway extends Core_Gateway {
	protected $config;
	private $intent_url_parameters;
	protected $payment_expiry_seconds = 300;

	/**
	 * Constructs and initializes an UPI QR gateway
	 *
	 * @param Config $config
	 *            Config.
	 */
	public function __construct( Config $config ) {
		parent::__construct( $config );
		
		$this->config = $config;

		$this->set_method( self::METHOD_HTML_FORM );

		$this->payment_page_title = 'Payment Page';
		
		if ( wp_is_mobile() ) {
			$this->payment_page_description = $config->mobile_payment_instruction;
		} else {
			$this->payment_page_description = $config->payment_instruction;
		}

		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

		$this->register_payment_methods();
	}

	private function register_payment_methods() {
		$this->register_payment_method( new PaymentMethod( PaymentMethods::UPI ) );
	}

	/**
	 * Start.
	 *
	 * @see Core_Gateway::start()
	 *
	 * @param Payment $payment
	 *            Payment.
	 */
	public function start( Payment $payment ) {
		$payment_currency = $payment->get_total_amount()->get_currency()->get_alphabetic_code();
		if ( isset( $payment_currency ) && 'INR' !== $payment_currency ) {
			$currency_error = 'UPI only accepts payments in Indian Rupees. If you are a store owner, kindly activate INR currency for ' . $payment->get_source() . ' plugin.';
			throw new Exception( $currency_error );
		}

		if ( 100 > $payment->get_total_amount()->get_minor_units()->format( 0, '.', '' ) ) {
			$mobile_error = 'The amount should be at least ₹1.';
			throw new Exception( $mobile_error );
		}

		if ( $this->config->hide_mobile_qr && $this->config->hide_pay_button ) {
			$mobile_error = "QR code and Payment Button can't be hidden at the same time. Kindly show at least one of them from the configuration page.";
			throw new Exception( $mobile_error );
		}

		$payment->set_transaction_id( $payment->key . '_' . $payment->get_id() );

		$payment->set_action_url( $payment->get_pay_redirect_url() );
	}

	/**
	 * Output form.
	 *
	 * @param Payment $payment Payment.
	 * @return void
	 * @throws \Exception When payment action URL is empty.
	 */
	public function output_form( Payment $payment ) {
		$hide_pay_button = $this->config->hide_pay_button;

		if ( ! wp_is_mobile() ) {
			$hide_pay_button = true;
		}

		\wp_enqueue_script( 'jquery' );
		\wp_enqueue_script( 'knit-pay-easy-qrcode' );

		$data    = $this->get_output_fields( $payment );
		$pay_uri = add_query_arg( $data, 'upi://pay' );
		
		$form_inner = '';

		$html = wp_head() . '<hr>';
		
		// Show Pay Button after delay.
		$html .= '<script type="text/javascript">
                    // Get time after 30 seconds
                    var countDownDate = new Date().getTime() + 30000;
    		    
                    // Update the count down every 1 second
                    var x = setInterval(function() {
    		    
                          // Get today\'s date and time
                          var now = new Date().getTime();
    		    
                          // Find the distance between now and the count down date
                          var distance = countDownDate - now;
    		    
                          // Time calculations for seconds
                          var seconds = Math.ceil((distance % (1000 * 60)) / 1000);
    		    
                          // Output the result in an element with id="timmer"
                          document.getElementById("timmer").innerHTML = seconds + " sec";
    		    
                          // If the count down is over, write some text
                          if (distance < 0) {
                            clearInterval(x);
                            document.getElementById("transaction-details").removeAttribute("style");
                            document.getElementById("delay-info").remove();
                          }
                    }, 1000);
                </script>';
		
		// Show QR Code.
		if ( ! ( wp_is_mobile() && $this->config->hide_mobile_qr ) ) {
			$html .= '<div><strong>Scan the QR Code</strong></div><div class="qrcode"></div>';
			$html .= "<script type='text/javascript'>
                        jQuery(document).ready(function() {
                            new QRCode(document.querySelector('.qrcode'), {
                            		text: '$pay_uri',
                            		width: 250, //default 128
                            		height: 250,
                            		colorDark: '#000000',
                            		colorLight: '#ffffff',
                            		correctLevel: QRCode.CorrectLevel.H
                            	});
                            
                        });
                      </script>";
		}
		
		if ( ! ( $hide_pay_button || $this->config->hide_mobile_qr ) ) {
			$html .= '<p>or</p>';
		}

		if ( ! $hide_pay_button ) {
			$html .= '<a class="pronamic-pay-btn" href="' . $pay_uri . '" style="font-size: 15px;">Click here to make the payment</a>';
		}

		$html .= '<hr>';

		$form_inner .= '<span id="transaction-details" style="display: none;"><br><br>';

		// Transaction ID Filed.
		if ( Integration::HIDE_FIELD !== $this->config->transaction_id_field ) {
			$form_inner .= '<label for="transaction_id">Transaction ID:</label>
                <input type="text" id="transaction_id" name="transaction_id" ';
			if ( Integration::SHOW_REQUIRED_FIELD === $this->config->transaction_id_field ) {
				$form_inner .= 'required';
			}
			$form_inner .= ' ><br><br>';
		}

		// Submit Button.
		$form_inner .= sprintf(
			'<input class="pronamic-pay-btn" type="submit" name="pay-status" value="%s" />',
			__( 'Submit', 'knit-pay-lang' )
		);
		$form_inner .= '&nbsp;&nbsp;</span>';
		$form_inner .= "<div id='delay-info'>The \"Submit\" button will be visible after <span id='timmer'>30 sec</span>.<br><br></div>";
		
		// Cancel Button.
		$form_inner .= sprintf(
			'<input id = "cancel-button" class="pronamic-pay-btn" type="submit" name="pay-status" formnovalidate value="%s" />',
			__( 'Cancel', 'knit-pay-lang' )
		);

		$html .= sprintf(
			'<form id="pronamic_ideal_form" name="pronamic_ideal_form" method="post" action="%s">%s</form>',
			esc_attr( $payment->get_return_url() ),
			$form_inner
		);
		$html .= wp_footer();

		echo $html;
	}

	/**
	 * Get output inputs.
	 *
	 * @see Core_Gateway::get_output_fields()
	 *
	 * @param Payment $payment
	 *            Payment.
	 *
	 * @return array
	 */
	public function get_output_fields( Payment $payment ) {
		$vpa        = $this->config->vpa;
		$payee_name = $this->config->payee_name;
		if ( empty( $payee_name ) ) {
			$payee_name = get_bloginfo();
		}
		$payee_name = rawurlencode( $payee_name );
		if ( empty( $payee_name ) ) {
			throw new \Exception( 'The Payee Name is blank. Kindly set it from the UPI QR Configuration page.' );
		}
		if ( empty( $vpa ) ) {
			throw new \Exception( 'UPI ID is blank. Kindly set it from the UPI QR Configuration page.' );
		}

		// @see https://developers.google.com/pay/india/api/web/create-payment-method
		$data['pa'] = $vpa;
		$data['pn'] = $payee_name;
		if ( ! empty( $this->config->merchant_category_code ) ) {
			$data['mc'] = $this->config->merchant_category_code;
		}
		$data['tr'] = $payment->get_transaction_id();
		// $data['url'] = ''; // Invoice/order details URL
		$data['am'] = $payment->get_total_amount()->number_format( null, '.', '' );
		$data['cu'] = $payment->get_total_amount()->get_currency()->get_alphabetic_code();

		// $data['tid'] = $payment->get_transaction_id();
		$data['tn'] = rawurlencode( substr( trim( $payment->get_description() ), 0, 75 ) );

		return $data;
	}

	public function knit_pay_upi_data( $payment ) {
		return [
			'knitpay_payment_id'  => $payment->get_id(),
			'mode'                => $payment->get_mode(),
			'gateway'             => \get_post_meta( $payment->get_config_id(), '_pronamic_gateway_id', true ),
			'payment_method'      => $payment->get_payment_method(),
			'source'              => $payment->get_source(),
			'amount'              => $payment->get_total_amount()->number_format( null, '.', '' ),
			'currency'            => $payment->get_total_amount()->get_currency()->get_alphabetic_code(),
			'knitpay_version'     => KNITPAY_VERSION,
			'knitpay_upi_version' => defined( 'KNITPAY_UPI_VERSION' ) ? KNITPAY_UPI_VERSION : '',
			'php_version'         => PHP_VERSION,
			'website_url'         => home_url( '/' ),
			'data'                => [
				'payment_template' => $this->config->payment_template,
			],
		];
	}

	/**
	 * Update status of the specified payment.
	 *
	 * @param Payment $payment
	 *            Payment.
	 */
	public function update_status( Payment $payment ) {
		$transaction_id = filter_has_var( INPUT_POST, 'transaction_id' ) ? sanitize_text_field( $_POST['transaction_id'] ) : null;
		$pay_status     = filter_has_var( INPUT_POST, 'pay-status' ) ? sanitize_text_field( $_POST['pay-status'] ) : null;

		if ( $pay_status === 'Cancel' ) {
			$payment->add_note( 'Payment Cancelled.' );
			$payment->set_status( PaymentStatus::CANCELLED );
			return;
		}

		if ( empty( $transaction_id ) && Integration::SHOW_REQUIRED_FIELD === $this->config->transaction_id_field ) {
			$payment->add_note( 'Transaction ID not provided.' );
			$payment->set_status( PaymentStatus::FAILURE );
			return;
		}

		$payment->set_transaction_id( $transaction_id );

		$payment->set_status( $this->config->payment_success_status );
	}

	public function expire_old_upi_payment( Payment $payment ) {
		// Make payment status as expired for payment older than 5 min.
		if ( $this->payment_expiry_seconds < time() - $payment->get_date()->getTimestamp() ) {
			$payment->set_status( PaymentStatus::EXPIRED );
		}
	}

	/**
	 * Redirect via HTML.
	 *
	 * @param Payment $payment The payment to redirect for.
	 * @return void
	 */
	public function redirect_via_html( Payment $payment ) {
		// Check current payment status before proceeding.
		if ( $this->supports( 'payment_status_request' ) ) {
			try {
				$this->update_status( $payment );
			} catch ( Exception $e ) {
				echo $e->getMessage();
				exit;
			}
		}

		if ( PaymentStatus::OPEN !== $payment->get_status() ) {
			wp_safe_redirect( $payment->get_return_redirect_url() );
			exit;
		}

		if ( ! $this->supports( 'payment_status_request' ) ) {
			parent::redirect_via_html( $payment );
		}

		\wp_register_style(
			"knit-pay-upi-qr-template-{$this->config->payment_template}",
			KNITPAY_URL . "/gateways/upi-qr/src/css/template{$this->config->payment_template}.css",
			[],
			KNITPAY_VERSION
		);

		\wp_enqueue_script( "knit-pay-upi-qr-template-{$this->config->payment_template}" );

		define( 'KNIT_PAY_UPI_QR_IMAGE_URL', plugins_url( 'images/', __FILE__ ) );

		if ( headers_sent() ) {
			parent::redirect_via_html( $payment );
		} else {
			Core_Util::no_cache();

			include 'view/payment-page.php';
			// include "view/template{$this->config->payment_template}.php";
		}

		exit;
	}

	public function get_intent_url_parameters( $payment ) {
		if ( isset( $this->intent_url_parameters ) ) {
			return $this->intent_url_parameters;
		}
		try {
			$this->intent_url_parameters = $this->get_output_fields( $payment );
		} catch ( Exception $e ) {
			echo $e->getMessage();
			exit;
		}
		return $this->intent_url_parameters;
	}

	public function get_upi_qr_text( $payment ) {
		return add_query_arg( $this->get_intent_url_parameters( $payment ), 'upi://pay' );
	}

	public function enqueue_scripts() {
		\wp_register_script(
			'knit-pay-easy-qrcode',
			KNITPAY_URL . '/gateways/upi-qr/src/js/easy.qrcode.min.js',
			[],
			'4.6.0',
			true
		);

		\wp_register_script(
			'knit-pay-countdown-timer',
			KNITPAY_URL . '/gateways/upi-qr/src/js/countdown-timer.js',
			[]
		);

		\wp_register_script(
			'knit-pay-sweet-alert-2',
			'https://cdn.jsdelivr.net/npm/sweetalert2@11',
			[ 'jquery' ],
		);

		\wp_register_script(
			"knit-pay-upi-qr-template-{$this->config->payment_template}",
			KNITPAY_URL . "/gateways/upi-qr/src/js/template{$this->config->payment_template}.js",
			[ 'jquery', 'knit-pay-sweet-alert-2', 'knit-pay-countdown-timer', 'knit-pay-easy-qrcode' ],
			KNITPAY_VERSION
		);

		wp_localize_script(
			"knit-pay-upi-qr-template-{$this->config->payment_template}",
			'knit_pay_upi_qr_vars',
			[
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
			]
		);
	}
}
