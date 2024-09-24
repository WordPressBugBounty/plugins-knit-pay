<?php

namespace KnitPay\Extensions\WPAdverts;

use Pronamic\WordPress\Money\Currency;
use Pronamic\WordPress\Money\Money;
use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Payments\Payment;
use Adverts_Form;
use Adverts;
use Adverts_Html;

/**
 * Title: WP Adverts Gateway
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   4.0.0
 */

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit();

class Gateway {

	/**
	 * @var string
	 */
	public $id = 'knit_pay';

	/**
	 * Bootstrap
	 *
	 * @param array $args Gateway properties.
	 */
	public function __construct( /* $args */ ) {

		// Set default values for Bank Transfer form
		add_filter( 'adverts_form_bind', [ $this, 'adverts_form_bind_defaults' ], 10, 2 );

		add_filter( 'wpadverts_module_groups', [ $this, 'add_core_gateways' ] );

		add_action( 'adext_register_payment_gateway', [ $this, 'adext_register_payment_gateway' ] );

		// Add Knit Pay to $adverts_namespace
		add_action( 'plugins_loaded', [ $this, 'adext_knit_pay_namespace' ] );
	}


	public static function adext_knit_pay_namespace() {
		global $adverts_namespace;

		// Add Knit Pay to adverts_namespace, in order to store module options and default options
		$adverts_namespace['knit_pay'] = [
			'option_name' => 'adext_knit_pay_config',
			'default'     => [
				'custom_title' => '',
			],
		];
	}

	public static function add_core_gateways( $module_groups ) {
		$new_module_groups = [];
		$module            = filter_input( INPUT_GET, 'module', FILTER_SANITIZE_STRING );
		$plugin            = 'knit-pay-wpadverts/knit-pay-wpadverts.php';
		if ( ! empty( $module ) ) {
			$plugin = 'knit-pay/extensions/wpadverts/src/gateway/admin/';
		}

		foreach ( $module_groups as $key => $group ) {
			if ( ! array_key_exists( 'bank-transfer', $group['modules'] ) ) {
				$new_module_groups[ $key ] = $group;
				continue;
			}

			$group['modules']['knit_pay'] = [
				'title'        => __( 'Knit Pay', 'wpadverts' ),
				'text'         => __( "This payment method does not use a predefined payment method for the payment. Some payment providers list all activated payment methods for your account to choose from. Use payment method specific gateways (such as 'Instamojo') to let customers choose their desired payment method at checkout.", 'knit-pay-lang' ),
				'type'         => '',
				'plugin'       => $plugin,
				'purchase_url' => 'https://www.knitpay.org/',
			];
			$new_module_groups[ $key ]    = $group;
		}
		return $new_module_groups;
	}

	/**
	 * Sets default values for Knit Pay Gateway
	 *
	 * This function checks if current payment form is Bank Transfer. If so and
	 * the $data is empty then we set default values for contact person and email fields.
	 *
	 * @since   1.3.0   The function will try to get the details from payment object first (if it exists).
	 *
	 * @param   Adverts_Form $form   Instance of form.
	 * @param   array        $data   User submitted form values ( key => value )
	 * @return  Adverts_Form            Modified instance of form.
	 */
	function adverts_form_bind_defaults( Adverts_Form $form, $data ) {

		$scheme = $form->get_scheme();

		if ( $scheme['name'] !== 'adverts-' . $this->id ) {
			return $form;
		}

		if ( empty( $data ) && 'adext_payments_render' === adverts_request( 'action' ) ) {

			if ( adverts_request( 'object_id' ) ) {
				$object = get_post( adverts_request( 'object_id' ) );
			} elseif ( adverts_request( 'payment_id' ) ) {
				$object = get_post( adverts_request( 'payment_id' ) );
			} else {
				return $form;
			}

			$form->set_value( 'adverts_person', get_post_meta( $object->ID, 'adverts_person', true ) );
			$form->set_value( 'adverts_email', get_post_meta( $object->ID, 'adverts_email', true ) );
			$form->set_value( 'adverts_phone', get_post_meta( $object->ID, 'adverts_phone', true ) );
		}

		return $form;
	}

