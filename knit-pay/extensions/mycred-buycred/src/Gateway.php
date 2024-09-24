<?php
namespace KnitPay\Extensions\MycredBuycred;

use Pronamic\WordPress\Money\Currency;
use Pronamic\WordPress\Money\Money;
use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Core\PaymentMethods;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;
use myCRED_Payment_Gateway;


if ( ! defined( 'myCRED_VERSION' ) ) {
	exit();
}

/**
 * Title: myCRED buyCRED Gateway
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   3.5.0
 */

class Gateway extends myCRED_Payment_Gateway {


	/**
	 * Construct
	 */
	public function __construct( $gateway_prefs = [] ) {
		$types            = mycred_get_types();
		$default_exchange = [];
		foreach ( $types as $type => $label ) {
			$default_exchange[ $type ] = 1;
		}

		parent::__construct(
			[
				'id'               => 'knit_pay',
				'label'            => 'Knit Pay',
				'gateway_logo_url' => '',
				'defaults'         => [
					'title'               => PaymentMethods::get_name( 'knit_pay', __( 'Knit Pay', 'knit-pay-lang' ) ),
					'payment_description' => 'Purchase of myCRED - {transaction_id}',
					'logo_url'            => '',
					'config_id'           => get_option( 'pronamic_pay_config_id' ),
					'currency'            => 'INR',
					'exchange'            => $default_exchange,
				],
			],
			$gateway_prefs
		);

		add_filter( 'mycred_buycred_sort_gateways', [ $this, 'mycred_buycred_sort_gateways' ] );
	}

	public function mycred_buycred_sort_gateways( $gateways ) {
		$gateways[ $this->id ]['title'] = $this->prefs['title'];
		return $gateways;
	}

	/**
	 * Prep Sale
	 *
	 * @since 1.8
	 * @version 1.0
	 */
	public function prep_sale( $new_transaction = false ) {

		$payment = get_pronamic_payment_by_meta( '_pronamic_payment_source_id', $this->transaction_id );
		if ( ! is_null( $payment ) && PaymentStatus::SUCCESS !== $payment->get_status() ) {
			$this->redirect_to = $payment->get_pay_redirect_url();
			return;
		}

		$config_id      = $this->prefs['config_id'];
		$payment_method = $this->id;

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

		$payment->source    = 'mycred-buycred';
		$payment->source_id = $this->transaction_id;
		$payment->order_id  = $this->transaction_id;

		$payment->set_description( Helper::get_description( $this->prefs, $this ) );

		$payment->title = Helper::get_title( $payment->source_id );

		// Customer.
		$payment->set_customer( Helper::get_customer( $this ) );

		// Address.
		$payment->set_billing_address( Helper::get_address( $this ) );

		// Currency.
		$currency = Currency::get_instance( $this->currency );

		// Amount.
		$payment->set_total_amount( new Money( $this->cost, $currency ) );

		// Method.
		$payment->set_payment_method( $payment_method );

		// Configuration.
		$payment->config_id = $config_id;

		try {
			$payment = Plugin::start_payment( $payment );

			$this->redirect_to = $payment->get_pay_redirect_url();
		} catch ( \Exception $e ) {
			exit;
			// TODO

			learn_press_add_message( Plugin::get_default_error_message() . '<br>' . $e->getMessage(), 'error' );

			return [
				'result' => 'fail',
			];
		}

	}

	/**
	 * AJAX Buy Handler
	 *
	 * @since 1.8
	 * @version 1.0
	 */
	public function ajax_buy() {

		// Construct the checkout box content
		$content  = $this->checkout_header();
		$content .= $this->checkout_logo();
		$content .= $this->checkout_order();
		$content .= $this->checkout_cancel();
		$content .= $this->checkout_footer();

		// Return a JSON response
		$this->send_json( $content );
	}

	/**
	 * Checkout Page Body
	 *
	 * @since 1.8
	 * @version 1.0
	 */
	public function checkout_page_body() {

		echo $this->checkout_header();
		echo $this->checkout_logo();

		echo $this->checkout_order();
		echo $this->checkout_cancel();

		echo $this->checkout_footer();

	}

