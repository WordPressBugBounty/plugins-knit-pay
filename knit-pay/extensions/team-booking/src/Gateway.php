<?php

namespace KnitPay\Extensions\TeamBooking;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

use Pronamic\WordPress\Money\Currency;
use Pronamic\WordPress\Money\Money;
use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Payments\Payment;
use TeamBooking\Database;
use TeamBooking\PaymentGateways;
use TeamBooking\Admin\Framework;
use Exception;
use TeamBooking_PaymentGateways_Settings;
use TeamBooking_ReservationData;
use TeamBooking\Functions;

/**
 * Title: Team Booking Gateway
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   5.1.0
 */
class Gateway implements TeamBooking_PaymentGateways_Settings {

	private $use_gateway;
	private $gateway_id;
	private $title;
	private $payment_description;
	private $config_id;
	private $icon_media_id;

	public function __construct() {
		$this->gateway_id  = 'knit_pay';
		$this->use_gateway = false;
	}

	/**
	 * @return string
	 */
	public function getGatewayId() {
		return $this->gateway_id;
	}

	/**
	 * @return bool
	 */
	public function isActive() {
		return $this->use_gateway;
	}

	/**
	 * @return bool
	 */
	public function isOffsite() {
		return true;
	}

	/**
	 * @return Framework\PanelWithForm
	 */
	public function getBackendSettingsTab() {
		$panel = new Framework\PanelWithForm( null );
		$panel->setAction( 'tbk_save_payments' );
		$panel->setNonce( 'team_booking_options_verify' );
		$panel->addTitleContent( Framework\ElementFrom::content( 'Knit Pay' ) );

		// Use Knit Pay gateway
		$element = new Framework\PanelSettingYesOrNo( __( 'Use Knit Pay gateway', 'team-booking' ) );
		$element->addFieldname( 'gateway_settings[' . $this->gateway_id . '][use_gateway]' );
		$element->setState( $this->use_gateway );
		$element->appendTo( $panel );

		// Title.
		$element = new Framework\PanelSettingText( __( 'Title', 'team-booking' ) );
		$element->addFieldname( 'gateway_settings[' . $this->gateway_id . '][title]' );
		$element->addDescription( __( 'This controls the title which the user sees during checkout.', 'knit-pay-lang' ) );
		$element->addDefaultValue( $this->title );
		$element->appendTo( $panel );

		// Configuration.
		$element = new Framework\PanelSettingSelector( __( 'Configuration', 'team-booking' ) );
		$element->addFieldname( 'gateway_settings[' . $this->gateway_id . '][config_id]' );
		$element->addDescription( 'Configurations can be created in Knit Pay gateway configurations page at "Knit Pay >> Configurations".' );
		foreach ( Plugin::get_config_select_options( $this->payment_method ) as $key => $payment_config ) {
			$element->addOption( $key, $payment_config );
		}
		$element->setSelected( $this->config_id );
		$element->appendTo( $panel );

		// Payment Description.
		$element = new Framework\PanelSettingText( __( 'Payment Description', 'team-booking' ) );
		$element->addFieldname( 'gateway_settings[' . $this->gateway_id . '][payment_description]' );
		$element->addDescription( __( sprintf( __( 'Available tags: %s', 'knit-pay-lang' ), sprintf( '%s', '{reservation_id}, {service_name}' ) ) ) );
		$element->addDefaultValue( $this->payment_description );
		$element->appendTo( $panel );

		// Payment Icon.
		$element = new Framework\PanelSettingInsertMedia( __( 'Payment Icon', 'team-booking' ) );
		$element->addDescription( __( 'Choose an image that will be shown on the payment page.', 'team-booking' ) );
		$element->addFieldname( 'gateway_settings[' . $this->gateway_id . '][icon_media_id]' );
		$element->addDefaultMediaId( $this->icon_media_id );
		$element->appendTo( $panel );

		// Save changes
		$wildcard = new Framework\PanelSettingWildcard( null );
		$element  = new Framework\PanelSaveButton( __( 'Save changes', 'team-booking' ), 'tbk_save_payments' );
		$wildcard->addContent( $element );
		$panel->addElement( $wildcard );

		return $panel;
	}

	/**
	 * @return string
	 */
	public function getPayButton( $item ) {
		$icon_url = wp_get_attachment_image_src( $this->getIconMediaId() );
		if ( is_array( $icon_url ) ) {
			$icon_url = $icon_url[0];
		}
		if ( empty( $icon_url ) ) {
			$icon_url = 'https://plugins.svn.wordpress.org/knit-pay/assets/icon.svg';
		}

		ob_start();
		?>
		<div class="tb-icon tbk-button tbk-pay-button" data-offsite="<?php echo $this->isOffsite(); ?>"
			 data-gateway="<?php echo $this->gateway_id; ?>" tabindex="0">
			
			<img style="height: 40px;width: 47.2px;" src="<?php echo $icon_url; ?>">

			<div class="tbk-content">
				<?php echo sprintf( __( 'Pay with %s', 'knit-pay-lang' ), $this->getTitle() ); ?>
			</div>
		</div>

		<?php
		return ob_get_clean();
	}

	/**
	 * @return string
	 */
	public function getLabel() {
		return __( 'via Knit Pay', 'knit-pay-lang' );
	}

