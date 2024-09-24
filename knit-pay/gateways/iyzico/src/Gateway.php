<?php
namespace KnitPay\Gateways\Iyzico;

use Pronamic\WordPress\Pay\Core\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Payments\Payment;
use WP_Error;


/**
 * Title: Iyzico Gateway
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 5.6.0
 * @since 5.6.0
 */
class Gateway extends Core_Gateway {


	const NAME = 'iyzico';

	/**
	 * Initializes an Iyzico gateway
	 *
	 * @param Config $config
	 *            Config.
	 */
	public function init( Config $config ) {
		$this->set_method( self::METHOD_HTTP_REDIRECT );

		// Supported features.
		$this->supports = [
			'payment_status_request',
		];

		$options = new \Iyzipay\Options();
		$options->setApiKey( $config->api_key );
		$options->setSecretKey( $config->secret_key );
		$options->setBaseUrl( 'https://api.iyzipay.com' );
		if ( self::MODE_TEST === $config->mode ) {
			$options->setBaseUrl( 'https://sandbox-api.iyzipay.com' );
		}

		$this->options = $options;
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
		$billing_address = $payment->get_billing_address();
		if ( null !== $billing_address ) {
			if ( ! empty( $billing_address->get_phone() ) ) {
				$phone = $billing_address->get_phone();
			}
			$address = $billing_address->get_line_1() . ' ' . $billing_address->get_line_2();
			$city    = $billing_address->get_city();
			$country = is_null( $billing_address->get_country() ) ? '' : $billing_address->get_country()->get_code();
			$zipcode = $billing_address->get_postal_code();
		}

		if ( empty( $address ) || ' ' === $address ) {
			$address = 'Address';
		}
		if ( empty( $city ) ) {
			$city = 'City';
		}
		if ( empty( $country ) ) {
			$country = 'Country';
		}

		$amount           = $payment->get_total_amount()->number_format( null, '.', '' );
		$customer         = $payment->get_customer();
		$payment_currency = $payment->get_total_amount()->get_currency()->get_alphabetic_code();

		$transaction_id = $payment->key . '_' . $payment->get_id();

		// create request class
		$request = new \Iyzipay\Request\CreatePayWithIyzicoInitializeRequest();
		$request->setLocale( substr( get_locale(), 0, 2 ) );
		$request->setConversationId( $transaction_id );
		$request->setPrice( $amount );
		$request->setPaidPrice( $amount );
		$request->setCurrency( $payment_currency );
		$request->setBasketId( $transaction_id );
		$request->setPaymentGroup( \Iyzipay\Model\PaymentGroup::PRODUCT );
		$request->setCallbackUrl( $payment->get_return_url() );

		$buyer = new \Iyzipay\Model\Buyer();
		$buyer->setId( $payment->get_id() );
		if ( null !== $customer->get_name() ) {
			$buyer->setName( $customer->get_name()->get_first_name() );
			$buyer->setSurname( $customer->get_name()->get_last_name() );
		}
		if ( ! empty( $phone ) ) {
			$buyer->setGsmNumber( $phone );
		}
		$buyer->setEmail( $customer->get_email() );
		$buyer->setIdentityNumber( '11111111111' );
		$buyer->setLastLoginDate( '2018-07-06 11:11:11' );
		$buyer->setRegistrationDate( '2018-07-06 11:11:11' );
		$buyer->setRegistrationAddress( $address );
		$buyer->setIp( $customer->get_ip_address() );
		$buyer->setCity( $city );
		$buyer->setCountry( $country );
		$buyer->setZipCode( $zipcode );
		$request->setBuyer( $buyer );

		$billingAddress = new \Iyzipay\Model\Address();
		if ( null !== $customer->get_name() ) {
			$buyer_name = substr( trim( ( html_entity_decode( $customer->get_name(), ENT_QUOTES, 'UTF-8' ) ) ), 0, 30 );
			$billingAddress->setContactName( $buyer_name );
		}
		$billingAddress->setCity( $city );
		$billingAddress->setCountry( $country );
		$billingAddress->setAddress( $address );
		$billingAddress->setZipCode( $zipcode );
		$request->setBillingAddress( $billingAddress );

		$item = new \Iyzipay\Model\BasketItem();
		$item->setId( 'ID' );
		$item->setName( $payment->get_description() );
		$item->setCategory1( 'Payment' );
		$item->setItemType( \Iyzipay\Model\BasketItemType::VIRTUAL );
		$item->setPrice( $amount );
		$basketItems[] = $item;
		$request->setBasketItems( $basketItems );

		// make request
		$payWithIyzicoInitialize = \Iyzipay\Model\PayWithIyzicoInitialize::create( $request, $this->options );
		if ( 'failure' === $payWithIyzicoInitialize->getStatus() ) {
			throw new \Exception( $payWithIyzicoInitialize->getErrorMessage() );
		}

		$payment->set_transaction_id( $payWithIyzicoInitialize->getToken() );
		$payment->set_action_url( $payWithIyzicoInitialize->getPayWithIyzicoPageUrl() );
	}

	/**
	 * Update status of the specified payment.
	 *
	 * @param Payment $payment
	 *            Payment.
	 */
	public function update_status( Payment $payment ) {
		// create request class
		$request = new \Iyzipay\Request\RetrievePayWithIyzicoRequest();
		$request->setLocale( substr( get_locale(), 0, 2 ) );
		$request->setToken( $payment->get_transaction_id() );

		// make request
		$payWithIyzico = \Iyzipay\Model\PayWithIyzico::retrieve( $request, $this->options );

		if ( 'failure' === $payWithIyzico->getStatus() ) {
			throw new \Exception( $payWithIyzico->getErrorMessage() );
		}

		$payWithIyzico->setRawResult( '' );

		$note  = '<strong>Iyzico Status:</strong>';
		$note .= '<br>' . print_r( $payWithIyzico, true );

		$payment->set_status( Statuses::transform( $payWithIyzico->getPaymentStatus() ) );
		$payment->set_transaction_id( $payWithIyzico->getPaymentId() );
		$payment->add_note( $note );
	}
}
