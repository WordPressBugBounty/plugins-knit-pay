<?php
use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Payments\Payment;
use KnitPay\Extensions\PaidMembershipsPro\Helper;

/**
 * Title: Paid Memberships Pro extension
 * Description:
 * Copyright: 2020-2026 Knit Pay
 * Company: Knit Pay
 *
 * @author knitpay
 * @since 2.0.0
 * @version 8.96.22.0
 */

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit();

/**
 * PMProGateway_knit_pay class
 *
 * The class name must match PMPro core's dynamic instantiation pattern
 * (PMProGateway_ + gateway name), so we cannot rename it to satisfy
 * PEAR naming conventions without breaking PMPro integration.
 */
// phpcs:ignore PEAR.NamingConventions.ValidClassName.Invalid
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
	}

	/**
	 * Run on WP init
	 *
	 * @since 1.8
	 */
	public function init() {
		$this->config_id = self::pmpro_getOption( 'knit_pay_config_id' );

		// make sure knit pay is a gateway option
		add_filter( 'pmpro_gateways', [ $this, 'pmpro_gateways' ] );

		// add fields to payment settings
		add_filter( 'pmpro_payment_options', [ $this, 'pmpro_payment_options' ] );
		add_filter( 'pmpro_payment_option_fields', [ $this, 'pmpro_payment_option_fields' ], 10, 2 );

		// code to add at checkout if knit pay is the current gateway
		$gateway = self::pmpro_getOption( 'gateway' );
		if ( $gateway === $this->id ) {
			add_filter( 'pmpro_include_payment_information_fields', '__return_false' );
			add_filter( 'pmpro_required_billing_fields', [ $this, 'pmpro_required_billing_fields' ] );
			add_action( 'pmpro_checkout_before_change_membership_level', [ $this, 'pmpro_checkout_before_change_membership_level' ], 10, 2 );
			add_action( 'pmpro_checkout_before_form', [ $this, 'hide_checkout_fields' ] );

			// Actions for adding support for Multiple Gateways.
			add_action( 'pmpro_checkout_boxes', [ $this, 'pmpro_checkout_boxes_multi_gateway' ], 30 );
			add_action( 'pmpro_after_saved_payment_options', [ $this, 'save_config_ids' ] );
		}
	}

	/**
	 * Make sure knit_pay is in the gateways list
	 *
	 * @since 1.8
	 */
	public function pmpro_gateways( $gateways ) {
		if ( empty( $gateways[ $this->id ] ) ) {
			// TODO: remove hardcode
			$gateways[ $this->id ] = __( 'Knit Pay', 'knit-pay-lang' );
		}
		return $gateways;
	}

	/**
	 * Get a list of payment options that the knit pay gateway needs/supports.
	 *  TODO: deprecated in PMPro 3.5, remove after Dec 2026.
	 *
	 * @since 1.8
	 */
	public static function getGatewayOptions() {
		_deprecated_function( __METHOD__, '8.96.22.0' );
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
	public function pmpro_payment_options( $options ) {
		// TODO: Deprecated in PMPro 3.5, remove after Dec 2026.
		_deprecated_function( __METHOD__, '8.96.22.0' );

		// get knit pay options
		$knit_pay_options = $this->getGatewayOptions();

		// merge with others.
		$options = array_merge( $knit_pay_options, $options );

		return $options;
	}

	/**
	 * Settings form fields.
	 *
	 * @param string $form    Existing form HTML.
	 * @param string $gateway Current gateway.
	 * @param string $id      Gateway ID.
	 * @return string
	 */
	private static function setting_form( $form, $gateway = 'knit_pay', $id = 'knit_pay' ) {
		$configurations = Plugin::get_config_select_options( $id );
		if ( 1 < count( $configurations ) ) {
			unset( $configurations[0] );
		}

		$payment_description  = self::pmpro_getOption( 'knit_pay_payment_description' );
		$hide_billing_address = self::pmpro_getOption( 'knit_pay_hide_billing_address' );
		$hide_phone_field     = self::pmpro_getOption( 'knit_pay_hide_phone' );
		$title                = self::pmpro_getOption( 'knit_pay_title' );
		$config_id            = self::pmpro_getOption( 'knit_pay_config_id' );

		if ( empty( $config_id ) ) {
			$config_id = get_option( 'pronamic_pay_config_id' );
			pmpro_setOption( 'knit_pay_config_id', $config_id );
		}
		if ( empty( $payment_description ) ) {
			$payment_description = 'Paid Memberships Pro {order_id}';
			pmpro_setOption( 'knit_pay_payment_description', $payment_description );
		}
		if ( empty( $title ) ) {
			$title = 'Online Payment';
			pmpro_setOption( 'knit_pay_title', $title );
		}
		if ( '' === $hide_billing_address ) {
			$hide_billing_address = '1';
			pmpro_setOption( 'knit_pay_hide_billing_address', $hide_billing_address );
		}

		// Title.
		$form .= '<tr class="gateway gateway_' . esc_attr( $id ) . '"';
		if ( $gateway !== $id ) {
			$form .= ' style="display: none;"';
		}
		$form .= '>
            <th scope="row" valign="top"><label for="knit_pay_title">' . esc_html__( 'Title:', 'knit-pay-lang' ) . '</label></th>
            <td>
                <input type="text" id="knit_pay_title" name="knit_pay_title" value="' . esc_attr( $title ) . '" class="regular-text code" />
            	<p class="description">' . esc_html__( 'Payment Method title visible on the payment confirmation page and invoice page.', 'knit-pay-lang' ) . '</p>
            </td>
        </tr>';

		// Configuration.
		$form .= '<tr class="gateway gateway_' . esc_attr( $id ) . '"';
		if ( $gateway !== $id ) {
			$form .= ' style="display: none;"';
		}
		$form                       .= '>
            <th scope="row" valign="top"><label for="knit_pay_config_id">' . esc_html__( 'Configuration:', 'knit-pay-lang' ) . '</label></th>
        	<td><select id="knit_pay_config_id" name="knit_pay_config_id[]" multiple size="8">';
		$knit_pay_selected_config_id = self::pmpro_getOption( 'knit_pay_config_id' );
		if ( is_string( $knit_pay_selected_config_id ) ) {
			$knit_pay_selected_config_id = [ $knit_pay_selected_config_id ];
		}
		foreach ( $configurations as $key => $configuration ) {
			$selected = in_array( (string) $key, array_map( 'strval', (array) $knit_pay_selected_config_id ), true ) ? 'selected ' : '';
			$form    .= '<option value="' . esc_attr( $key ) . '"' . esc_attr( $selected ) . '>' . esc_html( $configuration ) . '</option>';
		}
		$form .= '</select>
        		<p class="description">' . esc_html__( 'Configurations can be created in Knit Pay gateway configurations page at', 'knit-pay-lang' ) . ' <a href="' . esc_url( admin_url( 'edit.php?post_type=pronamic_gateway' ) ) . '" target="_blank">"Knit Pay >> ' . esc_html__( 'Configurations', 'knit-pay-lang' ) . '"</a>.</p>
				<p class="description">' . esc_html__( 'Hold down the Ctrl (windows) or Command (Mac) button to select multiple options.', 'knit-pay-lang' ) . '</p>
        	</td>
        </tr>';

		// Payment Description.
		$form .= '<tr class="gateway gateway_' . esc_attr( $id ) . '"';
		if ( $gateway !== $id ) {
			$form .= ' style="display: none;"';
		}
		$form .= '>
            <th scope="row" valign="top"><label for="knit_pay_payment_description">' . esc_html__( 'Payment Description:', 'knit-pay-lang' ) . '</label></th>
            <td>
                <input type="text" id="knit_pay_payment_description" name="knit_pay_payment_description" value="' . esc_attr( $payment_description ) . '" class="regular-text code" />
            	<p class="description">' . esc_html__( 'Available tags:', 'knit-pay-lang' ) . ' <code>{order_id}, {code}, {invoice_id}, {membership_name}</code></p>
            </td>
        </tr>';

		// Hide Billing Address.
		$hide_options = [
			'0' => __( 'No', 'knit-pay-lang' ),
			'1' => __( 'Yes', 'knit-pay-lang' ),
		];
		$form        .= '<tr class="gateway gateway_' . esc_attr( $id ) . '"';
		if ( $gateway !== $id ) {
			$form .= ' style="display: none;"';
		}
		$form .= '>
            <th scope="row" valign="top"><label for="knit_pay_hide_billing_address">' . esc_html__( 'Hide Billing Address Fields:', 'knit-pay-lang' ) . '</label></th>
        	<td><select id="knit_pay_hide_billing_address" name="knit_pay_hide_billing_address">';
		foreach ( $hide_options as $key => $hide_option ) {
			$form .= '<option value="' . esc_attr( $key ) . '"' . selected( $hide_billing_address, $key, false ) . '>' . esc_html( $hide_option ) . '</option>';
		}
		$form .= '</select>
        		<p class="description">' . esc_html__( 'Hide not required billing address fields on the checkout page.', 'knit-pay-lang' ) . '</p>
        	</td>
        </tr>';

		// Hide Phone Field.
		$hide_options = [
			'0' => __( 'No', 'knit-pay-lang' ),
			'1' => __( 'Yes', 'knit-pay-lang' ),
		];
		$form        .= '<tr class="gateway gateway_' . esc_attr( $id ) . '"';
		if ( $gateway !== $id ) {
			$form .= ' style="display: none;"';
		}
		$form .= '>
            <th scope="row" valign="top"><label for="knit_pay_hide_phone">' . esc_html__( 'Hide Phone Field:', 'knit-pay-lang' ) . '</label></th>
        	<td><select id="knit_pay_hide_phone" name="knit_pay_hide_phone">';
		foreach ( $hide_options as $key => $hide_option ) {
			$form .= '<option value="' . esc_attr( $key ) . '"' . selected( $hide_phone_field, $key, false ) . '>' . esc_html( $hide_option ) . '</option>';
		}
		$form .= '</select>
        		<p class="description">' . esc_html__( 'Hide Phone field on the checkout page.', 'knit-pay-lang' ) . '</p>
        	</td>
        </tr>';

		return $form;
	}

	/**
	 * Display fields for Knit Pay options.
	 * TODO: old code for PMPro < 3.5, remove after Dec 2026.
	 *
	 * @since 1.8
	 */
	public function pmpro_payment_option_fields( $values, $gateway ) {
		// TODO: Deprecated in PMPro 3.5, remove after Dec 2026.
		_deprecated_function( __METHOD__, '8.96.22.0' );

		// Knit Pay Settings Heading.
		// TODO add message that recurring payments are not supported.
		$form  = '';
		$form .= '<tr class="pmpro_settings_divider gateway gateway_' . esc_attr( $this->id ) . '"';
		if ( $gateway !== $this->id ) {
			$form .= ' style="display: none;"';
		}
		$form .= '><td colspan="2">	<hr /><h2 class="title">' . esc_html__( 'Knit Pay Settings', 'knit-pay-lang' ) . '</h2></td></tr>';

		$form .= self::setting_form( $form, $gateway, $this->id );

		// Display Currency Fields.
		$form .= '<script>window.onload = function() {pmpro_changeGateway();}</script>';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The HTML string contains <select>/<option>/<script> and inline styles which wp_kses functions strip. All dynamic strings, attributes, and URLs are individually escaped during build-up.
		echo $form;

		return $form;
	}

	/**
	 * Display fields for Knit Pay options. Introduced in PMPro 3.5.
	 */
	public static function show_settings_fields() {
		?>
		<!-- TODO: add link of instructions<p>
		<?php
			printf(
				/* translators: %s: URL to the Knit Pay gateway documentation. */
				esc_html__( 'For detailed setup instructions, please visit our %s.', 'knit-pay-lang' ),
				'<a href="" target="_blank">' . esc_html__( 'Knit Pay documentation', 'knit-pay-lang' ) . '</a>'
			);
		?>
		</p>-->
		<div id="pmpro_knit_pay" class="pmpro_section" data-visibility="shown" data-activated="true">
			<div class="pmpro_section_toggle">
				<button class="pmpro_section-toggle-button" type="button" aria-expanded="true">
					<span class="dashicons dashicons-arrow-up-alt2"></span>
					<?php esc_html_e( 'Settings', 'knit-pay-lang' ); ?>
				</button>
			</div>
			<div class="pmpro_section_inside">
				<table class="form-table">
					<tbody>

						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The form contains <select>/<option>/<script> and inline styles which wp_kses functions strip. All dynamic strings and attributes are individually escaped during build-up.
						echo self::setting_form( '', 'knit_pay', 'knit_pay' );
						?>

					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Save settings for Knit Pay. Introduced in PMPro 3.5.
	 */
	public static function save_settings_fields() {
		$settings_to_save = [
			'knit_pay_title',
			'knit_pay_payment_description',
			'knit_pay_config_id',
			'knit_pay_hide_billing_address',
			'knit_pay_hide_phone',
		];

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nonce is verified by PMPro core (check_admin_referer) before this gateway hook executes.
		foreach ( $settings_to_save as $setting ) {
			if ( isset( $_REQUEST[ $setting ] ) ) {
				update_option( 'pmpro_' . $setting, sanitize_text_field( wp_unslash( $_REQUEST[ $setting ] ) ) );
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Remove required billing fields
	 *
	 * @since 1.8
	 */
	public static function pmpro_required_billing_fields( $fields ) {
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

		if ( self::pmpro_getOption( 'knit_pay_hide_phone' ) ) {
			unset( $fields['bphone'] );
		}

		return $fields;
	}

	/**
	 * Instead of change membership levels, send users to Payment Gateway to pay.
	 *
	 * @param int          $user_id User ID.
	 * @param \MemberOrder $morder  Member Order.
	 *
	 * @since 1.8
	 */
	public static function pmpro_checkout_before_change_membership_level( $user_id, $morder ) {
		global $wpdb, $discount_code, $discount_code_id, $knit_pay_redirect_url;

		// if no order, no need to pay
		if ( empty( $morder ) || empty( $knit_pay_redirect_url ) ) {
			return;
		}

		$morder->user_id = $user_id;
		$morder->saveOrder();

		// If we have a discount code but not the ID, get the ID.
		if ( ! empty( $discount_code ) && empty( $discount_code_id ) ) {
			$discount_code_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->pmpro_discount_codes} WHERE code = %s LIMIT 1", $discount_code ) );
		}
		// save discount code use
		if ( ! empty( $discount_code_id ) ) {
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$wpdb->pmpro_discount_codes_uses} (code_id, user_id, order_id, timestamp) VALUES(%d, %d, %s, %s)",
					$discount_code_id,
					$user_id,
					$morder->id,
					current_time( 'mysql' )
				)
			);
		}

		do_action( 'pmpro_before_send_to_knit_pay', $user_id, $morder );
		do_action( 'pmpro_after_checkout', $user_id, $morder );

		wp_safe_redirect( $knit_pay_redirect_url );
		exit();
	}

	/**
	 * Send Paramenters to payment gateway to generate the payment link
	 *
	 * @param \MemberOrder $morder Member Order.
	 * @return bool
	 */
	private function sendToGateway( &$morder ) {
		// TODO add recuring option
		if ( pmpro_isLevelRecurring( $morder->membership_level ) ) {
			$morder->error = __( 'This payment gateway currently does not support recurring payments. If you are the store owner, kindly uncheck the "Recurring Subscription" option in the Membership Level settings.', 'knit-pay-lang' );
			return false;
		}

		global $knit_pay_redirect_url;

		$this->config_id = self::pmpro_getOption( 'knit_pay_config_id' );
		if ( is_array( $this->config_id ) ) {
			$this->config_id = isset( $_POST['knit_pay_config_id'] ) ? sanitize_text_field( wp_unslash( $_POST['knit_pay_config_id'] ) ) : $this->config_id[0];
		}
		// Use default gateway if no configuration has been set.
		if ( empty( $this->config_id ) ) {
			$this->config_id = get_option( 'pronamic_pay_config_id' );
		}

		$payment_method = $this->id;

		$gateway = Plugin::get_gateway( $this->config_id );

		if ( ! $gateway ) {
			return false;
		}

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
		$payment->config_id = $this->config_id;

		try {
			$payment = Plugin::start_payment( $payment );

			$knit_pay_redirect_url = $payment->get_pay_redirect_url();
			return true;
		} catch ( \Exception $e ) {
			$morder->error = $e->getMessage() . '<br>' . Plugin::get_default_error_message();
			return false;
		}
	}

	/**
	 * Process order.
	 *
	 * @param \MemberOrder $order Member Order.
	 * @return bool
	 */
	public function process( &$order ) {
		if ( empty( $order->code ) ) {
			$order->code = $order->getRandomCode();
		}

		$title = self::pmpro_getOption( 'knit_pay_title' );
		if ( empty( $title ) ) {
			$title = 'Online Payment';
		}

		if ( self::pmpro_getOption( 'knit_pay_hide_billing_address' ) ) {
			$order->billing->country = '';
		}

		// clean up a couple values
		$order->payment_type = $title;

		// just save, the user will go to Knit Pay to pay
		$order->status = 'pending';
		$order->saveOrder();

		return $order->Gateway->sendToGateway( $order ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Gateway is a property of PMPro's MemberOrder class, cannot rename.
	}

	/**
	 * Hide checkout fields.
	 *
	 * @return void
	 */
	public function hide_checkout_fields() {
		$style = '';

		if ( self::pmpro_getOption( 'knit_pay_hide_billing_address' ) ) {
			$style .= '.pmpro_form_field.pmpro_form_field-baddress1,';
			$style .= '.pmpro_form_field.pmpro_form_field-baddress2,';
			$style .= '.pmpro_form_field.pmpro_form_field-bcity,';
			$style .= '.pmpro_form_field.pmpro_form_field-bstate,';
			$style .= '.pmpro_form_field.pmpro_form_field-bzipcode,';
			$style .= '.pmpro_form_field.pmpro_form_field-bcountry,';
		}

		if ( self::pmpro_getOption( 'knit_pay_hide_phone' ) ) {
			$style .= '.pmpro_form_field.pmpro_form_field-bphone,';
		}

		$style = rtrim( $style, ',' );

		if ( ! empty( $style ) ) {
			$style = '<style>' . $style . '{display: none;}</style>';
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $style is composed solely of hardcoded CSS selector strings, containing no user input or dynamic data.
		echo $style;
	}

	/**
	 * Get PMPro option.
	 *
	 * @param string $s     Option name.
	 * @param bool   $force Force.
	 * @return mixed
	 */
	private static function pmpro_getOption( $s, $force = false ) {
		return get_option( 'pmpro_' . $s, '' );
	}

	/**
	 * Save configuration IDs.
	 *
	 * @return void
	 */
	public function save_config_ids() {
		// Nonce verification is handled by PMPro core in adminpages/paymentsettings.php
		// before this hook is called. All PMPro gateway classes follow this pattern.

		if ( isset( $_POST['knit_pay_config_id'] ) ) {
			$value = array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['knit_pay_config_id'] ) );

			update_option( 'pmpro_knit_pay_config_id', $value );
		}
	}

	/**
	 * Checkout boxes for multiple gateways.
	 *
	 * @return void
	 */
	public function pmpro_checkout_boxes_multi_gateway() {
		$knit_pay_selected_config_id = self::pmpro_getOption( 'knit_pay_config_id' );
		if ( ! ( is_array( $knit_pay_selected_config_id ) && count( $knit_pay_selected_config_id ) > 1 ) ) {
			return;
		}

		?>
		<fieldset id="pmpro_payment_method" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_fieldset', 'pmpro_payment_method' ) ); ?>">
			<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card' ) ); ?>">
				<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card_content' ) ); ?>">
					<legend class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_legend' ) ); ?>">
						<h2 class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_heading pmpro_font-large' ) ); ?>">
							<?php esc_html_e( 'Choose Your Payment Method', 'knit-pay-lang' ); ?>
						</h2>
					</legend>
					<input type="hidden" id="knit_pay_config_id" name="knit_pay_config_id" value="" />
					<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_fields' ) ); ?>">
						<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_field pmpro_form_field-radio' ) ); ?>">
							<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_field-radio-items' ) ); ?>">
								<?php
								$knit_pay_gateways = Plugin::get_config_select_options();
								foreach ( $knit_pay_selected_config_id as $config_id ) {
									?>
									<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_field pmpro_form_field-radio-item' ) ); ?> gateway_knit_pay">
										<input type="radio" id="knit_pay_<?php echo esc_attr( $config_id ); ?>" name="gateway" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_input pmpro_form_input-radio' ) ); ?>" value="knit_pay" onclick="document.getElementById('knit_pay_config_id').value='<?php echo esc_attr( $config_id ); ?>'" required />
										<label for="knit_pay_<?php echo esc_attr( $config_id ); ?>" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_label pmpro_form_label-inline pmpro_clickable' ) ); ?>">
											<?php
											if ( isset( $knit_pay_gateways[ $config_id ] ) ) {
												echo esc_html( $knit_pay_gateways[ $config_id ] );
											}
											?>
										</label>
									</div> <!-- end pmpro_form_field pmpro_form_field-radio-item -->
								<?php } ?>
							</div> <!-- end pmpro_form_field-radio-items -->
						</div> <!-- end pmpro_form_field pmpro_form_field-radio -->
					</div> <!-- end pmpro_form_fields -->
				</div> <!-- end pmpro_card_content -->
			</div> <!-- end pmpro_card -->
		</fieldset> <!-- end pmpro_payment_method -->
		<?php
	}
}
