<?php

namespace KnitPay\Extensions\LearnDash;

use Pronamic\WordPress\Money\Currency;
use Pronamic\WordPress\Money\Money;
use Pronamic\WordPress\Pay\Customer;
use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;
use LearnDash_Settings_Section;
use Learndash_DTO_Validation_Exception;
use Learndash_Payment_Button;
use Learndash_Payment_Gateway;
use Learndash_Pricing_DTO;
use Learndash_Transaction_Gateway_Transaction_DTO;
use Learndash_Transaction_Meta_DTO;
use WP_Error;
use WP_Post;
use WP_User;

/**
 * Title: Learn Dash LMS extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   2.7.0
 */

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit();

class Gateway extends Learndash_Payment_Gateway {
	private const GATEWAY_NAME = 'knit_pay';

	/**
	 * @var string
	 */
	public $id = 'knit_pay';

	/**
	 * Bootstrap
	 *
	 * @param array $args Gateway properties.
	 */
	public function __construct() {
		parent::__construct();
		$this->id = 'knit_pay';

		if ( class_exists( 'LearnDash\Core\Models\Product' ) ) {
			$this->learndash_transaction_class = 'LearnDash\Core\Models\Transaction';
			$this->learndash_product_class     = 'LearnDash\Core\Models\Product';
		} else {
			$this->learndash_transaction_class = 'Learndash_Transaction_Model';
			$this->learndash_product_class     = 'Learndash_Product_Model';
		}
	}

