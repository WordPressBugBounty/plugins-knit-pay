<?php

namespace KnitPay\Extensions\IndeedUltimateMembershipPro;

use Pronamic\WordPress\Pay\AbstractPluginIntegration;
use Pronamic\WordPress\Pay\Payments\Payment;

/**
 * Title: Indeed Ultimate Membership Pro extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   5.5.0
 */
class Extension extends AbstractPluginIntegration {
	/**
	 * Slug
	 *
	 * @var string
	 */
	const SLUG = 'indeed-ultimate-membership-pro';

	/**
	 * Constructs and initialize Indeed Ultimate Membership Pro extension.
	 */
	public function __construct() {
		parent::__construct(
			[
				'name' => __( 'Indeed Ultimate Membership Pro', 'knit-pay-lang' ),
			]
		);

		// Dependencies.
		$dependencies = $this->get_dependencies();

		$dependencies->add( new Dependency() );
	}

	/**
	 * Setup plugin integration.
	 *
	 * @return void
	 */
	public function setup() {
		add_filter( 'pronamic_payment_source_text_' . self::SLUG, [ $this, 'source_text' ], 10, 2 );
		add_filter( 'pronamic_payment_source_description_' . self::SLUG, [ $this, 'source_description' ], 10, 2 );
		add_filter( 'pronamic_payment_source_url_' . self::SLUG, [ $this, 'source_url' ], 10, 2 );

		// Check if dependencies are met and integration is active.
		if ( ! $this->is_active() ) {
			return;
		}

		add_filter( 'pronamic_payment_redirect_url_' . self::SLUG, [ $this, 'redirect_url' ], 10, 2 );
		add_action( 'pronamic_payment_status_update_' . self::SLUG, [ $this, 'status_update' ], 10 );

		add_filter( 'ihc_payment_gateway_box_status', [ $this, 'ihc_payment_gateway_box_status' ], 10, 2 );
		add_action( 'ihc_payment_gateway_box', [ $this, 'ihc_payment_gateway_box' ] );
		add_action( 'ihc_payment_gateway_page', [ $this, 'ihc_payment_gateway_page' ] );
		add_filter( 'ihc_payment_gateways_list', [ $this, 'ihc_payment_gateways_list' ], 10, 1 );
		add_filter( 'ihc_default_options_group_filter', [ $this, 'ihc_default_options_group_filter' ], 10, 2 );
		add_filter( 'ihc_payment_gateway_create_payment_object', [ $this, 'ihc_payment_gateway_create_payment_object' ], 10, 2 );
		add_filter( 'ihc_payment_gateway_status', [ $this, 'ihc_payment_gateway_status' ], 10, 2 );

	}

	function ihc_payment_gateway_status( $status, $type ) {
		if ( 'knit_pay' === $type ) {
			$status = true;
		}
		return $status;
	}

	function ihc_payment_gateway_create_payment_object( $bool, $payment_method ) {
		if ( ihc_check_payment_available( 'knit_pay' ) ) {
			return new Gateway();
		}
	}

	function ihc_default_options_group_filter( $arr, $type ) {
		if ( 'payment_knit_pay' === $type ) {
			$arr = [
				'ihc_knit_pay_status'            => 1,
				'ihc_knit_pay_label'             => 'Pay Online',
				'ihc_knit_pay_select_order'      => 1,
				'ihc_knit_pay_short_description' => '',
			];
		}

		return $arr;
	}

	function ihc_payment_gateway_box_status( $return, $p_type ) {
		if ( 'knit_pay' === $p_type ) {
			$arr = ihc_return_meta_arr( 'payment_knit_pay' );
			if ( $arr['ihc_knit_pay_status'] == 1 ) {
				$return['active'] = 'knit-pay-active';
				$return['status'] = 1;
			}
			// if ($arr['ihc_paypal_email'] != ''){
			$return['settings'] = 'Completed';
			// }
		}
		return $return;
	}

