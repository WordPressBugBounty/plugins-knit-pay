<?php
namespace BooklyKnitPay\Lib\Payment;

use Bookly\Lib as BooklyLib;
use Bookly\Frontend\Modules\Payment\Request;
use KnitPay\Extensions\BooklyPro\Helper;
use Pronamic\WordPress\Money\Currency;
use Pronamic\WordPress\Money\Money;
use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Class KnitPay Gateway
 */
class KnitPayGateway  extends BooklyLib\Base\Gateway {
	protected $type = '';
	
	public function __construct( $gateway, Request $request ) {
		$this->type = $gateway;
		parent::__construct( $request );
	}

	/**
	 * @inerhitDoc
	 */
	protected function createGatewayIntent() {
		$bookly_payment_method = $this->type;
		$config_id             = get_option( 'bookly_' . $bookly_payment_method . '_config_id' );

		// Use default gateway if no configuration has been set.
		if ( empty( $config_id ) ) {
			$config_id = get_option( 'pronamic_pay_config_id' );
		}

		$gateway = Plugin::get_gateway( $config_id );

		if ( ! $gateway ) {
			return false;
		}
		
		$cart_info      = $this->request->getCartInfo();
		$userData       = $this->request->getUserData();
		$form_id        = $this->request->getFormId();
		$bookly_payment = $this->payment;

		$knit_pay_payment_method        = $bookly_payment_method;
		$knit_pay_payment_method_prefix = 'knit_pay_';
		if ( substr( $knit_pay_payment_method, 0, strlen( $knit_pay_payment_method_prefix ) ) === $knit_pay_payment_method_prefix ) {
			$knit_pay_payment_method = substr( $knit_pay_payment_method, strlen( $knit_pay_payment_method_prefix ) );
		}

		/**
		 * Build payment.
		 */
		$payment = new Payment();

		$payment->source    = 'bookly-pro';
		$payment->source_id = $bookly_payment->getId();

		$payment->order_id = $bookly_payment->getId();


		$payment->set_description( Helper::get_description( $bookly_payment_method, $form_id, $userData, $bookly_payment ) );

		$payment->title = Helper::get_title( $userData );

		// Customer.
		$payment->set_customer( Helper::get_customer( $userData ) );

		// Address.
		$payment->set_billing_address( Helper::get_address( $userData ) );

		// Currency.
		$currency = Currency::get_instance( BooklyLib\Config::getCurrency() );

		// Amount.
		$payment->set_total_amount( new Money( $cart_info->getGatewayAmount(), $currency ) );

		// Method.
		$payment->set_payment_method( $knit_pay_payment_method );

		// Configuration.
		$payment->config_id = $config_id;

		try {
			$payment = Plugin::start_payment( $payment );

			$payment->set_meta( 'bookly_response_url', $this->getResponseUrl( self::EVENT_RETRIEVE ) );
			$payment->save();
			
			return [
				'ref_id'     => $payment->get_id(),
				'target_url' => $payment->get_pay_redirect_url(),
			];
		} catch ( \Exception $e ) {
			throw new \Exception( $e->getMessage() );
		}
	}
	
	public function retrieveStatus() {
		$payment = get_pronamic_payment( $this->getPayment()->getRefId() );
		
		if ( null === $payment ) {
			exit;
		}
		
		switch ( $payment->get_status() ) {
			case Core_Statuses::CANCELLED:
			case Core_Statuses::EXPIRED:
			case Core_Statuses::FAILURE:
				return self::STATUS_FAILED;
				
				break;
			case Core_Statuses::SUCCESS:
				return self::STATUS_COMPLETED;
				
			case Core_Statuses::OPEN:
			default:
				return self::STATUS_PROCESSING;
		}
		
		return self::STATUS_PROCESSING;
	}

	/**
	 * @inerhitDoc
	 */
	protected function getInternalMetaData() {
		return [];
	}

	protected function getCheckoutUrl( array $intent_data ) {
		return $intent_data['target_url'];
	}
}
