<?php
namespace KnitPay\Extensions\WPForms;

use WPForms_Payment;
use Pronamic\WordPress\Money\Currency;
use Pronamic\WordPress\Money\Money;
use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Payments\Payment;

/**
 * Title: WPForms Gateway
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   5.9.0.0
 */

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit();

class Gateway extends WPForms_Payment {

	/**
	 * Initialize.
	 *
	 * @since 1.0.0
	 */
	public function init() {

		$this->version  = KNITPAY_VERSION;
		$this->name     = 'Knit Pay';
		$this->slug     = 'knit_pay_knit_pay';
		$this->priority = 1;
		$this->icon     = 'https://plugins.svn.wordpress.org/knit-pay/assets/icon.svg';
		
		add_action( 'wpforms_process_complete', [ $this, 'process_entry' ], 20, 4 );
		add_action( 'wpforms_form_settings_notifications_single_after', [ $this, 'notification_settings' ], 10, 2 );
		add_filter( 'wpforms_entry_email_process', [ $this, 'process_email' ], 70, 5 );
	}

	/**
	 * Display content inside the panel content area.
	 *
	 * @since 1.0.0
	 */
	public function builder_content() {

		wpforms_panel_field(
			'checkbox',
			$this->slug,
			'enable',
			$this->form_data,
			esc_html__( "Enable {$this->name} payments", 'knit-pay-lang' ),
			[
				'parent'  => 'payments',
				'default' => '0',
			]
		);
		
		wpforms_panel_field(
			'select',
			$this->slug,
			'config_id',
			$this->form_data,
			\esc_html__( 'Configuration', 'knit-pay-lang' ),
			[
				'parent'  => 'payments',
				'options' => Plugin::get_config_select_options(),
				'after'   => 'Configurations can be created in Knit Pay gateway configurations page at <a href="' . admin_url() . 'edit.php?post_type=pronamic_gateway">"Knit Pay >> Configurations"</a>.',
			]
		);
		
		\wpforms_panel_field(
			'text',
			$this->slug,
			'payment_description',
			$this->form_data,
			\esc_html__( 'Payment Description', 'knit-pay-lang' ),
			[
				'parent'  => 'payments',
				'default' => __( '{form_title} {entry_id}', 'knit-pay-lang' ),
				'after'   => sprintf( __( 'Available tags: %s', 'knit-pay-lang' ), sprintf( '<code>%s</code> <code>%s</code> <code>%s</code> <code>%s</code>', '{form_title}', '{form_desc}', '{form_id}', '{entry_id}' ) ),
			]
		);

		wpforms_panel_field(
			'select',
			$this->slug,
			'buyer_name',
			$this->form_data,
			\esc_html__( 'Buyer Name', 'knit-pay-lang' ),
			[
				'parent'      => 'payments',
				'field_map'   => [ 'name' ],
				'placeholder' => \esc_html__( '— Select Name —', 'knit-pay-lang' ),
			]
		);
		
		\wpforms_panel_field(
			'select',
			$this->slug,
			'buyer_email',
			$this->form_data,
			\esc_html__( 'Buyer Email', 'knit-pay-lang' ),
			[
				'parent'      => 'payments',
				'field_map'   => [ 'email' ],
				'placeholder' => \esc_html__( '— Select Email —', 'knit-pay-lang' ),
			]
		);
		
		\wpforms_panel_field(
			'select',
			$this->slug,
			'buyer_phone',
			$this->form_data,
			\esc_html__( 'Buyer Phone', 'knit-pay-lang' ),
			[
				'parent'      => 'payments',
				'field_map'   => [ 'phone' ],
				'placeholder' => \esc_html__( '— Select Phone —', 'knit-pay-lang' ),
			]
		);

		if ( function_exists( 'wpforms_conditional_logic' ) ) {
			wpforms_conditional_logic()->builder_block(
				[
					'form'        => $this->form_data,
					'type'        => 'panel',
					'panel'       => $this->slug,
					'parent'      => 'payments',
					'actions'     => [
						'go'   => esc_html__( 'Process', 'knit-pay-lang' ),
						'stop' => esc_html__( 'Don\'t process', 'knit-pay-lang' ),
					],
					'action_desc' => esc_html__( 'this charge if', 'knit-pay-lang' ),
				]
			);
		} else {
			echo '<p class="note">' .
				sprintf(
					wp_kses(
						/* translators: %s - Addons page URL in admin area. */
						__( 'Install the <a href="%s">Conditional Logic addon</a> to enable conditional logic for Knit Pay.', 'knit-pay-lang' ),
						[
							'a' => [
								'href' => [],
							],
						]
					),
					admin_url( 'admin.php?page=wpforms-addons' )
				) .
				'</p>';
		}
	}