	public function setup_payment(): void {
		if (
			empty( (int) $_POST['post_id'] ) ||
			empty( $_POST['nonce'] ) ||
			! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['nonce'] ) ),
				$this->get_nonce_name()
			)
			) {
				wp_send_json_error(
					[
						'message' => esc_html__( 'Cheating?', 'learndash' ),
					]
				);
		}

		$product = $this->learndash_product_class::find( (int) $_POST['post_id'] );

		if ( ! $product ) {
			wp_send_json_error(
				[
					'message' => esc_html__( 'Cheating?', 'learndash' ),
				]
			);
		}

		$product_pricing_type = $product->get_pricing_type();
		if ( LEARNDASH_PRICE_TYPE_PAYNOW !== $product_pricing_type ) {
			wp_send_json_error(
				[ 'message' => '"' . $product_pricing_type . '" price type is currently not supported in this gateway. Kindly try a different gateway.' ]
			);
		}

		$product_pricing = $product->get_pricing( $this->user );
		/** This filter is documented in includes/payments/gateways/class-learndash-stripe-gateway.php */
		$price = apply_filters( 'learndash_get_price_by_coupon', $product_pricing->price, $product->get_id(), $this->user->ID );

		$config_id      = $this->settings['config_id'];
		$payment_method = $this->id;

		// Use default gateway if no configuration has been set.
		if ( empty( $config_id ) ) {
			$config_id = get_option( 'pronamic_pay_config_id' );
		}

		$gateway = Plugin::get_gateway( $config_id );

		if ( ! $gateway ) {
			return;
		}

		/**
		 * Build payment.
		 */
		$payment = new Payment();

		$payment->source    = 'learndash';
		$payment->source_id = $product->get_id();
		$payment->order_id  = $product->get_id();

		$payment->set_description( Helper::get_description( $this->settings, $product ) );

		$payment->title = Helper::get_title( $product );

		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			// Customer.
			$payment->set_customer( Helper::get_customer( $user ) );

			// Address.
			$payment->set_billing_address( Helper::get_address( $user ) );
		}

		// Currency.
		$currency = Currency::get_instance( $this->currency_code );

		// Amount.
		$payment->set_total_amount( new Money( $price, $currency ) );

		// Method.
		$payment->set_payment_method( $payment_method );

		// Configuration.
		$payment->config_id = $config_id;

		try {
			$payment = Plugin::start_payment( $payment );

			wp_send_json_success(
				[
					'redirect_url' => $payment->get_pay_redirect_url(),
				]
			);
		} catch ( \Exception $e ) {
			wp_send_json_error(
				[ 'message' => $e->getMessage() ]
			);
		}
	}

	/**
	 * Returns payment button HTML markup.
	 *
	 * @param array<mixed> $params Payment params.
	 * @param WP_Post      $post   Post being processing.
	 *
	 * @return string Payment button HTML markup.
	 */
	protected function map_payment_button_markup( array $params, WP_Post $post ): string {
		if ( 'yes' === $this->settings['login_required'] && ! is_user_logged_in() ) {
			return '';
		}

		$button_label = $this->map_payment_button_label(
			$this->settings['title'],
			$post
		);

		ob_start();
		?>
			<form
				class="<?php echo esc_attr( $this->get_form_class_name() ); ?>"
				method="post"
				data-action="<?php echo esc_attr( $this->get_ajax_action_name_setup() ); ?>"
				data-nonce="<?php echo esc_attr( wp_create_nonce( $this->get_nonce_name() ) ); ?>"
				data-post_id="<?php echo esc_attr( (string) $post->ID ); ?>"
			>
				<input
					class="<?php echo esc_attr( Learndash_Payment_Button::map_button_class_name() ); ?>"
					id="<?php echo esc_attr( Learndash_Payment_Button::map_button_id() ); ?>"
					type="submit"
					value="<?php echo esc_attr( $button_label ); ?>"
				>
			</form>
		<?php
		$buffer = ob_get_clean();
		return $buffer ? $buffer : '';
	}

	public function enqueue_scripts(): void {
		wp_enqueue_script( 'knit-pay-learndash-front', plugins_url( 'js/knit-pay-learndash-front.js', dirname( __FILE__ ) ), [ 'jquery' ], KNITPAY_VERSION, true );

		wp_enqueue_script( 'knit-pay-learndash-front' );
	}

	public function add_extra_hooks(): void {
	}

	public function is_ready(): bool {
		$enabled = ( 'yes' === $this->settings['enabled'] );

		$config_available = ! ( empty( get_option( 'pronamic_pay_config_id' ) ) && empty( $this->settings['config_id'] ) );

		return $enabled && $config_available;
	}

	protected function is_test_mode(): bool {
	}

	public static function get_label(): string {
		return esc_html__( 'Knit Pay', 'knit-pay-lang' );
	}

	public function process_webhook(): void {
	}

	public function status_update( Payment $payment ): void {
		$customer = $payment->get_customer();
		$user     = $this->setup_user_or_fail( $customer );
		$products = $this->setup_products_or_fail( (int) ( $payment->get_order_id() ?? 0 ) );

		switch ( $payment->get_status() ) {
			case Core_Statuses::CANCELLED:
			case Core_Statuses::EXPIRED:
			case Core_Statuses::FAILURE:
				$this->remove_access_to_products( $products, $user );
				$this->log_info( 'Removed access to products.' );
				break;

			case Core_Statuses::SUCCESS:
				$this->add_access_to_products( $products, $user );
				$this->log_info( 'Added access to products.' );

				if ( (int) $payment->get_source_id() === (int) $payment->get_order_id() ) {
					foreach ( $products as $product ) {
						try {
							$ld_transaction_id = $this->record_transaction(
								$this->map_transaction_meta( $payment, $product )->to_array(),
								$product->get_post(),
								$user
							);

							$payment->get_customer()->set_user_id( $user->ID );
							$payment->set_source_id( $ld_transaction_id );
							$payment->save();

							$this->log_info( 'Recorded transaction for product ID: ' . $product->get_id() );
						} catch ( Learndash_DTO_Validation_Exception $e ) {
							$this->log_error( 'Error recording transaction: ' . $e->getMessage() );
							exit;
						}
					}
				}
				break;
			case Core_Statuses::OPEN:
			default:
				$this->remove_access_to_products( $products, $user );
				break;
		}
	}

	public static function get_name(): string {
		return self::GATEWAY_NAME;
	}

	protected function configure(): void {
		$this->settings = LearnDash_Settings_Section::get_section_settings_all( __NAMESPACE__ . '\KnitPaySettingsSection' );

		if ( empty( $this->settings['title'] ) ) {
			$this->settings['title'] = __( 'Pay Online', 'knit-pay-lang' );
		}
	}

	protected function map_transaction_meta( $payment, $product ): Learndash_Transaction_Meta_DTO {
		$is_subscription_event = false;

		$pricing = $product->get_pricing();
		/**
		 * Pricing.
		 *
		 * @var array<string,mixed> $pricing_array Pricing.
		 */
		$pricing_array = $pricing->to_array();

		$pricing_info = ! empty( $this->user_hash[ $this->learndash_transaction_class::$meta_key_pricing_info ] ) ?
		Learndash_Pricing_DTO::create(
			// @phpstan-ignore-next-line -- Variable array key name.
			$this->user_hash[ $this->learndash_transaction_class::$meta_key_pricing_info ]
		) :
			Learndash_Pricing_DTO::create(
				$pricing_array
			);

			$meta = [
				$this->learndash_transaction_class::$meta_key_gateway_name => $this::get_name(),
				$this->learndash_transaction_class::$meta_key_price_type => ! $is_subscription_event ? LEARNDASH_PRICE_TYPE_PAYNOW : LEARNDASH_PRICE_TYPE_SUBSCRIBE,
				$this->learndash_transaction_class::$meta_key_pricing_info => $pricing_info,
				// $this->learndash_transaction_class::$meta_key_has_trial => $is_subscription_event && ! empty( $data['period1'] ),
				// $this->learndash_transaction_class::$meta_key_has_free_trial => $is_subscription_event && ! empty( $data['period1'] ) && isset( $data['mc_amount1'] ) && ( '0.00' === strval( $data['mc_amount1'] ) || '0' === strval( $data['mc_amount1'] ) ),
				$this->learndash_transaction_class::$meta_key_gateway_transaction => Learndash_Transaction_Gateway_Transaction_DTO::create(
					[
						'id'    => $payment->get_id(),
						'event' => $payment,
					]
				),
			];

			return Learndash_Transaction_Meta_DTO::create( $meta );
	}

	/**
	 * Creates/finds a user or sends a json error on fail.
	 *
	 * @param Customer $customer Customer Object.
	 *
	 * @return WP_User
	 */
	private function setup_user_or_fail( Customer $customer ): WP_User {
		if ( empty( $customer->get_email() ) ) {
			return new WP_User();
		}
		$user = $this->find_or_create_user(
			$customer->get_user_id() ?? 0,
			$customer->get_email(),
			''
		);

		if ( ! $user instanceof WP_User ) {
			$this->log_error( 'No WP user found and failed to create a new user.' );

			wp_send_json_error(
				new WP_Error( 'bad_request', 'User validation failed. User was not found or had not been able to be created successfully.' ),
				422
			);
		}

		$this->log_info( 'WP related User ID: ' . $user->ID . '; Email: ' . $user->user_email );

		return $user;
	}

	/**
	 * Finds products or sends a json error on fail.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return $this->learndash_product_class[]
	 */
	public function setup_products_or_fail( int $post_id ): array {
		if ( $post_id <= 0 ) {
			$this->log_error( 'Event notes validation failed. Missing key "post_id" in notes.' );

			wp_send_json_error(
				new WP_Error(
					'bad_request',
					'Event notes validation failed. Missing key "post_id" in notes.'
				),
				422
			);
		}

		$products = $this->learndash_product_class::find_many( [ $post_id ] );

		if ( empty( $products ) ) {
			$this->log_error( 'No related products found.' );

			wp_send_json_error(
				new WP_Error(
					'bad_request',
					sprintf( 'Product validation failed. Product with the ID %d was not found.', $post_id )
				),
				422
			);
		}

		$this->log_info( 'Products found: ' . count( $products ) );
		$this->log_info(
			'Products IDs: ' . array_reduce(
				$products,
				function ( string $carry, $product ): string {
					return $carry . $product->get_id() . ', ';
				},
				''
			)
		);

		return $products;
	}
}