	/**
	 * @param TeamBooking_ReservationData[] $items
	 * @param null                          $additional_parameter
	 *
	 * @return string
	 * @throws Exception
	 */
	public function prepareGateway( array $items, $additional_parameter = null ) {
		$config_id      = $this->getConfigID();
		$payment_method = 'knit_pay'; // TODO

		// Use default gateway if no configuration has been set.
		if ( empty( $config_id ) ) {
			$config_id = get_option( 'pronamic_pay_config_id' );
		}

		$gateway = Plugin::get_gateway( $config_id );

		if ( ! $gateway ) {
			return false;
		}

		$amount = 0;
		foreach ( $items as $item ) {
			$amount += $item->getPriceIncremented() * $item->getTickets();
		}
		$reservation_data = reset( $items );

		/**
		 * Build payment.
		 */
		$payment = new Payment();

		$payment->source    = 'team-booking';
		$payment->source_id = $reservation_data->getDatabaseId();
		$payment->order_id  = $reservation_data->getDatabaseId();

		$payment->set_description( Helper::get_description( $this, $reservation_data ) );

		$payment->title = Helper::get_title( $reservation_data->getDatabaseId() );

		// Customer.
		$payment->set_customer( Helper::get_customer( $reservation_data ) );

		// Address.
		$payment->set_billing_address( Helper::get_address( $reservation_data ) );

		// Currency.
		$currency = Currency::get_instance( $reservation_data->getCurrencyCode() );

		// Amount.
		$payment->set_total_amount( new Money( $amount, $currency ) );

		// Method.
		$payment->set_payment_method( $payment_method );

		// Configuration.
		$payment->config_id = $config_id;

		try {
			$payment = Plugin::start_payment( $payment );

			// Saving Reservation token.
			$payment->set_meta( 'team_booking_reservation_order_id', $additional_parameter );
			$payment->save();

			// Execute a redirect.
			return $payment->get_pay_redirect_url();
		} catch ( \Exception $e ) {
			echo $e->getMessage();
			exit;
		}
	}

	/**
	 * @param array  $items
	 * @param string $order_redirect
	 *
	 * @return bool
	 */
	public function getDataForm( $items, $order_redirect ) {
		return false;
	}

	/**
	 * @param array  $settings
	 * @param string $new_currency_code
	 */
	public function saveBackendSettings( array $settings, $new_currency_code ) {
		isset( $settings['use_gateway'] ) ? $this->setUseGateway( $settings['use_gateway'] ) : $this->setUseGateway( false );
		empty( $settings['title'] ) ? $this->setTitle( 'Online Payment' ) : $this->setTitle( $settings['title'] );
		isset( $settings['config_id'] ) ? $this->setConfigID( $settings['config_id'] ) : $this->setConfigID( get_option( 'pronamic_pay_config_id' ) );
		empty( $settings['payment_description'] ) ? $this->setPaymentDescription( 'Team Booking Reservation: {reservation_id}' ) : $this->setPaymentDescription( $settings['payment_description'] );
		isset( $settings['icon_media_id'] ) ? $this->setIconMediaId( $settings['icon_media_id'] ) : $this->setIconMediaId( '' );
	}

	/**
	 * @param string $code
	 *
	 * @return bool
	 */
	public function verifyCurrency( $code ) {
		return true;
	}

	/**
	 * @param array $ipn_data
	 */
	public function listenerIPN( $ipn_data ) {
	}

	/**
	 * @return string
	 */
	public function get_json() {
		$encoded = wp_json_encode( get_object_vars( $this ) );
		if ( $encoded ) {
			return $encoded;
		}

		return '[]';
	}

	/**
	 * @param string $json
	 */
	public function inject_json( $json ) {
		$array = json_decode( $json, true );
		if ( ! [] ) {
			$array = [];
		}
		if ( isset( $array['use_gateway'] ) ) {
			$this->setUseGateway( $array['use_gateway'] );
		}
		if ( isset( $array['title'] ) ) {
			$this->setTitle( $array['title'] );
		}
		if ( isset( $array['config_id'] ) ) {
			$this->setConfigID( $array['config_id'] );
		}
		if ( isset( $array['icon_media_id'] ) ) {
			$this->setIconMediaId( $array['icon_media_id'] );
		}
	}

	/**
	 * @param $bool
	 */
	public function setUseGateway( $bool ) {
		$this->use_gateway = (bool) $bool;
	}

	/**
	 * @param string $title
	 */
	public function setTitle( $title ) {
		$this->title = $title;
	}

	/**
	 * @return string
	 */
	public function getTitle() {
		return trim( $this->title );
	}

	/**
	 * @param string $email
	 */
	public function setPaymentDescription( $payment_description ) {
		 $this->payment_description = $payment_description;
	}

	/**
	 * @return string
	 */
	public function getPaymentDescription() {
		return trim( $this->payment_description );
	}

	/**
	 * @param string $email
	 */
	public function setConfigID( $config_id ) {
		 $this->config_id = $config_id;
	}

	/**
	 * @return string
	 */
	public function getConfigID() {
		 return $this->config_id;
	}

	/**
	 * @param $id
	 */
	public function setIconMediaId( $id ) {
		 $this->icon_media_id = $id;
	}

	/**
	 * @return mixed
	 */
	public function getIconMediaId() {
		return $this->icon_media_id;
	}
}
