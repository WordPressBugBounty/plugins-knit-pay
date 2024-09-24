<?php
/**
 * Title: Tour Master extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author knitpay
 * @since 2.1.0
 * @version 8.85.13.0
 * @package KnitPay\Extensions\TourMaster
 */

namespace KnitPay\Extensions\TourMaster;

use Pronamic\WordPress\Money\Currency;
use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Money\Money;
use Pronamic\WordPress\Pay\Payments\Payment;

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit();

/**
 * Tour Master Gateway Class
 *
 * @author Gautam Garg
 */
class Gateway {

	protected $config_id;

	/**
	 *
	 * @var string
	 */
	public $id = 'knit_pay';

	public $name = 'Knit Pay';

	public $payment_method;

	/**
	 * Bootstrap
	 *
	 * @param array $args
	 *            Gateway properties.
	 */
	public function __construct( $id, $name = '', $payment_method = null ) {
		$this->payment_method = $payment_method;
		$this->id             = $id;
		$this->name           = $name;

		// Add Payment Gateway settings in backend.
		add_filter( 'goodlayers_plugin_payment_option', [ $this, 'goodlayers_plugin_payment_option' ] );

		// Redirect to payment gateway if payment method from frontend.
		add_filter( 'goodlayers_' . $this->id . '_payment_form', [ $this, 'knit_pay_redirect_form_tour' ], 10, 2 );
		// TODO Add support for Tour Master Rooms when Rooms mode of tour master becomes stable.
		// add_filter( 'goodlayers_room_' . $this->id . '_payment_form', [ $this, 'knit_pay_redirect_form_room' ], 10, 3 );

		// Add Payment button on front end for Style 1 Tours.
		add_filter( 'tourmaster_additional_payment_method', [ $this, 'tourmaster_additional_payment_method_frontend' ] );

		// Workaround: There is bug in Tourmaster, that it does not add custom payment method in payment page style 2.
		add_action( 'wp_head', [ $this, 'add_payment_method_js' ] );
	}

	public function goodlayers_plugin_payment_option( $options ) {
		// TODO remove this to add support for Tour Master rooms, when rooms module become stable.
		$current_action = isset( $_POST['action'] ) ? sanitize_text_field( $_POST['action'] ) : null;
		if ( 'get_tourmaster_option_tab_tourmaster_room_admin_option' === $current_action ) {
			return $options;
		}

		$options['payment-settings']['options']['payment-method']['options'][ $this->id ] = 'Knit Pay - ' . $this->name;

		$options[ $this->id ] = [
			'title'   => 'Knit Pay - ' . esc_html__( $this->name, 'knit-pay-lang' ),
			'options' => [
				$this->id . '-config-id'           => [
					'title'       => esc_html__( 'Configuration', 'knit-pay-lang' ),
					'type'        => 'combobox',
					'default'     => get_option( 'pronamic_pay_config_id' ),
					'options'     => Plugin::get_config_select_options( $this->payment_method ),
					'description' => __( 'Configurations can be created in Knit Pay gateway configurations page at <a href="' . admin_url() . 'edit.php?post_type=pronamic_gateway">"Knit Pay >> Configurations"</a>.', 'knit-pay-lang' ),
				],
				$this->id . '-payment-description' => [
					'title'       => __( 'Payment Description', 'knit-pay-lang' ),
					'type'        => 'text',
					'default'     => __( 'Tour Master Order {order_id}', 'knit-pay-lang' ),
					'description' => sprintf( __( 'Available tags: %s', 'knit-pay-lang' ), sprintf( '<code>%s</code>', '{order_id}' ) ),
				],
				$this->id . '-payment-icon'        => [
					'title'       => __( 'Payment Icon', 'knit-pay-lang' ),
					'type'        => 'text',
					'description' => __( 'This controls the icon which the user sees during checkout. Keep it blank to use default Icon.', 'knit-pay-lang' ),
				],
			],
		];
		return $options;
	}

