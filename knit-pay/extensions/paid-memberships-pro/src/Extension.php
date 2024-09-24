<?php

namespace KnitPay\Extensions\PaidMembershipsPro;

use Pronamic\WordPress\Pay\AbstractPluginIntegration;
use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;
use Pronamic\WordPress\Pay\Core\Util;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\FailureReason;

/**
 * Title: Paid Memberships Pro extension
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   2.0.0
 */
class Extension extends AbstractPluginIntegration {
	/**
	 * Slug
	 *
	 * @var string
	 */
	const SLUG = 'paid-memberships-pro';

	/**
	 * Constructs and initialize Paid Memberships Pro extension.
	 */
	public function __construct() {
		parent::__construct(
			[
				'name' => __( 'Paid Memberships Pro', 'knit-pay-lang' ),
			]
		);

		// Dependencies.
		$dependencies = $this->get_dependencies();

		$dependencies->add( new PaidMembershipsProDependency() );
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

		require_once 'PMProGateway_knit_pay.php';
		$gateway = new \PMProGateway_knit_pay();

		add_action( 'init', [ $gateway, 'init' ] );
		add_filter( 'pmpro_default_country', [ $this, 'pmpro_default_country' ] );
	}

	/**
	 * Payment redirect URL filter.
	 *
	 * @param string  $url     Redirect URL.
	 * @param Payment $payment Payment.
	 *
	 * @return string
	 */
	public function redirect_url( $url, $payment ) {
		$item_number = (int) $payment->get_source_id();
		$morder      = new \MemberOrder( $item_number );

		switch ( $payment->get_status() ) {
			case Core_Statuses::CANCELLED:
			case Core_Statuses::EXPIRED:
			case Core_Statuses::FAILURE:
			case Core_Statuses::OPEN:
				$url = add_query_arg( 'level', $morder->getMembershipLevel()->id, pmpro_url( 'checkout' ) );
				break;

			case Core_Statuses::SUCCESS:
			case Core_Statuses::AUTHORIZED:
				$url = add_query_arg( 'level', $morder->getMembershipLevel()->id, pmpro_url( 'confirmation' ) );
				break;

			default:
				$url = home_url( '/' );
				break;
		}

		return $url;
	}