	/**
	 * Process and submit entry to provider.
	 *
	 * @since 1.0.0
	 *
	 * @param array $fields
	 * @param array $entry
	 * @param array $form_data
	 * @param int   $entry_id
	 */
	public function process_entry( $fields, $entry, $form_data, $entry_id ) {
		// Check an entry was created and passed.
		if ( empty( $entry_id ) ) {
			return;
		}

		// Check if payment method exists.
		if ( empty( $form_data['payments'][ $this->slug ] ) ) {
			return;
		}

		// Check required payment settings.
		$payment_settings = $form_data['payments'][ $this->slug ];
		if ( empty( $payment_settings['enable'] ) || '1' !== $payment_settings['enable'] ) {
			return;
		}
		
		// Check for processing errors.
		if ( ! empty( wpforms()->process->errors[ $form_data['id'] ] ) ) {
			return;
		}
		
		$this->form_data = $form_data;
		$this->fields    = $fields;

		// If preventing the notification, log it, and then bail.
		if ( ! $this->is_conditional_logic_ok( $payment_settings ) ) {
			return;
		}

		// Check that, despite how the form is configured, the form and
		// entry actually contain payment fields, otherwise no need to proceed.
		$form_has_payments  = wpforms_has_payment( 'form', $form_data );
		$entry_has_paymemts = wpforms_has_payment( 'entry', $fields );
		if ( ! $form_has_payments || ! $entry_has_paymemts ) {
			return;
		}

		// Check total charge amount.
		$amount = wpforms_get_total_payment( $fields );
		if ( empty( $amount ) || $amount === wpforms_sanitize_amount( 0 ) ) {
			return;
		}
		
		// Initiating Knit Pay Payment.
		$config_id      = $payment_settings['config_id'];
		$payment_method = 'knit_pay'; // TODO
		
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
		
		$payment->source    = 'wpforms';
		$payment->source_id = $entry_id;
		$payment->order_id  = $entry_id;
		
		$payment->set_description( Helper::get_description( $form_data, $entry_id, $payment_settings ) );
		
		$payment->title = Helper::get_title( $entry_id );
		
		// Customer.
		$payment->set_customer( Helper::get_customer( $entry['fields'], $payment_settings ) );
		
		// Address.
		$payment->set_billing_address( Helper::get_address( $entry['fields'], $payment_settings ) );
		
		// Currency.
		$currency = Currency::get_instance( wpforms_get_currency() );
		
		// Amount.
		$payment->set_total_amount( new Money( $amount, $currency ) );
		
		// Method.
		$payment->set_payment_method( $payment_method );
		
		// Configuration.
		$payment->config_id = $config_id;
		
		try {
			$payment = Plugin::start_payment( $payment );
			
			// Build the return URL with hash.
			$query_args  = 'form_id=' . $form_data['id'] . '&entry_id=' . $entry_id . '&hash=' . wp_hash( $form_data['id'] . ',' . $entry_id );
			$return_url  = is_ssl() ? 'https://' : 'http://';
			$return_url .= $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
			if ( ! empty( $form_data['settings']['ajax_submit'] ) && ! empty( $_SERVER['HTTP_REFERER'] ) ) {
				$return_url = $_SERVER['HTTP_REFERER'];
			}
			
			$return_url = esc_url_raw(
				add_query_arg(
					[
						'wpforms_return' => base64_encode( $query_args ),
					],
					$return_url
				)
			);
			$payment->set_meta( 'wpforms_return_url', $return_url );
			$payment->save();
			
			// Redirect to Payment Gateway.
			wp_safe_redirect( $payment->get_pay_redirect_url() );
			exit;
		} catch ( \Exception $e ) {
			  \wpforms()->process->errors[ $form_data['id'] ]['footer'] = $e->getMessage();
			return;
		}
	}