	/**
	 * Renders Knit Pay Payment Form
	 *
	 * If user will select Knit Pay as a payment method, this function will render
	 * payment instructions.
	 *
	 * It is executed in third step in [adverts_add] shortcode.
	 *
	 * @param array $data Payment data
	 * @return array
	 */
	public static function gateway_render( $data ) {

		// Initiating Payment.
		$config_id = adverts_config( $data['gateway_name'] . '.config_id' );

		$payment_method = $data['gateway_name'];

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

		$payment->source    = 'wpadverts';
		$payment->source_id = $data['payment_id'];
		$payment->order_id  = $data['payment_id'];

		$payment->set_description( Helper::get_description( $data ) );

		$payment->title = Helper::get_title( $data );

		// Customer.
		$payment->set_customer( Helper::get_customer( $data ) );

		// Address.
		$payment->set_billing_address( Helper::get_address( $data ) );

		// Currency.
		$currency = Currency::get_instance( Adverts::instance()->get( 'currency' )['code'] );

		// Amount.
		$payment->set_total_amount( new Money( $data['price'], $currency ) );

		// Method.
		$payment->set_payment_method( $payment_method );

		// Configuration.
		$payment->config_id = $config_id;

		try {
			$payment = Plugin::start_payment( $payment );

			return self::get_payment_redirect_html( $payment->get_pay_redirect_url() );
		} catch ( \Exception $e ) {
			// Create Form
			$gateway = adext_payment_gateway_get( $data['gateway_name'] );
			$form    = new Adverts_Form();
			$form->load( $gateway['form']['payment_form'] );
			$form->bind( $data['bind'] );

			// Render Form
			ob_start();
			include ADVERTS_PATH . 'templates/form.php';
			$html_form = ob_get_clean();

			// Add Error Message
			$html_form .= new Adverts_Html(
				'div',
				[
					'class' => 'adverts-form',
				],
				Adverts_Html::build( 'div', [ 'class' => 'adverts-field-error' ], $e->getMessage() )
			);

			return [
				'result'  => 0,
				'html'    => $html_form,
				'execute' => null,
			];
		}
	}

	public static function get_payment_redirect_html( $payment_url ) {
		$html_note = new Adverts_Html(
			'div',
			[
				'style' => 'line-height:2em',
			],
			join(
				"\r\n",
				[
					Adverts_Html::build(
						'span',
						[
							'class' => 'adverts-loader adverts-icon-spinner animate-spin',
							'style' => 'display:inline',
						],
						''
					),
					Adverts_Html::build( 'span', [], __( 'You are being redirected to Payment Gateway, please wait few seconds.', 'knit-pay-lang' ) ),
				]
			)
		);

		$html_form = new Adverts_Html(
			'form',
			[
				'action' => $payment_url,
				'method' => 'post',
				'id'     => 'knit-pay-form',
				'style'  => 'display:none',
			],
			join(
				"\r\n",
				[
					Adverts_Html::build(
						'input',
						[
							'type' => 'submit',
							'name' => 'submit',
						]
					),
				]
			)
		);

		return [
			'result'     => 1,
			'html'       => $html_note->render() . $html_form->render(),
			'execute'    => 'click', // null|click|submit
			'execute_id' => "#knit-pay-form input[name='submit']",
		];
	}

	public static function adext_register_payment_gateway() {
		$id = 'knit_pay';

		if ( adverts_config( $id . '.custom_title' ) ) {
			$title = adverts_config( $id . '.custom_title' );
		} else {
			$title = __( 'Knit Pay', 'wpadverts' );
		}

		adext_payment_gateway_add(
			$id,
			[
				'name'     => $id,
				'title'    => $title,
				'order'    => 10,
				'data'     => [],
				'callback' => [
					'render' => [ __NAMESPACE__ . '\Gateway', 'gateway_render' ],
				],
				'form'     => [
					'payment_form' => [
						'name'   => 'adverts-' . $id,
						'layout' => 'aligned',
						'action' => '',
						'field'  => [
							[
								'name'        => 'adverts_person',
								'type'        => 'adverts_field_text',
								'order'       => 10,
								'label'       => __( 'Contact Person', 'wpadverts' ),
								'is_required' => true,
								'validator'   => [
									[ 'name' => 'is_required' ],
								],
							],
							[
								'name'        => 'adverts_email',
								'type'        => 'adverts_field_text',
								'order'       => 10,
								'label'       => __( 'Email', 'wpadverts' ),
								'is_required' => true,
								'validator'   => [
									[ 'name' => 'is_required' ],
									[ 'name' => 'is_email' ],
								],
							],
							[
								'name'      => 'adverts_phone',
								'type'      => 'adverts_field_text',
								'order'     => 10,
								'label'     => __( 'Phone Number', 'wpadverts' ),
								'validator' => [
									[
										'name'   => 'string_length',
										'params' => [ 'min' => 5 ],
									],
								],
							],
						], // end field
					], // end payment_form
				], // end form
			]
		);
	}
}
