<?php

namespace KnitPay\Extensions\FluentForms;

use Pronamic\WordPress\Pay\Address;
use Pronamic\WordPress\Pay\AddressHelper;
use Pronamic\WordPress\Pay\ContactName;
use Pronamic\WordPress\Pay\CustomerHelper;
use KnitPay\Utils;
use FluentFormPro\Payments\PaymentHelper;

/**
 * Title: Fluent Forms Helper
 * Description:
 * Copyright: 2020-2024 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   8.92.0.0
 */
class Helper extends PaymentHelper {
	/**
	 * Get title.
	 *
	 * @param int $submission_id Submission ID.
	 * @return string
	 */
	public static function get_title( $submission_id ) {
		return \sprintf(
			/* translators: %s: Submission */
			__( 'Submission %s', 'knit-pay-lang' ),
			$submission_id
		);
	}

	/**
	 * Get description.
	 *
	 * @return string
	 */
	public static function get_description( $submission, $form, $settings ) {
		$description = $settings['payment_description'];

		if ( empty( $description ) ) {
			$description = self::get_title( $submission->id );
		}

		// Replacements.
		$replacements = [
			'{submission_id}' => $submission->id,
			'{customer_name}' => self::getCustomerName( $submission, $form ),
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
	public static function get_customer( $submission, $form ) {
		return CustomerHelper::from_array(
			[
				'name'    => self::get_name( $submission, $form ),
				'email'   => self::getCustomerEmail( $submission, $form ),
				'phone'   => self::getCustomerPhoneNumber( $submission, $form ),
				'user_id' => null,
			]
		);
	}

	/**
	 * Get name from order.
	 *
	 * @return ContactName|null
	 */
	public static function get_name( $submission, $form ) {
		return Utils::get_contact_name_from_string( self::getCustomerName( $submission, $form ) );
	}

	/**
	 * Get address from order.
	 *
	 * @return Address|null
	 */
	public static function get_address( $submission, $form ) {
		return AddressHelper::from_array(
			[
				'name'  => self::get_name( $submission, $form ),
				'email' => self::getCustomerEmail( $submission, $form ),
				'phone' => self::getCustomerPhoneNumber( $submission, $form ),
			]
		);
	}
}
