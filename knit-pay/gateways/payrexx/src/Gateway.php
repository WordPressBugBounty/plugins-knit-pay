<?php
namespace KnitPay\Gateways\Payrexx;

use Pronamic\WordPress\Pay\Core\Gateway as Core_Gateway;
use Pronamic\WordPress\Pay\Payments\Payment;
use Exception;

/**
 * Title: Payrexx Gateway
 * Copyright: 2020-2024 Knit Pay
 *
 * @author Knit Pay
 * @version 8.82.0.0
 * @since 8.82.0.0
 */
class Gateway extends Core_Gateway {
	/**
	 * Initializes an Payrexx gateway
	 *
	 * @param Config $config
	 *            Config.
	 */
	public function init( Config $config ) {
		$this->config = $config;

		$this->set_method( self::METHOD_HTTP_REDIRECT );

		// Supported features.
		$this->supports = [
			'payment_status_request',
		];
		
		$this->payrexx = new \Payrexx\Payrexx( $config->instance, $config->api_key );
	}

	/**
	 * Get available payment methods.
	 *
	 * @return array<int, string>
	 * @see Core_Gateway::get_available_payment_methods()
	 */
	public function get_available_payment_methods() {
		return $this->get_supported_payment_methods();
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
		$payment->set_transaction_id( $payment->key . '_' . $payment->get_id() );
		
		$customer        = $payment->get_customer();
		$language        = $customer->get_language();
		$billing_address = $payment->get_billing_address();
		
		$gateway = new \Payrexx\Models\Request\Gateway();
		
		// amount multiplied by 100
		$gateway->setAmount( $payment->get_total_amount()->get_minor_units()->format( 0, '.', '' ) );
		
		// VAT rate percentage (nullable)
		// $gateway->setVatRate(7.70);
		
		// Product SKU
		// $gateway->setSku('P01122000');
		
		// currency ISO code
		$gateway->setCurrency( $payment->get_total_amount()->get_currency()->get_alphabetic_code() );
		
		// success and failed url in case that merchant redirects to payment site instead of using the modal view
		$gateway->setSuccessRedirectUrl( $payment->get_return_url() );
		$gateway->setFailedRedirectUrl( $payment->get_return_url() );
		$gateway->setCancelRedirectUrl( $payment->get_return_url() );
		
		// optional: payment service provider(s) to use (see http://developers.payrexx.com/docs/miscellaneous)
		// empty array = all available psps
		$gateway->setPsp( [] );
		// $gateway->setPsp(array(4));
		// $gateway->setPm(['mastercard']);
		
		// optional: whether charge payment manually at a later date (type authorization)
		$gateway->setPreAuthorization( false );
		// optional: if you want to do a pre authorization which should be charged on first time
		// $gateway->setChargeOnAuthorization(true);
		
		// optional: whether charge payment manually at a later date (type reservation)
		$gateway->setReservation( false );
		
		// subscription information if you want the customer to authorize a recurring payment.
		// this does not work in combination with pre-authorization payments.
		// $gateway->setSubscriptionState(true);
		// $gateway->setSubscriptionInterval('P1M');
		// $gateway->setSubscriptionPeriod('P1Y');
		// $gateway->setSubscriptionCancellationInterval('P3M');
		
		// optional: reference id of merchant (e. g. order number)
		$gateway->setReferenceId( $payment->get_transaction_id() );
		$gateway->setValidity( 15 );
		// $gateway->setLookAndFeelProfile('144be481');
		
		// optional: parse multiple products
		// $gateway->setBasket([
		// [
			// 'name' => [
				// 1 => 'Dies ist der Produktbeispielname 1 (DE)',
			// 2 => 'This is product sample name 1 (EN)',
			// 3 => 'Ceci est le nom de l\'échantillon de produit 1 (FR)',
			// 4 => 'Questo è il nome del campione del prodotto 1 (IT)'
			// ],
		// 'description' => [
			// 1 => 'Dies ist die Produktmusterbeschreibung 1 (DE)',
		// 2 => 'This is product sample description 1 (EN)',
		// 3 => 'Ceci est la description de l\'échantillon de produit 1 (FR)',
		// 4 => 'Questa è la descrizione del campione del prodotto 1 (IT)'
		// ],
		// 'quantity' => 1,
		// 'amount' => 100
		// ],
		// [
			// 'name' => [
				// 1 => 'Dies ist der Produktbeispielname 2 (DE)',
			// 2 => 'This is product sample name 2 (EN)',
			// 3 => 'Ceci est le nom de l\'échantillon de produit 2 (FR)',
			// 4 => 'Questo è il nome del campione del prodotto 2 (IT)'
			// ],
		// 'description' => [
			// 1 => 'Dies ist die Produktmusterbeschreibung 2 (DE)',
		// 2 => 'This is product sample description 2 (EN)',
		// 3 => 'Ceci est la description de l\'échantillon de produit 2 (FR)',
		// 4 => 'Questa è la descrizione del campione del prodotto 2 (IT)'
		// ],
		// 'quantity' => 2,
		// 'amount' => 200
		// ]
		// ]);
				
		// optional: add contact information which should be stored along with payment
		// $gateway->addField('title', 'mister');
		$gateway->addField( 'forename', $customer->get_name()->get_first_name() );
		$gateway->addField( 'surname', $customer->get_name()->get_last_name() );
		$gateway->addField( 'company', $customer->get_company_name() );
		$gateway->addField( 'street', $billing_address->get_line_1() );
		$gateway->addField( 'postcode', $billing_address->get_line_2() );
		$gateway->addField( 'place', $billing_address->get_city() );
		$gateway->addField( 'country', $billing_address->get_country_code() );
		$gateway->addField( 'phone', $billing_address->get_phone() );
		$gateway->addField( 'email', $customer->get_email() );
		$gateway->addField( 'date_of_birth', $customer->get_birth_date() );
		// $gateway->addField('terms', '');
		// $gateway->addField('privacy_policy', '');
		/*
		 $gateway->addField('custom_field_1', '123456789', array(
					1 => 'Benutzerdefiniertes Feld (DE)',
					2 => 'Benutzerdefiniertes Feld (EN)',
					3 => 'Benutzerdefiniertes Feld (FR)',
					4 => 'Benutzerdefiniertes Feld (IT)',
		)); */
		
		$gateway->setPurpose( $payment->get_description() );
		
		try {
			$response = $this->payrexx->create( $gateway );
			$payment->set_meta( 'payrexx_gateway_id', $response->getId() );
			
			$payment->set_action_url( str_replace( '?', $language . '/?', $response->getLink() ) );
		} catch ( \Payrexx\PayrexxException $e ) {
			throw new Exception( $e->getMessage() );
		}
	}

	/**
	 * Update status of the specified payment.
	 *
	 * @param Payment $payment
	 *            Payment.
	 */
	public function update_status( Payment $payment ) {
		$gateway = new \Payrexx\Models\Request\Gateway();
		
		$gateway->setId( $payment->get_meta( 'payrexx_gateway_id' ) );

		try {
			$response = $this->payrexx->getOne( $gateway );
		} catch ( \Payrexx\PayrexxException $e ) {
			throw new Exception( $e->getMessage() );
		}

		$gateway_status = Statuses::transform( $response->getStatus() );
		$payment->set_status( $gateway_status );
		$payment->add_note( '<strong>Payrexx Response:</strong><br><pre>' . print_r( $response, true ) . '</pre><br>' );
	}
}
