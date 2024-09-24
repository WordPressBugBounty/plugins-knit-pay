<?php

namespace KnitPay\Extensions\AWPCP;

use Pronamic\WordPress\Pay\Address;
use Pronamic\WordPress\Pay\AddressHelper;
use Pronamic\WordPress\Pay\ContactName;
use Pronamic\WordPress\Pay\ContactNameHelper;
use Pronamic\WordPress\Pay\CustomerHelper;
use AWPCP_Payment_Transaction;

/**
 * Title: AWP Classifieds Helper
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   2.5
 */
class Helper {

	/**
	 * Transaction
	 *
	 * @var AWPCP_Payment_Transaction
	 */
	private $transaction;

	/**
	 * Constructs and initializes a AWPCP Helper object.
	 *
	 * @param   AWPCP_Payment_Transaction $transaction   Instance AWPCP_Payment_Transaction for the order being processed
	 */
	public function __construct( AWPCP_Payment_Transaction $transaction ) {
		$user_id = $transaction->user_id;

		$this->user_info = (object) [
			'first_name' => awpcp_post_param( 'first_name', '' ),
			'last_name'  => awpcp_post_param( 'last_name', '' ),
			'user_email' => awpcp_post_param( 'email', '' ),
			'phone'      => awpcp_post_param( 'phone', '' ),
		];
		if ( ! empty( $user_id ) ) {
			$this->save_user_data( $user_id );

			$fields                   = [ 'first_name', 'last_name', 'user_email', 'awpcp-profile' ];
			$this->user_info          = awpcp_users_collection()->find_by_id( $user_id, $fields );
			$this->user_info->user_id = $user_id;
		}
	}

	private function save_user_data( $user_id ) {
		if ( ! ( isset( $_POST['step'] ) && 'checkout' === $_POST['step'] ) ) {
			$profile          = (array) get_user_meta( $user_id, 'awpcp-profile', true );
			$profile['phone'] = $this->user_info->phone;
			update_user_meta( $user_id, 'awpcp-profile', $profile );
			do_action( 'awpcp-user-profile-updated', $profile, $user_id );

			update_user_meta( $user_id, 'first_name', $this->user_info->first_name );
			update_user_meta( $user_id, 'last_name', $this->user_info->last_name );
		}
	}

	/**
	 * Get title.
	 *
	 * @param int $ad_id.
	 * @return string
	 */
	public function get_title( $ad_id ) {
		/* translators: %s: AWP Classified Ad */
		return sprintf( __( 'AWP Classified Ad %s', 'knit-pay-lang' ), $ad_id );
	}

	/**
	 * Get description.
	 *
	 * @return string
	 */
	public function get_description( $ad_id ) {
		$description = get_awpcp_option( 'knit_pay_payment_description' );

		if ( empty( $description ) ) {
			$description = self::get_title( $ad_id );
		}

		// Replacements.
		$replacements = [
			'{listing_id}' => $ad_id,
		];

		return strtr( $description, $replacements );
	}

	/**
	 * Get value from object.
	 *
	 * @param object $object Object.
	 * @param string $key   Key.
	 * @return string|null
	 */
	private static function get_value_from_object( $object, $var ) {
		if ( isset( $object->{$var} ) ) {
			return $object->{$var};
		}
		return null;
	}

	/**
	 * Get customer from order.
	 */
	public function get_customer() {
		return CustomerHelper::from_array(
			[
				'name'    => self::get_name(),
				'email'   => self::get_value_from_object( $this->user_info, 'user_email' ),
				'phone'   => self::get_value_from_object( $this->user_info, 'phone' ),
				'user_id' => self::get_value_from_object( $this->user_info, 'user_id' ),
			]
		);
	}

	/**
	 * Get name from order.
	 *
	 * @return ContactName|null
	 */
	public function get_name() {
		return ContactNameHelper::from_array(
			[
				'first_name' => self::get_value_from_object( $this->user_info, 'first_name' ),
				'last_name'  => self::get_value_from_object( $this->user_info, 'last_name' ),
			]
		);
	}

	/**
	 * Get address from order.
	 *
	 * @return Address|null
	 */
	public function get_address() {
		$user_info = $this->user_info;

		return AddressHelper::from_array(
			[
				'name'         => self::get_name(),
				'line_1'       => self::get_value_from_object( $user_info, 'address' ),
				'line_2'       => null,
				'postal_code'  => null,
				'city'         => self::get_value_from_object( $user_info, 'city' ),
				'region'       => self::get_value_from_object( $user_info, 'state' ),
				'country_code' => self::get_value_from_object( $user_info, 'country' ),
				'email'        => self::get_value_from_object( $user_info, 'user_email' ),
				'phone'        => self::get_value_from_object( $user_info, 'phone' ),
			]
		);
	}
}