	/**
	 * Update the status of the specified payment
	 *
	 * @param Payment $payment Payment.
	 */
	public function status_update( Payment $payment ) {

		$source_id = (int) $payment->get_source_id();
		$morder    = new \MemberOrder( $source_id );
		if ( empty( $morder ) || empty( $morder->id ) ) {
			return;
		}

		switch ( $payment->get_status() ) {
			case Core_Statuses::CANCELLED:
			case Core_Statuses::EXPIRED:
			case Core_Statuses::FAILURE:
				$morder->updateStatus( 'error' );

				break;
			case Core_Statuses::SUCCESS:
				if ( 'success' === $morder->status ) {
					break;
				}

				// get some more order info
				$morder->getMembershipLevel();
				$morder->getUser();

				$this->change_membership_level( $payment->get_transaction_id(), $morder );

				break;
			case Core_Statuses::OPEN:
			default:
				$morder->updateStatus( 'pending' );

				break;
		}
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
		$text = __( 'Paid Memberships Pro', 'knit-pay-lang' ) . '<br />';

		$text .= sprintf(
			'<a href="%s">%s</a>',
			get_edit_post_link( $payment->source_id ),
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
		return __( 'Paid Memberships Pro Order', 'knit-pay-lang' );
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
		return get_edit_post_link( $payment->source_id );
	}

	/**
	 * Change Membership Lever after successful Payment.
	 *
	 * @param string       $txn_id     Transaction ID.
	 * @param \MemberOrder $morder Member Order.
	 *
	 * @return string
	 */
	function change_membership_level( $txn_id, &$morder ) {
		// TODO Set Recurring paramer, removed hardcoded value
		// $recurring = pmpro_getParam( 'recurring', 'POST' );
		$recurring = false;

		global $wpdb;

		// filter for level
		$morder->membership_level = apply_filters( 'pmpro_checkout_level', $morder->membership_level, $morder->user_id );

		// @see /paid-memberships-pro/preheaders/checkout.php executing pmpro_checkout_before_change_membership_level again in order to support gift membership addon.
		do_action( 'pmpro_checkout_before_change_membership_level', $morder->user_id, $morder );

		// set the start date to current_time('timestamp') but allow filters  (documented in preheaders/checkout.php)
		$startdate = apply_filters( 'pmpro_checkout_start_date', "'" . current_time( 'mysql' ) . "'", $morder->user_id, $morder->membership_level );

		// fix expiration date
		if ( ! empty( $morder->membership_level->expiration_number ) ) {
			$enddate = "'" . date_i18n( 'Y-m-d', strtotime( '+ ' . $morder->membership_level->expiration_number . ' ' . $morder->membership_level->expiration_period, current_time( 'timestamp' ) ) ) . "'";
		} else {
			$enddate = 'NULL';
		}

		// filter the enddate (documented in preheaders/checkout.php)
		$enddate = apply_filters( 'pmpro_checkout_end_date', $enddate, $morder->user_id, $morder->membership_level, $startdate );

		// get discount code
		$morder->getDiscountCode();
		if ( ! empty( $morder->discount_code ) ) {
			// update membership level
			$morder->getMembershipLevel( true );
			$discount_code_id = $morder->discount_code->id;
		} else {
			$discount_code_id = '';
		}

		// custom level to change user to
		$custom_level = [
			'user_id'         => $morder->user_id,
			'membership_id'   => $morder->membership_level->id,
			'code_id'         => $discount_code_id,
			'initial_payment' => $morder->membership_level->initial_payment,
			'billing_amount'  => $morder->membership_level->billing_amount,
			'cycle_number'    => $morder->membership_level->cycle_number,
			'cycle_period'    => $morder->membership_level->cycle_period,
			'billing_limit'   => $morder->membership_level->billing_limit,
			'trial_amount'    => $morder->membership_level->trial_amount,
			'trial_limit'     => $morder->membership_level->trial_limit,
			'startdate'       => $startdate,
			'enddate'         => $enddate,
		];

		global $pmpro_error;
		if ( ! empty( $pmpro_error ) ) {
			echo $pmpro_error;
			$this->pmp_log( $pmpro_error );
		}

		// change level and continue "checkout"
		if ( pmpro_changeMembershipLevel( $custom_level, $morder->user_id, 'changed' ) !== false ) {
			// update order status and transaction ids
			$morder->status                 = 'success';
			$morder->payment_transaction_id = $txn_id;
			if ( $recurring ) {
				// TODO
				$morder->subscription_transaction_id = $_POST['subscr_id'];
			} else {
				$morder->subscription_transaction_id = '';
			}
			$morder->saveOrder();

			// add discount code use
			if ( ! empty( $discount_code ) && ! empty( $use_discount_code ) ) {

				$wpdb->query(
					$wpdb->prepare(
						"INSERT INTO {$wpdb->pmpro_discount_codes_uses}
						( code_id, user_id, order_id, timestamp )
						VALUES( %d, %d, %s, %s )",
						$discount_code_id
					),
					$morder->user_id,
					$morder->id,
					current_time( 'mysql' )
				);
			}

			// save first and last name fields
			// TODO add first and last from from payment to order
			/*
			 if ( ! empty( $_POST['first_name'] ) ) {
				$old_firstname = get_user_meta( $morder->user_id, "first_name", true );
				if ( empty( $old_firstname ) ) {
					update_user_meta( $morder->user_id, "first_name", $_POST['first_name'] );
				}
			}
			if ( ! empty( $_POST['last_name'] ) ) {
				$old_lastname = get_user_meta( $morder->user_id, "last_name", true );
				if ( empty( $old_lastname ) ) {
					update_user_meta( $morder->user_id, "last_name", $_POST['last_name'] );
				}
			} */

			// hook
			do_action( 'pmpro_after_checkout', $morder->user_id, $morder );

			// setup some values for the emails
			if ( ! empty( $morder ) ) {
				$invoice = new \MemberOrder( $morder->id );
			} else {
				$invoice = null;
			}

			$this->pmp_log( 'CHANGEMEMBERSHIPLEVEL: ORDER: ' . var_export( $morder, true ) . "\n---\n" );

			$user = get_userdata( $morder->user_id );
			if ( empty( $user ) ) {
				return false;
			}

			$user->membership_level = $morder->membership_level;        // make sure they have the right level info

			// send email to member
			$pmproemail = new \PMProEmail();
			$pmproemail->sendCheckoutEmail( $user, $invoice );

			// send email to admin
			$pmproemail = new \PMProEmail();
			$pmproemail->sendCheckoutAdminEmail( $user, $invoice );

			return true;
		} else {
			return false;
		}

	}

	function pmp_log( $s ) {
		global $logstr;
		$logstr .= "\t" . $s . "\n";
	}

	/**
	 * Change Default Country to IN.
	 *
	 * @since 1.8
	 */
	public function pmpro_default_country( $pmpro_default_country ) {
		$gateway = pmpro_getOption( 'gateway' );
		if ( $gateway !== 'knit_pay' ) {
			return $pmpro_default_country;
		}

		if ( pmpro_getOption( 'knit_pay_hide_billing_address' ) ) {
			return '';
		}
		return 'IN';
	}

}
