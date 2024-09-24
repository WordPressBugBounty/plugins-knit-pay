<?php

namespace KnitPay\Gateways\UpiQR;

use Pronamic\WordPress\Pay\AbstractGatewayIntegration;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;
use WP_Query;

/**
 * Title: UPI QR Integration
 * Copyright: 2020-2024 Knit Pay
 *
 * @author  Knit Pay
 * @version 1.0.0
 * @since   4.1.0
 */
class Integration extends AbstractGatewayIntegration {
	const HIDE_FIELD          = '0';
	const SHOW_FIELD          = '1';
	const SHOW_REQUIRED_FIELD = '2';

	/**
	 * Construct UPI QR integration.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'id'       => 'upi-qr',
				'name'     => 'UPI QR (Unstable)',
				'url'      => 'https://www.knitpay.org/',
				'provider' => 'upi-qr',
			]
		);

		parent::__construct( $args );

		// Add Ajax listener.
		add_action( 'wp_ajax_nopriv_knit_pay_upi_qr_payment_status_check', [ $this, 'ajax_payment_status_check' ] );
		add_action( 'wp_ajax_knit_pay_upi_qr_payment_status_check', [ $this, 'ajax_payment_status_check' ] );

		// Show notice if Knit Pay UPI supported.
		add_action( 'admin_notices', [ $this, 'knit_pay_upi_supported_notice' ] );
	}

	/**
	 * Admin notices.
	 *
	 * @return void
	 */
	public function knit_pay_upi_supported_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( 'upi-qr' !== $this->id ) {
			return;
		}

		// Show Notice once a week on random day.
		$random_day  = get_transient( 'knit_pay_upi_supported_rand_day' );
		$current_day = gmdate( 'N' );
		if ( empty( $random_day ) ) {
			$random_day = strval( wp_rand( 1, 7 ) );
			set_transient( 'knit_pay_upi_supported_rand_day', $random_day, 2 * WEEK_IN_SECONDS );
		}
		if ( $random_day !== $current_day ) {
			return;
		}

		// Show notification.
		$config_ids = get_transient( 'knit_pay_upi_supported_configs' );
		if ( empty( $config_ids ) ) {
			// Get gateways for which a API keys getting used.
			$query = new WP_Query(
				[
					'post_type'  => 'pronamic_gateway',
					'orderby'    => 'post_title',
					'order'      => 'ASC',
					'fields'     => 'ids',
					'nopaging'   => true,
					'meta_query' => [
						'relation' => 'AND',
						[
							'key'     => '_pronamic_gateway_id',
							'value'   => 'upi-qr',
							'compare' => '=',
						],
						[
							'key'     => '_pronamic_gateway_upi_qr_vpa',
							'value'   => '^(q.*@ybl|paytmqr.*@paytm|bharatpe.*@[a-z]*)$',
							'compare' => 'REGEXP',
						],
					],
				]
			);

			$config_ids = $query->posts;

			if ( empty( $config_ids ) ) {
				$config_ids = true;
			}

			set_transient( 'knit_pay_upi_supported_configs', $config_ids, DAY_IN_SECONDS );
		}