	/**
	 * Check if conditional logic check passes for the given settings.
	 *
	 * @since 1.4.0
	 *
	 * @param array $form_data Form data.
	 * @param array $fields    Form fields.
	 *
	 * @return bool
	 */
	private function is_conditional_logic_ok( $payment_settings ) {
		
		// Check for conditional logic.
		if (
			empty( $payment_settings['conditional_logic'] ) &&
			empty( $payment_settings['conditional_type'] ) &&
			empty( $payment_settings['conditionals'] )
		) {
			return true;
		}

		// All conditional logic checks passed, continue with processing.
		$process = wpforms_conditional_logic()->conditionals_process( $this->fields, $this->form_data, $payment_settings['conditionals'] );
		
		if ( 'stop' === $payment_settings['conditional_type'] ) {
			$process = ! $process;
		}

		return $process;
	}

	/**
	 * Add checkbox to form notification settings.
	 *
	 * @since 1.4.0
	 *
	 * @param \WPForms_Builder_Panel_Settings $settings WPForms_Builder_Panel_Settings class instance.
	 * @param int                             $id       Subsection ID.
	 */
	public function notification_settings( $settings, $id ) {

		wpforms_panel_field(
			'checkbox',
			'notifications',
			$this->slug,
			$settings->form_data,
			esc_html__( 'Enable for Knit Pay completed payments', 'knit-pay-lang' ),
			[
				'parent'      => 'settings',
				'class'       => empty( $settings->form_data['payments'][ $this->slug ]['enable'] ) ? 'wpforms-hidden' : '',
				'input_class' => 'wpforms-radio-group wpforms-radio-group-' . $id . '-notification-by-status wpforms-radio-group-item-' . $this->slug . ' wpforms-notification-by-status-alert',
				'subsection'  => $id,
				'tooltip'     => wp_kses(
					__( 'When enabled this notification will <em>only</em> be sent when a Knit Pay payment has been successfully <strong>completed</strong>.', 'knit-pay-lang' ),
					[
						'em'     => [],
						'strong' => [],
					]
				),
				'data'        => [
					'radio-group'    => $id . '-notification-by-status',
					'provider-title' => esc_html__( 'Knit Pay completed payments', 'knit-pay-lang' ),
				],
			]
		);
	}

	/**
	 * Logic that helps decide if we should send completed payments notifications.
	 *
	 * @since 1.4.0
	 *
	 * @param bool   $process         Whether to process or not.
	 * @param array  $fields          Form fields.
	 * @param array  $form_data       Form data.
	 * @param int    $notification_id Notification ID.
	 * @param string $context         The context of the current email process.
	 *
	 * @return bool
	 */
	public function process_email( $process, $fields, $form_data, $notification_id, $context ) {

		if ( ! $process ) {
			return false;
		}
		
		$payment_settings = $form_data['payments'][ $this->slug ];
		$this->form_data  = $form_data;
		$this->fields     = $fields;

		if ( empty( $payment_settings['enable'] ) ) {
			return $process;
		}

		if ( empty( $form_data['settings']['notifications'][ $notification_id ][ $this->slug ] ) ) {
			return $process;
		}

		if ( ! $this->is_conditional_logic_ok( $payment_settings ) ) {
			return false;
		}

		return $context === $this->slug;
	}
}
