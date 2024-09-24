<?php
/**
 * Payment gateway
 *
 * @author    Pronamic <info@pronamic.eu>
 * @copyright 2005-2023 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\WordPress\Pay\Extensions\NinjaForms
 */

namespace Pronamic\WordPress\Pay\Extensions\NinjaForms;

use NF_Abstracts_PaymentGateway;
use Pronamic\WordPress\Money\Currency;
use Pronamic\WordPress\Money\Money;
use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Core\PaymentMethods;
use Pronamic\WordPress\Pay\Payments\Payment;

/**
 * Payment gateway
 *
 * @version 1.5.1
 * @since   1.0.0
 */
final class PaymentGateway extends NF_Abstracts_PaymentGateway {
	/**
	 * Slug.
	 *
	 * @var string
	 */
	protected $_slug = 'pronamic_pay';

	/**
	 * Name.
	 *
	 * @var string
	 */
	protected $_name = '';

	/**
	 * Settings.
	 *
	 * @var array
	 */
	protected $_settings = [];

	/**
	 * Constructor for the payment gateway.
	 */
	public function __construct() {
		$this->_name = __( 'Knit Pay', 'pronamic_ideal' );

		add_action( 'ninja_forms_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		$this->_settings = $this->action_settings();
	}

	/**
	 * Processing form.
	 *
	 * @param array  $action_settings Action settings.
	 * @param string $form_id Form id.
	 * @param array  $data Form data.
	 * @return array|bool
	 */
	public function process( $action_settings, $form_id, $data ) {
		// Check if resuming form action processing.
		if ( \defined( 'PRONAMIC_PAY_NINJA_FORMS_RESUME' ) && PRONAMIC_PAY_NINJA_FORMS_RESUME ) {
			return false;
		}

		// Gateway.
		$config_id = NinjaFormsHelper::get_config_id_from_action_settings( $action_settings );
		$gateway   = Plugin::get_gateway( $config_id );

		if ( ! $gateway ) {
			return false;
		}

		/**
		 * Build payment.
		 */
		$payment = new Payment();

		$payment->source    = 'ninja-forms';
		$payment->source_id = NinjaFormsHelper::get_source_id_from_submission_data( $data );
		$payment->order_id  = $payment->source_id;

		$description = NinjaFormsHelper::get_description_from_action_settings( $action_settings );

		if ( empty( $description ) ) {
			$description = \sprintf(
				'%s #%s',
				__( 'Submission', 'pronamic_ideal' ),
				$payment->source_id
			);
		}

		$payment->set_description( $description );

		$payment->title = sprintf(
			/* translators: %s: payment data title */
			__( 'Payment for %s', 'pronamic_ideal' ),
			$description
		);

		// Customer.
		$payment->set_customer( NinjaFormsHelper::get_customer( $action_settings ) );

		// Address.
		$payment->set_billing_address( NinjaFormsHelper::get_address( $action_settings ) );

		// Currency.
		$currency = Currency::get_instance( NinjaFormsHelper::get_currency_from_form_id( $form_id ) );

		// Amount.
		$payment->set_total_amount( new Money( $action_settings['payment_total'], $currency ) );

		// Method.
		$payment->set_payment_method( NinjaFormsHelper::get_payment_method_from_submission_data( $data ) );

		// Issuer.
		$issuer = NinjaFormsHelper::get_issuer_from_submission_data( $data );

		if ( null !== $issuer ) {
			$payment->set_meta( 'issuer', $issuer );
		}

		// Configuration.
		$payment->config_id = $config_id;

		// Subscription
		$subscription = NinjaFormsHelper::get_subscription( $action_settings, $data, $payment->description, $payment->get_total_amount() );
		if ( isset( $subscription ) ) {
			$subscription->set_description( $payment->get_description() );
			$period                    = $subscription->new_period();
			if ( null !== $period ) {
				$payment->add_period( $period );
			}
			$payment->subscription_source_id = $payment->source_id;
			$payment->subscription           = $subscription;
		}

		// Only start payments for known/active payment methods.
		$payment_method = $payment->get_payment_method();

		if ( null !== $payment_method && ! PaymentMethods::is_active( $payment_method ) ) {
			return false;
		}

		// Set form and action ID in payment meta for use in redirect URL.
		$payment->set_meta( 'ninjaforms_payment_action_id', $action_settings['id'] );
		$payment->set_meta( 'ninjaforms_payment_form_id', $form_id );

		try {
			$payment = Plugin::start_payment( $payment );

			// Update Knit Pay payment status before redirecting.
			$submission = Ninja_Forms()->form( $form_id )->sub( $payment->get_order_id() )->get();
			$submission->update_extra_value( 'knit_pay_status', $payment->status );
			$submission->save();

			// Set form processing data.
			$data['actions']['redirect']        = $payment->get_pay_redirect_url();
			$data['actions']['success_message'] = __( 'Please wait while you are redirected to complete the payment.', 'pronamic_ideal' );

			// Maybe prepare for delayed actions.
			$delayed_action_ids = NinjaFormsHelper::get_delayed_action_ids_from_settings( $action_settings );

			if ( ! empty( $delayed_action_ids ) ) {
				// Update session.
				\Ninja_Forms()->session()->set( 'pronamic_pay_has_delayed_actions', true );
				\Ninja_Forms()->session()->set( 'nf_processing_data', $data );

				// Set session cookie in payment meta.
				$session_cookie = NinjaFormsHelper::get_session_cookie();

				if ( null !== $session_cookie ) {
					$payment->set_meta( 'ninjaforms_session_cookie', $session_cookie );

					$payment->save();
				}
			}
		} catch ( \Exception $e ) {
			$message = sprintf( '%1$s: %2$s', $e->getCode(), $e->getMessage() );

			$data['errors']['form']['pronamic-pay']         = Plugin::get_default_error_message();
			$data['errors']['form']['pronamic-pay-gateway'] = esc_html( $message );
		}

		return $data;
	}

	public function enqueue_scripts( $data ) {
		wp_enqueue_script( 'nf-knit-pay-response', plugin_dir_url( __FILE__ ) . 'assets/js/error-handler.js', array( 'nf-front-end' ) );
	}

	/**
	 * Action settings.
	 *
	 * @return array
	 */
	public function action_settings() {
		$settings = [];

		// Configuration.
		$settings['config_id'] = [
			'label'   => __( 'Configuration', 'pronamic_ideal' ),
			'name'    => 'pronamic_pay_config_id',
			'group'   => 'pronamic_pay',
			'type'    => 'select',
			'width'   => 'full',
			'options' => [],
		];

		foreach ( Plugin::get_config_select_options() as $value => $label ) {
			if ( 0 === $value ) {
				$label = \__( '— Default Gateway —', 'pronamic_ideal' );
			}

			$settings['config_id']['options'][] = [
				'label' => $label,
				'value' => $value,
			];
		}

		// Description.
		$settings['description'] = [
			'name'           => 'pronamic_pay_description',
			'type'           => 'textbox',
			'group'          => 'pronamic_pay',
			'label'          => __( 'Transaction Description', 'pronamic_ideal' ),
			'placeholder'    => '',
			'value'          => '',
			'width'          => 'full',
			'use_merge_tags' => [
				'include' => [
					'calcs',
				],
			],
		];

		// User Information Fields
		$knit_pay_user_info_settings[] = $this->add_user_info_action_setting( 'knit_pay_fname', __( 'First Name', 'knit-pay' ) );
		$knit_pay_user_info_settings[] = $this->add_user_info_action_setting( 'knit_pay_lname', __( 'Last Name', 'knit-pay' ) );
		$knit_pay_user_info_settings[] = $this->add_user_info_action_setting( 'knit_pay_phone', __( 'Phone', 'knit-pay' ) );
		$knit_pay_user_info_settings[] = $this->add_user_info_action_setting( 'knit_pay_email', __( 'Email', 'knit-pay' ) );
		$knit_pay_user_info_settings[] = $this->add_user_info_action_setting( 'knit_pay_address', __( 'Address', 'knit-pay' ) );
		$knit_pay_user_info_settings[] = $this->add_user_info_action_setting( 'knit_pay_city', __( 'City', 'knit-pay' ) );
		$knit_pay_user_info_settings[] = $this->add_user_info_action_setting( 'knit_pay_state', __( 'State', 'knit-pay' ) );
		$knit_pay_user_info_settings[] = $this->add_user_info_action_setting( 'knit_pay_country', __( 'Country (ISO 3166 country code)', 'knit-pay' ) );
		$knit_pay_user_info_settings[] = $this->add_user_info_action_setting( 'knit_pay_zip', __( 'Zip', 'knit-pay' ) );
		
		$settings['knit_pay_user_info'] = array(
			'name'     => 'knit_pay_user_info',
			'type'     => 'fieldset',
			'label'    => __( 'User Information Fields', 'pronamic_ideal' ),
			'width'    => 'full',
			'group'    => 'pronamic_pay',
			'settings' => $knit_pay_user_info_settings,
		);

		// Recurring Payment Settings
		$settings['knit_pay_interval']        = $this->add_action_setting( 'knit_pay_interval', __( 'Payment Repeats Every', 'knit-pay' ), 'knit_pay_recurring_settings' );
		$settings['knit_pay_interval_period'] = $this->add_interval_period_setting();
		$settings['knit_pay_frequency']       = $this->add_action_setting( 'knit_pay_frequency', __( 'Payment Count', 'knit-pay' ), 'knit_pay_recurring_settings' );

		/*
		 * Status pages.
		 */
		$settings['pronamic_pay_status_pages'] = [
			'name'     => 'pronamic_pay_status_pages',
			'type'     => 'fieldset',
			'label'    => __( 'Payment Status Pages', 'pronamic_ideal' ),
			'width'    => 'full',
			'group'    => 'pronamic_pay',
			'settings' => [],
		];

		$options = [
			[
				'label' => __( '— Select —', 'pronamic_ideal' ),
			],
		];

		foreach ( \get_pages() as $page ) {
			$options[] = [
				'label' => $page->post_title,
				'value' => $page->ID,
			];
		}

		// Add settings fields.
		$pages = [
			'completed' => \__( 'Completed', 'pronamic_ideal' ),
			'cancel'    => \__( 'Canceled', 'pronamic_ideal' ),
			'expired'   => \__( 'Expired', 'pronamic_ideal' ),
			'error'     => \__( 'Error', 'pronamic_ideal' ),
			'unknown'   => \__( 'Unknown', 'pronamic_ideal' ),
		];

		foreach ( $pages as $key => $label ) {
			$id = \sprintf( 'pronamic_pay_%s_page_id', $key );

			$settings['pronamic_pay_status_pages']['settings'][] = [
				'name'        => $id,
				'type'        => 'select',
				'group'       => 'pronamic_pay',
				'label'       => $label,
				'placeholder' => '',
				'value'       => '',
				'width'       => 'full',
				'options'     => $options,
			];
		}

		/*
		 * Delayed actions.
		 */
		$form_id = \filter_input( \INPUT_GET, 'form_id', \FILTER_SANITIZE_NUMBER_INT );

		if ( null !== $form_id ) {
			$settings['pronamic_pay_delayed_actions'] = [
				'name'     => 'pronamic_pay_delayed_actions',
				'type'     => 'fieldset',
				'label'    => __( 'Delayed actions', 'pronamic_ideal' ),
				'width'    => 'full',
				'group'    => 'pronamic_pay',
				'settings' => [],
			];

			$actions = \Ninja_Forms()->form( $form_id )->get_actions();

			$no_delay_types = [ 'successmessage' ];

			foreach ( $actions as $action ) {
				$action_type = $action->get_setting( 'type' );

				// Check action timing and priority. Only `late` (1) actions can be delayed
				// with a priority higher than the `collectpayment` action (`0`).
				$type = \Ninja_Forms()->actions[ $action_type ];

				if ( null === $type ) {
					continue;
				}

				if ( ! ( 1 === $type->get_timing() && $type->get_priority() > 0 ) ) {
					continue;
				}

				// Check if action type can be delayed.
				if ( \in_array( $action_type, $no_delay_types, true ) ) {
					continue;
				}

				// Add setting.
				$settings['pronamic_pay_delayed_actions']['settings'][] = [
					'name'  => sprintf( 'pronamic_pay_delayed_action_%d', $action->get_id() ),
					'type'  => 'toggle',
					'width' => 'full',
					'label' => $action->get_setting( 'label' ),
				];
			}
		}

		return $settings;
	}

	private function add_user_info_action_setting( $name, $label ) {
		return $this->add_action_setting( $name, $label, 'pronamic_pay' );
	}

	private function add_interval_period_setting() {
		return array(
			'name'            => 'knit_pay_interval_period',
			'type'            => 'select',
			'label'           => esc_html__( 'Payment Frequency', 'knit-pay' ),
			'width'           => 'one-half',
			'group'           => 'knit_pay_recurring_settings',
			'deps'            => array(
				'payment_gateways' => 'pronamic_pay',
			),
			'default_options' => array(
				'label' => esc_html__( 'Interval Period Form Field', 'ninja-forms' ),
				'value' => '0',
			),
			'options'         => array(
				array(
					'value' => 0,
					'label' => esc_html__( 'Value from Interval Period Form Field', 'knit-pay' ),
				),
				array(
					'value' => 'D',
					'label' => __( 'Daily', 'pronamic_ideal' ),
				),
				array(
					'value' => 'W',
					'label' => __( 'Weekly', 'pronamic_ideal' ),
				),
				array(
					'value' => 'M',
					'label' => __( 'Monthly', 'pronamic_ideal' ),
				),
				array(
					'value' => 'Y',
					'label' => __( 'Yearly', 'pronamic_ideal' ),
				),
			),
		);
	}

	private function add_action_setting( $name, $label, $group ) {
		return array(
			'name'           => $name,
			'type'           => 'textbox',
			'group'          => $group,
			'label'          => $label,
			'placeholder'    => '',
			'value'          => '',
			'width'          => 'one-half',
			'deps'           => array(
				'payment_gateways' => 'pronamic_pay',
			),
			'use_merge_tags' => array(
				'include' => array(
					'calcs',
				),
			),
		);
	}

}