		if ( ! empty( $config_ids ) ) {
			include __DIR__ . '/view/notice-knit-pay-upi-supported.php';
		}
	}

	/**
	 * Setup gateway integration.
	 *
	 * @return void
	 */
	public function setup() {
		// Display ID on Configurations page.
		\add_filter(
			'pronamic_gateway_configuration_display_value_' . $this->get_id(),
			[ $this, 'gateway_configuration_display_value' ],
			10,
			2
		);
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

		return $config->vpa;
	}

	/**
	 * Get settings fields.
	 *
	 * @return array
	 */
	public function get_settings_fields() {
		$fields = [];

		$fields = $this->get_about_settings_fields( $fields );
		
		$fields = $this->get_setup_settings_fields( $fields );

		// Return fields.
		return $fields;
	}

	/**
	 * Get settings fields.
	 *
	 * @return array
	 */
	protected function get_pro_about_settings_fields( $fields ) {
		// Prerequisite.
		$fields[] = [
			'section'  => 'general',
			'type'     => 'custom',
			'title'    => 'Prerequisite',
			'callback' => function () {
				$knit_pay_pro_setup_url = admin_url( 'admin.php?page=knit_pay_pro_setup_page' );
				$link                   = '<a target="_blank" href="' . $knit_pay_pro_setup_url . '">' . __( 'Knit Pay >> Knit Pay Pro Setup', 'knit-pay-upi' ) . '</a>';
				$message                = sprintf( __( 'Please visit the %s page to configure "Knit Pay - Pro".', 'knit-pay-upi' ), $link );

				echo '<ol><li>We rely on the third-party service provider RapidAPI for the setup of Knit Pay UPI. RapidAPI will charge you based on your monthly usage.</li>
                    	<li>Choose a plan that suits your usage. Each plan includes a certain
                    		number of free transactions. If you exceed the transaction quota,
                    		additional charges will apply for receiving extra transactions. <br> <a
                    		target="_blank"
                    		href="https://rapidapi.com/knitpay/api/knit-pay-upi/pricing">https://rapidapi.com/knitpay/api/knit-pay-upi/pricing</a></li>
                        <li>' . $message . '</li>
                    </ol>';
			},
		];

		// Terms and Conditions.
		$fields[] = [
			'section'  => 'general',
			'type'     => 'custom',
			'title'    => 'Terms and Conditions',
			'callback' => function () {
				echo '<ol>
                    <li>We generate QR codes on your behalf using your UPI/VPA ID so that you can accept payments with ease.</li>
                    <li>Knit Pay UPI is not a payment gateway service, and we are not involved in the payment process in any way. Your Merchant Account will be used for collecting payments.</li>
                    <li>We do not collect any of your payments in our account. The payment made by the user through the QR code will be received in your merchant account.</li>
                    <li>We are not liable for any fraudulent activity that takes place with your merchant account.</li>
                    <li>We might also suspend your RapidAPI subscription if any fraudulent activity gets detected.</li>
                    <li>We are not responsible for the suspension of your merchant account due to any reason.</li>
                    <li>Use this plugin at your own risk, we are not liable for any of your losses.</li>
                 </ol>';
			},
		];

		return $fields;
	}

	public function get_about_settings_fields( $fields ) {
		$fields[] = [
			'section'     => 'general',
			'type'        => 'custom',
			'description' => '<h1><strong>Please Note:</strong> This module is highly unstable and your customers might face lots of payment failures while using it. Knit Pay strongly suggests that you integrate UPI using some payment gateway service provider instead of this module. Due to a high number of requests from website owners, we have kept this module active. Kindly use it only if you are ready to face potential risks.</h1>',
		];

		// Steps to Integrate.
		$fields[] = [
			'section'  => 'general',
			'type'     => 'custom',
			'callback' => function () {
				$utm_parameter = '?utm_source=knit-pay&utm_medium=ecommerce-module&utm_campaign=module-admin&utm_content=help-signup';

				echo '<p>' . __( '<strong>Steps to Integrate UPI QR</strong>' ) . '</p>' .

				'<ol>
                <li>Signup at any UPI-enabled App. If you will signup using provided signup URLs and use the referral codes, you might also get a bonus after making few payments.
                    <ul>
                        <li>- <a target="_blank" href="' . $this->get_url() . 'bharatpe' . $utm_parameter . '">BharatPe (' . $this->get_url() . 'bharatpe)</a> - Signup using the referral link (on the phone) to get ₹200 bonus.</li>
                        <li>- <a target="_blank" href="' . $this->get_url() . 'open-money' . $utm_parameter . '">Open Money</a></li>
                        <li>- <a target="_blank" href="' . $this->get_url() . 'gpay' . $utm_parameter . '">Google Pay</a> Referral Code: Z05o0</li>
                        <li>- <a target="_blank" href="' . $this->get_url() . 'phonepe' . $utm_parameter . '">PhonePe</a></li>
                        <li>- <a target="_blank" href="' . $this->get_url() . 'amazon-pay' . $utm_parameter . '">Amazon Pay</a> Referral Code: K1ZESF</li>
                        <li>- <a target="_blank" href="https://play.google.com/store/search?q=merchant%20business%20upi&c=apps">More UPI Apps</a></li>
                    </ul>
                </li>

                <li>Link your Bank Account and generate a UPI ID/VPA.</li>

                <li>Use this VPA/UPI ID on the configuration page below.
                <br><strong>Kindly use the correct VPA/UPI ID. In case of wrong settings, payments will get credited to the wrong bank account. Knit Pay will not be responsible for any of your lose.</strong></li>

                <li>Save the settings.</li>

                <li>Before going live, make a test payment of ₹1 and check that you are receiving this payment in the correct bank account.</li>

                </ol>';
			},
		];

		// How does it work.
		$fields[] = [
			'section'  => 'general',
			'type'     => 'custom',
			'callback' => function () {
				echo '<p>' . __( '<strong>How does it work?</strong>' ) . '</p>' .

				'<ol>
                <li>On the payment screen, the customer scans the QR code using any UPI-enabled mobile app and makes the payment.</li>

                <li>The customer enters the transaction ID and submits the payment form.</li>

                <li>Payment remains on hold. Merchant manually checks the payment and mark it as complete on the "Knit Pay" Payments page.</li>

                <li>Automatic tracking is not available in the UPI QR payment method. You can signup at other supported free payment gateways to get an automatic payment tracking feature.
                    <br><a target="_blank" href="https://www.knitpay.org/indian-payment-gateways-supported-in-knit-pay/">Indian Payment Gateways Supported in Knit Pay</a>
                </li>

            </ol>';},
		];

		// Knit Pay - UPI - News.
		$fields[] = [
			'section'  => 'general',
			'title'    => 'Exciting News!',
			'type'     => 'custom',
			'callback' => function () {
				$message  = '<h1>Exciting News!</h1>';
				$message .= '<br>';
				$message .= 'Recently we have launched another plugin, using which you will no longer have to manually check the payment status of your UPI/QR payments. Knit Pay can now check the payment status automatically for you.';

				$message .= 'Unlock this feature now!<br><br><a class="button button-large button-primary" target="_blank" href="' . admin_url( 'plugin-install.php?s=Knit%2520Pay%2520UPI%2520QR%2520code%2520RapidAPI&tab=search&type=term' ) . '">Click here</a>';

				echo $message;
			},
		];

		return $fields;
	}
	
	public function get_setup_settings_fields( $fields ) {
		// Secret Key.
		$fields['qr_code_scanner'] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_upi_qr_qr_code',
			'title'    => __( 'Upload QR Code', 'knit-pay-lang' ),
			'type'     => 'description',
			'callback' => [ $this, 'field_qr_code_scanner' ],
			'classes'  => [ 'code' ],
			'tooltip'  => __( 'Choose a QR code to automatically retrieve the UPI VPA ID.', 'knit-pay-lang' ),
		];

		// Payee name or business name.
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_upi_qr_payee_name',
			'title'    => __( 'Payee Name or Business Name', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
		];
		
		// UPI VPA ID
		$fields[] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_upi_qr_vpa',
			'title'    => __( 'UPI VPA ID', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'regular-text', 'code' ],
			'tooltip'  => __( 'UPI/VPA ID which you want to use to receive the payment.', 'knit-pay-lang' ),
			'required' => true,
		];
		
		// Template.
		if ( 'upi-qr' !== $this->get_id() ) {
			$fields[] = [
				'section'  => 'general',
				'meta_key' => '_pronamic_gateway_upi_qr_payment_template',
				'title'    => __( 'Payment Template', 'knit-pay-lang' ),
				'type'     => 'select',
				'options'  => $this->get_supported_template_list(),
				'default'  => '0',
			];
		}

		// Merchant category code.
		$fields[] = [
			'section'     => 'general',
			'meta_key'    => '_pronamic_gateway_upi_qr_merchant_category_code',
			'title'       => __( 'Merchant Category Code', 'knit-pay-lang' ),
			'type'        => 'text',
			'classes'     => [ 'regular-text', 'code' ],
			'tooltip'     => __( 'four-digit ISO 18245 merchant category code (MCC) to classify your business.', 'knit-pay-lang' ),
			'description' => 'You can refer to below links to find out your MCC.<br>' .
			'<a target="_blank" href="https://www.citibank.com/tts/solutions/commercial-cards/assets/docs/govt/Merchant-Category-Codes.pdf">Citi Bank - Merchant Category Codes</a><br>' .
			'<a target="_blank" href="https://docs.checkout.com/resources/codes/merchant-category-codes">Checkout.com - Merchant Category Codes</a><br>',
		];
		
		// Payment Instruction.
		$fields['payment_instruction'] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_upi_qr_payment_instruction',
			'title'    => __( 'Payment Instruction', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'large-text', 'code' ],
			'default'  => __( 'Scan the QR Code with any UPI apps like BHIM, Paytm, Google Pay, PhonePe, or any Banking UPI app to make payment for this order. After successful payment, enter the UPI Reference ID or Transaction Number submit the form. We will manually verify this payment against your 12-digits UPI Reference ID or Transaction Number (eg. 001422121258).', 'knit-pay-lang' ),
			'tooltip'  => __( 'It will be displayed to customers while making payment using destop devices.', 'knit-pay-lang' ),
		];
		
		// Payment Instruction.
		$fields['mobile_payment_instruction'] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_upi_qr_mobile_payment_instruction',
			'title'    => __( 'Mobile Payment Instruction', 'knit-pay-lang' ),
			'type'     => 'text',
			'classes'  => [ 'large-text', 'code' ],
			'default'  => __( 'Scan the QR Code with any UPI apps like BHIM, Paytm, Google Pay, PhonePe, or any Banking UPI app to make payment for this order. After successful payment, enter the UPI Reference ID or Transaction Number submit the form. We will manually verify this payment against your 12-digits UPI Reference ID or Transaction Number (eg. 001422121258).', 'knit-pay-lang' ),
			'tooltip'  => __( 'It will be displayed to customers while making payment using mobile devices.', 'knit-pay-lang' ),
		];
		
		// Payment Success Status.
		$fields['payment_success_status'] = [
			'section'     => 'general',
			'meta_key'    => '_pronamic_gateway_upi_qr_payment_success_status',
			'title'       => __( 'Payment Success Status', 'knit-pay-lang' ),
			'type'        => 'select',
			'options'     => [
				PaymentStatus::ON_HOLD => PaymentStatus::ON_HOLD,
				PaymentStatus::OPEN    => __( 'Pending', 'knit-pay-lang' ),
				PaymentStatus::SUCCESS => PaymentStatus::SUCCESS,
			],
			'default'     => PaymentStatus::ON_HOLD,
			'description' => 'Knit Pay does not check if payment is received or not. Kindly deliver the product/service only after cross-checking the payment status with your bank.',
		];
		
		// Transaction ID Field.
		$fields['transaction_id_field'] = [
			'section'  => 'general',
			'meta_key' => '_pronamic_gateway_upi_qr_transaction_id_field',
			'title'    => __( 'Transaction ID Field', 'knit-pay-lang' ),
			'type'     => 'select',
			'options'  => [
				self::HIDE_FIELD          => __( 'Hide Input Field', 'knit-pay-lang' ),
				self::SHOW_FIELD          => __( 'Show Input Field (Not Requied)', 'knit-pay-lang' ),
				self::SHOW_REQUIRED_FIELD => __( 'Show Input Field (Requied)', 'knit-pay-lang' ),
			],
			'default'  => 2,
			'tooltip'  => __( 'If you want to collect UPI Transaction ID from customers, set it from here.', 'knit-pay-lang' ),
		];
		
		// Hide Mobile QR Code.
		$fields['hide_mobile_qr'] = [
			'section'  => 'general',
			'filter'   => FILTER_VALIDATE_BOOLEAN,
			'meta_key' => '_pronamic_gateway_upi_qr_hide_mobile_qr',
			'title'    => __( 'Hide Mobile QR Code', 'knit-pay-lang' ),
			'type'     => 'checkbox',
			'default'  => false,
			'label'    => __( 'Select to Hide QR Code on Mobile.', 'knit-pay-lang' ),
		];
		
		// Hide Payment Button.
		$fields['hide_pay_button'] = [
			'section'     => 'general',
			'filter'      => FILTER_VALIDATE_BOOLEAN,
			'meta_key'    => '_pronamic_gateway_upi_qr_hide_pay_button',
			'title'       => __( 'Hide Payment Button', 'knit-pay-lang' ),
			'type'        => 'checkbox',
			'default'     => true,
			'label'       => __( 'Select to Hide Payment Button on Mobile. (Click here to make the payment)', 'knit-pay-lang' ),
			'description' => __( 'Please Note: Some VPA id does not work with Payment Buttons. Uncheck this option only if your VPA works properly with Payment Buttons.', 'knit-pay-lang' ),
		];
		
		// Show Download QR.
		if ( 'upi-qr' !== $this->get_id() ) {
			$fields['show_download_qr_button'] = [
				'section'     => 'general',
				'meta_key'    => '_pronamic_gateway_upi_qr_show_download_qr_button',
				'title'       => __( 'Show Download QR Button', 'knit-pay-lang' ),
				'type'        => 'select',
				'options'     => [
					'yes' => 'Yes',
					'no'  => 'No',
				],
				'default'     => 'yes',
				'description' => __( 'Show Download QR Button if amount is less than 2000.', 'knit-pay-lang' ),
			];
		}

		return $fields;
	}

	protected function get_supported_template_list() {
		return [
			'0' => 'Default',
			'2' => '2',
			'3' => '3',
		];
	}

	public function get_config( $post_id ) {
		$config = new Config();

		$config->vpa                        = $this->get_meta( $post_id, 'upi_qr_vpa' );
		$config->payment_template           = $this->get_meta( $post_id, 'upi_qr_payment_template' );
		$config->payee_name                 = $this->get_meta( $post_id, 'upi_qr_payee_name' );
		$config->merchant_category_code     = $this->get_meta( $post_id, 'upi_qr_merchant_category_code' );
		$config->payment_instruction        = $this->get_meta( $post_id, 'upi_qr_payment_instruction' );
		$config->mobile_payment_instruction = $this->get_meta( $post_id, 'upi_qr_mobile_payment_instruction' );
		$config->payment_success_status     = $this->get_meta( $post_id, 'upi_qr_payment_success_status' );
		$config->transaction_id_field       = $this->get_meta( $post_id, 'upi_qr_transaction_id_field' );
		$config->hide_mobile_qr             = $this->get_meta( $post_id, 'upi_qr_hide_mobile_qr' );
		$config->hide_pay_button            = $this->get_meta( $post_id, 'upi_qr_hide_pay_button' );
		$config->show_download_qr_button    = $this->get_meta( $post_id, 'upi_qr_show_download_qr_button' );

		if ( empty( $config->payment_template ) ) {
			$config->payment_template = '3';
		}

		if ( empty( $config->payment_success_status ) ) {
			$config->payment_success_status = PaymentStatus::ON_HOLD;
		}

		if ( '' === $config->transaction_id_field ) {
			$config->transaction_id_field = self::SHOW_REQUIRED_FIELD;
		}

		if ( empty( $config->show_download_qr_button ) ) {
			$config->show_download_qr_button = 'yes';
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
		return new Gateway( $this->get_config( $config_id ) );
	}

	/**
	 * Take action when configuration is saved.    
	 *
	 * @param int $config_id The ID of the post being saved.
	 * @return void
	 */
	public function save_post( $config_id ) {
		parent::save_post( $config_id );

		// Delete and recheck supported UPI configs.
		delete_transient( 'knit_pay_upi_supported_configs' );
	}

	/**
	 * Field private key.
	 *
	 * @param array<string, mixed> $field Field.
	 * @return void
	 */
	public function field_qr_code_scanner( $field ) {
		wp_enqueue_script(
			'knit-pay-nuintun-qrcode',
			KNITPAY_URL . '/gateways/upi-qr/src/js/qrcode.js',
			[],
			'3.3.5',
		);

		wp_enqueue_script(
			'knit-pay-upi-qr-admin',
			KNITPAY_URL . '/gateways/upi-qr/src/js/admin.js',
			[ 'knit-pay-nuintun-qrcode' ],
			KNITPAY_VERSION
		);

		?>
		<p>
			<?php

			printf(
				'<label class="pronamic-pay-form-control-file-button button"><span id="upi-file-label">%s</span> <input type="file" name="%s" onchange="KnitPayQRCodeScan(this)" /></label>',
				esc_html__( 'Select UPI QR', 'knit-pay-lang' ),
				'_pronamic_gateway_upi_qr_qr_code',
			);

			printf(
				'<p class="pronamic-pay-description description">%s</p>',
				esc_html__( 'You can either manually fill in the details below or upload the QR code. If you choose to upload the QR code, it will be scanned, and the relevant details will be updated automatically.', 'knit-pay-lang' )
			);
			?>
		</p>
		<?php
	}

	public function ajax_payment_status_check() {
		$payment_id     = isset( $_POST['knit_pay_payment_id'] ) ? sanitize_text_field( $_POST['knit_pay_payment_id'] ) : '';
		$transaction_id = isset( $_POST['knit_pay_transaction_id'] ) ? sanitize_text_field( $_POST['knit_pay_transaction_id'] ) : '';
		$knit_pay_nonce = isset( $_POST['knit_pay_nonce'] ) ? sanitize_text_field( $_POST['knit_pay_nonce'] ) : '';

		$nonce_action = "knit_pay_payment_status_check|{$payment_id}|{$transaction_id}";

		if ( ! wp_verify_nonce( $knit_pay_nonce, $nonce_action ) ) {
			wp_send_json_error( __( 'Nonce Missmatch!', 'knit-pay-lang' ) );
		}

		$payment = get_pronamic_payment( $payment_id );

		if ( null === $payment ) {
			exit;
		}

		$gateway = $payment->get_gateway();
		if ( ! $gateway->supports( 'payment_status_request' ) ) {
			wp_send_json_error( __( 'Gateway does not support automatic payment status check.', 'knit-pay-lang' ) );
		}

		// Update status.
		try {
			$gateway->update_status( $payment );

			// Update payment in data store.
			$payment->save();

			wp_send_json_success( $payment->get_status() );
		} catch ( \Exception $error ) {
			$message = $error->getMessage();

			// Maybe include error code in message.
			$code = $error->getCode();

			if ( $code > 0 ) {
				$message = \sprintf( '%s: %s', $code, $message );
			}

			// Add note.
			$payment->add_note( $message );

			wp_send_json_error( $message );
		}
	}
}