	/**
	 * Preferences
	 *
	 * @since 1.0
	 * @version 1.0
	 */
	public function preferences() {
		$prefs = $this->prefs;

		?>
<div class="row">
	<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
		<h3><?php _e( 'Details', 'mycred' ); ?></h3>
		<div class="form-group">
			<label for="<?php echo $this->field_id( 'title' ); ?>"><?php _e( 'Title', 'knit-pay-lang' ); ?></label>
			<input type="text" name="<?php echo $this->field_name( 'title' ); ?>" id="<?php echo $this->field_id( 'title' ); ?>" value="<?php echo esc_attr( $prefs['title'] ); ?>" class="form-control" />
		</div>
		<div class="form-group">
			<label for="<?php echo $this->field_id( 'payment_description' ); ?>"><?php _e( 'Payment Description', 'knit-pay-lang' ); ?></label>
			<input type="text" name="<?php echo $this->field_name( 'payment_description' ); ?>" id="<?php echo $this->field_id( 'payment_description' ); ?>" value="<?php echo esc_attr( $prefs['payment_description'] ); ?>" class="form-control" />
			<small><?php echo sprintf( __( 'Available tags: %s', 'knit-pay-lang' ), sprintf( '<code>%s</code>', '{transaction_id}, {point_count}' ) ); ?></small>
		</div>
		<div class="form-group">
			<label for="<?php echo $this->field_id( 'config_id' ); ?>"><?php _e( 'Configuration', 'knit-pay-lang' ); ?></label>

			<?php $this->get_payment_gateway_configurations(); ?>

		</div>
		<div class="form-group">
			<label for="<?php echo $this->field_id( 'logo_url' ); ?>"><?php _e( 'Logo URL', 'mycred' ); ?></label>
			<input type="text" name="<?php echo $this->field_name( 'logo_url' ); ?>" id="<?php echo $this->field_id( 'logo_url' ); ?>" value="<?php echo esc_attr( $prefs['logo_url'] ); ?>" class="form-control" />
		</div>
	</div>
	<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
		<h3><?php _e( 'Setup', 'mycred' ); ?></h3>
		<div class="form-group">
			<label for="<?php echo $this->field_id( 'currency' ); ?>"><?php _e( 'Currency', 'mycred' ); ?></label>

			<?php $this->currencies_dropdown( 'currency', 'mycred-gateway-knit_pay-currency' ); ?>

		</div>
		<div class="form-group">
			<label><?php _e( 'Exchange Rates', 'mycred' ); ?></label>

			<?php $this->exchange_rate_setup(); ?>

		</div>
	</div>
</div>
		<?php
	}

	/**
	 * Sanatize Prefs
	 *
	 * @since 1.0
	 * @version 1.0
	 */
	public function sanitise_preferences( $data ) {
		$new_data = [];

		$new_data['title']               = sanitize_text_field( $data['title'] );
		$new_data['payment_description'] = sanitize_text_field( $data['payment_description'] );
		$new_data['config_id']           = sanitize_text_field( $data['config_id'] );

		$new_data['logo_url'] = sanitize_text_field( $data['logo_url'] );
		$new_data['currency'] = sanitize_text_field( $data['currency'] );

		// If exchange is less then 1 we must start with a zero
		if ( isset( $data['exchange'] ) ) {
			foreach ( (array) $data['exchange'] as $type => $rate ) {
				if ( $rate != 1 && in_array(
					substr( $rate, 0, 1 ),
					[
						'.',
						',',
					]
				) ) {
					$data['exchange'][ $type ] = (float) '0' . $rate;
				}
			}
		}
		$new_data['exchange'] = $data['exchange'];

		return $new_data;

	}

	/**
	 * Currencies Dropdown
	 *
	 * @since 0.1
	 * @version 1.0.2
	 */
	private function get_payment_gateway_configurations() {
		$name           = 'config_id';
		$configurations = Plugin::get_config_select_options( $this->id );

		echo '<select name="' . $this->field_name( $name ) . '" id="' . $this->field_id( $name ) . '" >';
		echo '<option value="">' . __( 'Select', 'mycred' ) . '</option>';
		foreach ( $configurations as $key => $name ) {
			echo '<option value="' . $key . '"';
			if ( isset( $this->prefs[ $name ] ) && $this->prefs[ $name ] == $key ) {
				echo ' selected="selected"';
			}
			echo '>' . $name . '</option>';
		}
		echo '</select>';
	}


}