	// Workaround: Add Payment Methods in Payment Method Style 2.
	function add_payment_method_js() {
		$payment_page_id = tourmaster_get_option( 'general', 'payment-page' );

		if ( is_page( $payment_page_id ) || empty( $payment_page_id ) ) {
			$option_type = isset( $_GET['pt'] ) && 'room' === sanitize_text_field( $_GET['pt'] ) ? 'room_payment' : 'payment';

			if ( ! $this->is_gateway_enabled( $option_type ) ) {
				return;
			}

			if ( 'knit_pay' === $this->id ) {
				$this->name = 'Pay Online';
			}

			?>
			<script type="text/javascript">
				jQuery(document).on('keyup click mouseenter hover', 'select.tourmaster-payment-selection, div.tourmaster-payment-terms, .tourmaster-button', function(e) {
					var payment_option = jQuery("select.tourmaster-payment-selection option[value=<?php echo $this->id; ?>]");
					if ( payment_option.length == 0 ){
						jQuery('select.tourmaster-payment-selection').append(new Option("<?php echo $this->name; ?>", "<?php echo $this->id; ?>"));
					} else if ( "" == payment_option.text() ){
						payment_option.text("<?php echo $this->name; ?>");
					}
				})
			</script>
			<?php
		}
	}

	public function tourmaster_additional_payment_method_frontend( $ret ) {
		if ( ! $this->is_gateway_enabled( 'payment' ) ) {
			return $ret;
		}

		$image_url = $this->get_icon_url();

		$ret .= '<div class="tourmaster-online-payment-method tourmaster-payment-' . $this->id . '">';
		$ret .= '<img src="' . $image_url . '" alt="' . $this->id . '" data-action-type="' . $this->id . '" ';
		$ret .= 'data-method="ajax" data-action="tourmaster_payment_selected" data-ajax="' . esc_url( TOURMASTER_AJAX_URL ) . '"  />';

		/*
		 $ret .= '<div class="tourmaster-payment-credit-card-type" >';
		$ret .= '<img src="' . esc_attr( TOURMASTER_URL ) . '/images/' . 'visa' . '.png" alt="visa" />';
		$ret .= '<img src="' . esc_attr( TOURMASTER_URL ) . '/images/' . 'master-card' . '.png" alt="master-card" />';
		$ret .= '</div>'; */

		$ret .= '<br><br><br></div>';

		$ret .= '<style>';
		$ret .= '.tourmaster-payment-' . $this->id . '{width: 100%;text-align: center;line-height: 1;}
				.tourmaster-payment-' . $this->id . ' > img {height: 76px;width: 170px;cursor: pointer;border-width: 10px;border-style: solid;border-color: transparent;transition: border-color 400ms;-moz-transition: border-color 400ms;-o-transition: border-color 400ms;-webkit-transition: border-color 400ms;background: white;}';

		$ret .= '</style>';
		return $ret;
	}

	private function get_icon_url() {
		$jpg_file_name      = '/icon-170x76.jpg';
		$png_file_name      = '/icon-51x32@4x.png';
		$svg_file_name      = '/icon.svg';
		$file_relative_path = '/images/' . str_replace( '_', '-', $this->payment_method );
		$image_file         = KNITPAY_DIR . $file_relative_path;
		$option_type        = 'payment';

		$payment_icon = tourmaster_get_option( $option_type, $this->id . '-payment-icon' );
		if ( ! empty( $payment_icon ) ) {
			return $payment_icon;
		}

		if ( file_exists( $image_file . $svg_file_name ) ) {
			return esc_attr( KNITPAY_URL ) . $file_relative_path . $svg_file_name;
		}

		if ( file_exists( $image_file . $jpg_file_name ) ) {
			return esc_attr( KNITPAY_URL ) . $file_relative_path . $jpg_file_name;
		}

		if ( file_exists( $image_file . $png_file_name ) ) {
			return esc_attr( KNITPAY_URL ) . $file_relative_path . $png_file_name;
		}

		return 'https://plugins.svn.wordpress.org/knit-pay/assets/icon.svg';
	}

