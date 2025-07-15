<?php

namespace KnitPay\Extensions\Give;

use Pronamic\WordPress\Money\Currency;
use Pronamic\WordPress\Money\Money;
use Pronamic\WordPress\Pay\Core\PaymentMethods;
use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Payments\Payment;
use Give\Framework\PaymentGateways\PaymentGateway;
use Give\Donations\Models\Donation;
use Give\Framework\Exceptions\Primitives\Exception;
use Give\Framework\PaymentGateways\Commands\GatewayCommand;
use Give\Framework\PaymentGateways\Commands\RedirectOffsite;
use Give\Framework\PaymentGateways\Exceptions\PaymentGatewayException;
use Give\Helpers\Language;
use Pronamic\WordPress\Pay\Extensions\Give\Gateway as ParentGateway;
use Pronamic\WordPress\Pay\Extensions\Give\GiveHelper;
use Give\Donations\ValueObjects\DonationStatus;
use Give\Donations\Models\DonationNote;

/**
 * Title: Give Gateway
 * Description:
 * Copyright: 2020-2025 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   8.96.23.0
 * @version 8.96.23.0
 */
class Gateway extends PaymentGateway {
	public static function id(): string {
		$class = get_called_class();
		
		if ( preg_match( '/Gateway_([A-Za-z0-9_]+)/', $class, $matches ) ) {
			return 'pronamic_pay_' . $matches[1];
		}

		return 'pronamic_pay';
	}

	public function getId(): string {
		return self::id();
	}

	public function getName(): string {
		$name = PaymentMethods::get_name( self::id() );
		return __( 'Knit Pay', 'give' ) . ' - ' . $name;
	}

	public function getPaymentMethodLabel(): string {
		$name = PaymentMethods::get_name( self::id() );
		return esc_html__( 'Knit Pay', 'give' ) . ' - ' . esc_html( $name );
	}

	/**
	 * Create a payment with gateway
	 * Note: You can use "givewp_create_payment_gateway_data_{$gatewayId}" filter hook to pass additional data for gateway which helps/require to process transaction.
	 *       This filter will help to add additional arguments to this function which should be optional otherwise you will get PHP fatal error.
	 *
	 * @param Donation $donation
	 * @param array    $gatewayData
	 *
	 * @return GatewayCommand|RedirectOffsite|void
	 *
	 * @throws PaymentGatewayException
	 * @throws Exception
	 */
	public function createPayment( Donation $donation, $gatewayData = [] ): RedirectOffsite {
		$parent_gateway = new ParentGateway();

		$payment_method = $this->getId();
		if ( strpos( $payment_method, 'pronamic_pay_' ) === 0 ) {
			$payment_method = substr( $payment_method, strlen( 'pronamic_pay_' ) );
		}

		// Record the pending payment.
		$donation_id = $donation->id;

		$config_id = $this->get_config_id();

		$gateway = Plugin::get_gateway( $config_id );

		if ( null === $gateway ) {
			return new RedirectOffsite( '' );
		}

		$user_info = \give_get_payment_meta_user_info( $donation_id );

		/**
		 * Build payment.
		 */
		$payment = new Payment();

		$payment->source    = 'give';
		$payment->source_id = $donation_id;
		$payment->order_id  = get_post( $donation_id )->post_title;

		$payment->set_description( GiveHelper::get_description( $parent_gateway, $payment->order_id ) );

		$payment->title = GiveHelper::get_title( $payment->order_id );

		// Customer.
		$payment->set_customer( GiveHelper::get_customer_from_user_info( $user_info, $donation_id ) );

		// Address.
		$payment->set_billing_address( GiveHelper::get_address_from_user_info( $user_info, $donation_id ) );

		// Currency.
		$currency = Currency::get_instance( \give_get_payment_currency_code( $donation_id ) );

		// Amount.
		$payment->set_total_amount( new Money( \give_donation_amount( $donation_id ), $currency ) );

		// Payment method.
		$payment->set_payment_method( $payment_method );

		// Configuration.
		$payment->config_id = $config_id;

		// Start.
		try {
			$payment = Plugin::start_payment( $payment );

			$payment->set_meta( 'gateway_data', $gatewayData );
			$payment->save();

			return new RedirectOffsite( $payment->get_pay_redirect_url() );
		} catch ( \Exception $e ) {
			// Step 4: If an error occurs, you can update the donation status to something appropriate like failed, and finally throw the PaymentGatewayException for the framework to catch the message.
			$errorMessage = $e->getMessage();

			$donation->status = DonationStatus::FAILED();
			$donation->save();

			DonationNote::create(
				[
					'donationId' => $donation->id,
					'content'    => sprintf( esc_html__( 'Donation failed. Reason: %s', 'example-give' ), $errorMessage ),
				]
			);

			throw new PaymentGatewayException( $errorMessage );
		}
	}

	/**
	 * @since 2.20.0
	 * @inerhitDoc
	 * @throws Exception
	 */
	public function refundDonation( Donation $donation ) {
		throw new Exception( 'Method has not been implemented yet. Please use the legacy method in the meantime.' );
	}

	// @see https://givewp.com/documentation/developers/how-to-build-a-gateway-add-on-for-givewp/#adding-support-for-option-based-forms
	public function getLegacyFormFieldMarkup( int $formId, array $args ): string {
		return 'You will be redirected to the payment gateway to complete the donation!';
	}

	public function enqueueScript( int $formId ) {
		$handle = $this::id();

		// Enqueue a minimal/blank script file to ensure the handle exists for wp_add_inline_script.
		wp_enqueue_script(
			$handle,
			plugins_url( 'js/knitpay-gateway-blank.js', dirname( __FILE__ ) ),
			[ 'react', 'wp-element' ],
			'1.0.0',
			true
		);

		// Inline script to alert after the script loads.
		$inline_script = "window.givewp.gateways.register({
			id: '$handle',
			Fields() {
				return window.wp.element.createElement('span',null,'You will be redirected to the payment gateway to complete the donation!');
			},
		});";
		wp_add_inline_script(
			$handle,
			$inline_script,
			'after'
		);

		Language::setScriptTranslations( $handle );
	}

	protected function get_config_id() {
		$config_id = give_get_option( sprintf( 'give_%s_configuration', $this->getId() ) );

		if ( empty( $config_id ) ) {
			// Use default gateway if no configuration has been set.
			$config_id = get_option( 'pronamic_pay_config_id' );
		}

		return $config_id;
	}
}