	/**
	 * Show Knit Pay Payment box on Payment settings page.
	 */
	public function ihc_payment_gateway_box() {
		$url = get_admin_url() . 'admin.php?page=ihc_manage';
		$tab = 'payment_settings';
		?>
		<div class="iump-payment-box-wrap">
		   <?php $pay_stat = ihc_check_payment_status( 'knit_pay' ); ?>
		   <a href="<?php echo $url . '&tab=' . $tab . '&subtab=knit_pay'; ?>">
			<div class="iump-payment-box <?php echo $pay_stat['active']; ?>">
				<div class="iump-payment-box-title">Knit Pay</div>
				<div class="iump-payment-box-type">OffSite payment solution</div>
				<div class="iump-payment-box-bottom">Settings: <span><?php echo $pay_stat['settings']; ?></span></div>
			</div>
		   </a>
		</div>
		<?php
	}

	/**
	 * Show Knit Pay Payment box on Payment settings page.
	 *
	 * @param String $subtab Subtab.
	 */
	public function ihc_payment_gateway_page( $subtab ) {
		if ( 'knit_pay' !== $subtab ) {
			return;
		}

		if ( isset( $_POST['ihc_save'] ) && ! empty( $_POST['ihc-payment-settings-nonce'] ) && wp_verify_nonce( $_POST['ihc-payment-settings-nonce'], 'ihc-payment-settings-nonce' ) ) {
			// ihc_save_update_metas('payment_knit_pay');//save update metas
			ihc_save_update_trimmed_metas( 'payment_knit_pay' ); // save update metas without extra spaces

		}
		$meta_arr = ihc_return_meta_arr( 'payment_knit_pay' );// getting metas
		echo ihc_check_default_pages_set();// set default pages message
		echo ihc_check_payment_gateways();
		echo ihc_is_curl_enable();
		do_action( 'ihc_admin_dashboard_after_top_menu' );
		?>
				<div class="iump-page-title">Ultimate Membership Pro -
					<span class="second-text">
						<?php esc_html_e( 'Payment Services', 'ihc' ); ?>
					</span>
				</div>
			<form  method="post">
				<input type="hidden" name="ihc-payment-settings-nonce" value="<?php echo wp_create_nonce( 'ihc-payment-settings-nonce' ); ?>" />
				<div class="ihc-stuffbox">
					<h3><?php esc_html_e( 'Knit Pay Activation:', 'ihc' ); ?></h3>
					<div class="inside">
						<div class="iump-form-line">
							<h4><?php esc_html_e( 'Enable Knit Pay', 'ihc' ); ?> </h4>
							<p><?php esc_html_e( 'Once all Settings are properly done, Activate the Payment Getway for further use.', 'ihc' ); ?> </p>
							<p><?php esc_html_e( 'Knit Pay redirects customers to payment gateway to enter their payment information', 'ihc' ); ?> </p>
							<label class="iump_label_shiwtch ihc-switch-button-margin">
								<?php $checked = ( $meta_arr['ihc_knit_pay_status'] ) ? 'checked' : ''; ?>
								<input type="checkbox" class="iump-switch" onClick="iumpCheckAndH(this, '#ihc_knit_pay_status');" <?php echo $checked; ?> />
								<div class="switch ihc-display-inline"></div>
							</label>
							<input type="hidden" value="<?php echo $meta_arr['ihc_knit_pay_status']; ?>" name="ihc_knit_pay_status" id="ihc_knit_pay_status" />
						</div>
						<div class="ihc-wrapp-submit-bttn iump-submit-form">
							<input type="submit" value="<?php esc_html_e( 'Save Changes', 'ihc' ); ?>" name="ihc_save" class="button button-primary button-large" />
						</div>
					</div>
				</div>
				<!-- <div class="ihc-stuffbox">
					<h3><?php esc_html_e( 'Bank Transfer Instructions Message:', 'ihc' ); ?></h3>

					<div class="inside">
						<div class="iump-form-line">
							<p><?php esc_html_e( 'Instructions will be provided to buyer via trank you page. Use available {constants} for a dynamic and complete description', 'ihc' ); ?></p>
						</div>
							<div class="ihc-payment-bank-editor">
								<?php
								wp_editor(
									stripslashes( $meta_arr['ihc_knit_pay_message'] ),
									'ihc_knit_pay_message',
									[
										'textarea_name' => 'ihc_knit_pay_message',
										'quicktags'     => true,
									]
								);
								?>
							</div>
							<div class="ihc-payment-bank-editor-constants">
								<div>{siteurl}</div>
								<div>{username}</div>
								<div>{first_name}</div>
								<div>{last_name}</div>
								<div>{user_id}</div>
								<div>{level_id}</div>
								<div>{level_name}</div>
								<div>{amount}</div>
								<div>{currency}</div>
							</div>
						<div class="ihc-wrapp-submit-bttn iump-submit-form">
							<input type="submit" value="<?php esc_html_e( 'Save Changes', 'ihc' ); ?>" name="ihc_save" class="button button-primary button-large" />
						</div>
					</div>
				</div> -->

				<div class="ihc-stuffbox">
					<h3><?php esc_html_e( 'Extra Settings', 'ihc' ); ?></h3>
					<div class="inside">
					<div class="row ihc-row-no-margin">
						  <div class="col-xs-4">
						<div class="iump-form-line iump-no-border input-group">
							<span class="input-group-addon" ><?php esc_html_e( 'Label:', 'ihc' ); ?></span>
							<input type="text" name="ihc_knit_pay_label" value="<?php echo $meta_arr['ihc_knit_pay_label']; ?>"  class="form-control"/>
						</div>

						<div class="iump-form-line iump-no-border input-group">
							<span class="input-group-addon" ><?php esc_html_e( 'Order:', 'ihc' ); ?></span>
							<input type="number" min="1" name="ihc_knit_pay_select_order" value="<?php echo $meta_arr['ihc_knit_pay_select_order']; ?>"  class="form-control"/>
						</div>

											</div>
							  </div>
										<!-- developer -->
										  <div class="row ihc-row-no-margin">
										<div class="col-xs-4">
										<div class="input-group">
										   <h4><?php esc_html_e( 'Short Description', 'ihc' ); ?></h4>
											 <textarea name="ihc_knit_pay_short_description" class="form-control" rows="2" cols="125" placeholder="<?php esc_html_e( 'write a short description', 'ihc' ); ?>"><?php echo isset( $meta_arr['ihc_knit_pay_short_description'] ) ? stripslashes( $meta_arr['ihc_knit_pay_short_description'] ) : ''; ?></textarea>
										 </div>
										</div>
										</div>
										 <!-- end developer -->
								<div class="ihc-wrapp-submit-bttn iump-submit-form">
									<input type="submit" value="<?php esc_html_e( 'Save Changes', 'ihc' ); ?>" name="ihc_save" class="button button-primary button-large" />
								</div>
		  </div>
				</div>

			</form>

			<?php
	}