	public function knit_pay_redirect_form_tour( $ret = '', $tid = '' ) {
		return $this->knit_pay_redirect_form( 'payment', $ret, $tid );
	}

	public function knit_pay_redirect_form_room( $ret = '', $tid = '' ) {
		return $this->knit_pay_redirect_form( 'room_payment', $ret, $tid );
	}

	private function knit_pay_redirect_form( $option_type, $ret = '', $tid = '' ) {
		// Gateway.
		$gateway = Plugin::get_gateway( $this->get_config_id( $option_type ) );

		if ( empty( $gateway ) ) {
			return $ret;
		}

		$booking_data = \tourmaster_get_booking_data( [ 'id' => $tid ], [ 'single' => true ] );

		$billing_info = json_decode( $booking_data->billing_info );

		/**
		 * Build payment.
		 */
		$payment = new Payment();

		$payment->source    = 'tourmaster';
		$payment->source_id = $tid;
		$payment->order_id  = $tid;

		$payment->set_description( Helper::get_description( $this->id, $tid ) );

		$payment->title = Helper::get_title( $tid );

		// Customer.
		$payment->set_customer( Helper::get_customer( $billing_info ) );

		// Address.
		$payment->set_billing_address( Helper::get_address( $billing_info ) );

		// Currency.
		// TODO: Currently passing default currency, actual currency can be passed from $booking_data if required.
		$currency = Currency::get_instance( tourmaster_get_option( 'general', 'currency-code' ) );

		// Amount.
		$payment->set_total_amount( new Money( Helper::get_amount( $tid ), $currency ) );

		// Method.
		$payment->set_payment_method( $this->payment_method );

		// Configuration.
		$payment->config_id = $this->get_config_id( $option_type );

		try {
			$payment = Plugin::start_payment( $payment );

			// TODO: Try to implement it using $gateway->output_form
			ob_start();
			if ( ! empty( $payment->get_pay_redirect_url() ) ) {
				?>
				<input type="hidden"
					value="<?php echo $payment->get_pay_redirect_url(); ?>"
					id="<?php echo $this->id; ?>_url" name="<?php echo $this->id; ?>_url">
				<div><?php esc_html_e( 'Please wait while we redirect you to payment page.', 'knit-pay-lang' ); ?></div>
				<script type="text/javascript">
					   (function($){
						document.location = $("#<?php echo $this->id; ?>_url").val();
					})(jQuery);
				   </script>

				<?php
			} else {
				?>
				<div><?php esc_html_e( 'There was an error generating the payment. Please refresh the page and try again.', 'knit-pay-lang' ); ?></div>
				<?php
			}
		} catch ( \Exception $e ) {
			?>
			<div class="tourmaster-notification-box tourmaster-failure"><p><?php echo $e->getMessage(); ?></p><p><?php esc_html_e( 'There was an error generating the payment. Please refresh the page and try again.', 'knit-pay-lang' ); ?></p></div>
			<?php
		}

		$ret = ob_get_contents();
		ob_end_clean();

		return $ret;
	}

	/**
	 * Init.
	 */
	private function get_config_id( $option_type ) {
		if ( isset( $this->config_id ) ) {
			return $this->config_id;
		}

		$this->config_id = tourmaster_get_option( $option_type, $this->id . '-config-id' );

		if ( empty( $this->config_id ) ) {
			$this->config_id = get_option( 'pronamic_pay_config_id' );
		}

		return $this->config_id;
	}

	private function is_gateway_enabled( $option_type ) {
		$tourmaster_payment_method = tourmaster_get_option( $option_type, 'payment-method' );
		if ( ! in_array( $this->id, $tourmaster_payment_method, true ) ) {
			return false;
		}

		// Gateway.
		$gateway = Plugin::get_gateway( $this->get_config_id( $option_type ) );
		if ( empty( $gateway ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Instance.
	 *
	 * @param string $id gateway
	 *
	 * @return Plugin
	 */
	public static function instance( $id, $name = '', $payment_method = null ) {
		return new self( $id, $name, $payment_method );
	}
}
