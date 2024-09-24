<?php

namespace KnitPay\Extensions\ContactForm7;

use Pronamic\WordPress\Pay\AbstractPluginIntegration;
use Pronamic\WordPress\Pay\Payments\Payment;

/**
 * Title: Contact Form 7 extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   5.60.0.0
 */
class Extension extends AbstractPluginIntegration {
	/**
	 * Slug
	 *
	 * @var string
	 */
	const SLUG = 'contact-form-7';

	/**
	 * Constructs and initialize Contact Form 7 extension.
	 */
	public function __construct() {
		parent::__construct(
			[
				'name' => __( 'Contact Form 7', 'knit-pay-lang' ),
			]
		);

		// Dependencies.
		$dependencies = $this->get_dependencies();

		$dependencies->add( new ContactForm7Dependency() );
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

		add_action( 'pronamic_payment_status_update_' . self::SLUG, [ $this, 'status_update' ], 10 );
		
		\add_action( 'wpcf7_redirect_enqueue_frontend', [ $this, 'enqueue_scripts' ] );
		\add_action( 'wpcf_7_redirect_admin_scripts', [ $this, 'enqueue_admin_scripts' ] );
				
		if ( ! class_exists( 'WPCF7R_Action' ) ) {
			add_filter( 'wpcf7_editor_panels', [ $this, 'missing_dependency_panel' ] );
			return;
		}
		
		require_once 'WPCF7R_Action_Knit_Pay.php';
		
		register_wpcf7r_actions(
			'knit_pay',
			__( 'Knit Pay Payment', 'knit-pay-lang' ),
			'WPCF7R_Action_Knit_Pay'
		);
				
		// FIXME refer: https://wordpress.org/support/topic/paypal-integration-addon-not-working-without-ajax/
	}
	
	public function missing_dependency_panel( $panels ) {
		
		$panels['knit_pay'] = [
			'title'    => __( 'Knit Pay', 'knit-pay-lang' ),
			'callback' => [ $this, 'install_dependency_message' ],
		];
		
		return $panels;
		
	}
	
	public function install_dependency_message( $cf7 ) {
		$wpcf7_redirect_base = 'wpcf7-redirect/wpcf7-redirect.php';
		$plugin_link         = '<a href="https://wordpress.org/plugins/wpcf7-redirect/" target="_blank">Redirection For Contact Form 7</a>';
		
		$error = '<h2>For Knit Pay to work with Contact Form 7, %s plugin is required. Please %s to continue!</h2>';
				
		$plugins = get_plugins();
		if ( isset( $plugins[ $wpcf7_redirect_base ] ) ) {
			$url  = esc_url( wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=' . $wpcf7_redirect_base ), 'activate-plugin_' . $wpcf7_redirect_base ) );
			$link = '<a href="' . $url . '">' . __( 'activate it', 'knit-pay-lang' ) . '</a>';
		} else {
			$url  = esc_url( wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=wpcf7-redirect' ), 'install-plugin_wpcf7-redirect' ) );
			$link = '<a href="' . $url . '">' . __( 'install it', 'knit-pay-lang' ) . '</a>';
		}
						  
		echo '<p>' . sprintf( $error, $plugin_link, $link ) . '</p>';
	}
	
	/**
	 * Enqueue scripts.
	 */
	public function enqueue_scripts() {
		\wp_register_script(
			'knit-pay-contact-form-7',
			plugins_url( 'js/payment-form-processor.js', dirname( __FILE__ ) ),
			[ 'jquery' ],
			KNITPAY_VERSION,
			true
		);
		
		\wp_enqueue_script( 'knit-pay-contact-form-7' );
	}

	/**
	 * Enqueue Admin scripts.
	 */
	public function enqueue_admin_scripts() {
		\wp_register_script(
			'knit-pay-contact-form-7-admin',
			plugins_url( 'js/knit-pay-contact-form-7-admin.js', dirname( __FILE__ ) ),
			[ 'jquery' ],
			KNITPAY_VERSION,
			true
		);

		\wp_enqueue_script( 'knit-pay-contact-form-7-admin' );
	}

	/**
	 * Update the status of the specified payment
	 *
	 * @param Payment $payment Payment.
	 */
	public static function status_update( Payment $payment ) {
		$lead_id             = (int) $payment->get_source_id();
		$save_lead_action_id = get_post_meta( $lead_id, 'cf7_action_id', true );
		
		if ( empty( $lead_id ) ) {
			return $lead_id;
		}
		
		$save_lead_action_lead_map = get_post_meta( $save_lead_action_id, 'leads_map', true );
		if ( ! isset( $save_lead_action_lead_map['knit_pay_transaction_id'] ) ) {
			$save_lead_action_lead_map['knit_pay_transaction_id'] = [
				'tag'    => 'Knit Pay Transaction ID',
				'appear' => true,
			];
			$save_lead_action_lead_map['knit_pay_status']         = [
				'tag'    => 'Knit Pay Payment Status',
				'appear' => true,
			];
			$save_lead_action_lead_map['knit_pay_payment_id']     = [
				'tag' => 'Knit Pay Payment ID',
			];
			$save_lead_action_lead_map['knit_pay_amount']         = [
				'tag' => 'Knit Pay Amount',
			]; 
			update_post_meta( $save_lead_action_id, 'leads_map', $save_lead_action_lead_map );
		}
		
		update_post_meta( $lead_id, 'knit_pay_transaction_id', $payment->get_transaction_id() );
		update_post_meta( $lead_id, 'knit_pay_status', $payment->status );
		update_post_meta( $lead_id, 'knit_pay_payment_id', $payment->get_id() );
		update_post_meta( $lead_id, 'knit_pay_amount', $payment->get_total_amount()->get_value() );
		
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
		$text = __( 'Contact Form 7', 'knit-pay-lang' ) . '<br />';
		
		if ( ! empty( $payment->source_id ) ) {
			$text .= sprintf(
				'<a href="%s">%s</a>',
				get_edit_post_link( $payment->source_id ),
				/* translators: %s: source id */
				sprintf( __( 'Payment %s', 'knit-pay-lang' ), $payment->source_id )
			);
		}

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
		return __( 'Contact Form 7 Payment', 'knit-pay-lang' );
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
		if ( empty( $payment->source_id ) ) {
			return $url;
		}
		return get_edit_post_link( $payment->source_id );
	}

}