	public function ihc_payment_gateways_list( $gateways ) {
		$gateways['knit_pay'] = 'Knit Pay';

		return $gateways;
	}

	/**
	 * Payment redirect URL filter.
	 *
	 * @param string  $url     Redirect URL.
	 * @param Payment $payment Payment.
	 *
	 * @return string
	 */
	public static function redirect_url( $url, $payment ) {
		return $url;
	}

	/**
	 * Update the status of the specified payment
	 *
	 * @param Payment $payment Payment.
	 */
	public static function status_update( Payment $payment ) {
		// Calling Gateway::webhookPayment (function in parent of Gateway).
		wp_remote_get(
			add_query_arg(
				[
					'ihc_action' => 'knit_pay',
					'payment_id' => $payment->get_id(),
				],
				trailingslashit( site_url() )
			),
			[ 'sslverify' => false ]
		);
	}

	/**
	 * Source column
	 *
	 * @param string  $text    Source text.
	 * @param Payment $payment Payment.
	 *
	 * @return string $text
	 */
	public function source_text( $text, Payment $payment ) {
		$text = __( 'Indeed Ultimate Membership Pro', 'knit-pay-lang' ) . '<br />';

		$text .= sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'admin.php?page=ihc_manage&tab=order-edit&order_id=' . $payment->source_id ),
			/* translators: %s: source id */
			sprintf( __( 'Order %s', 'knit-pay-lang' ), $payment->source_id )
		);

		return $text;
	}

	/**
	 * Source description.
	 *
	 * @param string  $description Description.
	 * @param Payment $payment     Payment.
	 *
	 * @return string
	 */
	public function source_description( $description, Payment $payment ) {
		return __( 'Indeed Ultimate Membership Pro', 'knit-pay-lang' );
	}

	/**
	 * Source URL.
	 *
	 * @param string  $url     URL.
	 * @param Payment $payment Payment.
	 *
	 * @return string
	 */
	public function source_url( $url, Payment $payment ) {
		return admin_url( 'admin.php?page=ihc_manage&tab=order-edit&order_id=' . $payment->source_id );
	}

}
