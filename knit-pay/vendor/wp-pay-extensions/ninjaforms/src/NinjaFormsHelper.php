<?php
/**
 * Ninja Forms Helper
 *
 * @author    Pronamic <info@pronamic.eu>
 * @copyright 2005-2023 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\WordPress\Pay\Extensions\NinjaForms
 */

namespace Pronamic\WordPress\Pay\Extensions\NinjaForms;

use Pronamic\WordPress\Pay\Address;
use Pronamic\WordPress\Pay\AddressHelper;
use Pronamic\WordPress\Pay\ContactName;
use Pronamic\WordPress\Pay\ContactNameHelper;
use Pronamic\WordPress\Pay\CustomerHelper;
use Pronamic\WordPress\Pay\Subscriptions\Subscription;
use Pronamic\WordPress\Pay\Subscriptions\SubscriptionInterval;
use Pronamic\WordPress\Pay\Subscriptions\SubscriptionPhase;
use Pronamic\WordPress\Pay\Core\Util;

/**
 * Ninja Forms Helper
 *
 * @version 1.3.0
 * @since   1.3.0
 */
class NinjaFormsHelper {
	/**
	 * Get source ID from submission data.
	 *
	 * @param array $data Submission data.
	 * @return string|int
	 */
	public static function get_source_id_from_submission_data( $data ) {
		/**
		 * Ninja Forms form submissions are only saved if a save action has been set.
		 * Only in this case a form submission ID is available.
		 */
		if ( isset( $data['actions']['save']['sub_id'] ) ) {
			return $data['actions']['save']['sub_id'];
		}

		return \time();
	}

	/**
	 * Get Collect Payment action settings.
	 *
	 * @param int|string $form_id Form ID.
	 * @return array|null
	 */
	public static function get_collect_payment_action_settings( $form_id ) {
		$actions = \Ninja_Forms()->form( $form_id )->get_actions();

		foreach ( $actions as $action ) {
			$action_settings = $action->get_settings();

			// Check Collect Payment action.
			if ( 'collectpayment' !== $action_settings['type'] ) {
				continue;
			}

			// Check gateway.
			if ( 'pronamic_pay' !== $action_settings['payment_gateways'] ) {
				continue;
			}

			return $action_settings;
		}

		return null;
	}

	/**
	 * Get action IDs of delayed actions from action settings.
	 *
	 * @param array<string, mixed> $action_settings Action settings.
	 * @return array<int>
	 */
	public static function get_delayed_action_ids_from_settings( $action_settings ) {
		$delayed_actions = [];

		foreach ( $action_settings as $key => $value ) {
			// Check settings key.
			if ( 'pronamic_pay_delayed_action_' !== substr( $key, 0, 28 ) ) {
				continue;
			}

			// Check settings key.
			if ( 1 !== (int) $value ) {
				continue;
			}

			$delayed_actions[] = (int) substr( $key, 28 );
		}

		return $delayed_actions;
	}

	/**
	 * Get config ID from action settings or use default config.
	 *
	 * @param array|null $action_settings Action settings.
	 * @return string
	 */
	public static function get_config_id_from_action_settings( $action_settings ) {
		$config_id = null;

		if ( \is_array( $action_settings ) && \array_key_exists( 'pronamic_pay_config_id', $action_settings ) ) {
			$config_id = $action_settings['pronamic_pay_config_id'];
		}

		if ( empty( $config_id ) ) {
			// TODO: Remove this if block after few months, better to check if we can update it.
			if ( ! empty( $action_settings['knit_pay_config_id'] ) ) {
				return $action_settings['knit_pay_config_id'];
			}

			$config_id = \get_option( 'pronamic_pay_config_id' );
		}

		return $config_id;
	}

	/**
	 * Get description from action settings.
	 *
	 * @param array $action_settings Action settings.
	 * @return string
	 */
	public static function get_description_from_action_settings( $action_settings ) {
		return $action_settings['pronamic_pay_description'];
	}

	/**
	 * Get currency from form.
	 *
	 * @param int $form_id Form ID.
	 * @return string
	 */
	public static function get_currency_from_form_id( $form_id ) {
		$form = \Ninja_Forms()->form( $form_id )->get();

		$currency = $form->get_setting( 'currency' );

		if ( ! empty( $currency ) ) {
			// Return currency from form settings.
			return $currency;
		}

		// Return currency from Ninja Forms settings.
		return \Ninja_Forms()->get_setting( 'currency' );
	}

	/**
	 * Get payment method from submission data.
	 *
	 * @param array $data Form submission data.
	 * @return string|null
	 */
	public static function get_payment_method_from_submission_data( $data ) {
		$payment_method = null;

		// Get payment method from a payment method field if it exists.
		foreach ( $data['fields'] as $field ) {
			if ( 'pronamic_pay_payment_method' !== $field['type'] ) {
				continue;
			}

			$value = $field['value'];

			if ( ! empty( $value ) ) {
				$payment_method = $value;

				break;
			}
		}

		return $payment_method;
	}

	/**
	 * Get issuer from submission data.
	 *
	 * @param array $data Submission data.
	 * @return string|null
	 */
	public static function get_issuer_from_submission_data( $data ) {
		$issuer = null;

		// Get issuer from an issuers field if it exists.
		foreach ( $data['fields'] as $field ) {
			if ( 'pronamic_pay_issuer' !== $field['type'] ) {
				continue;
			}

			$issuer = $field['value'];

			break;
		}

		return $issuer;
	}

	/**
	 * Get page link from action settings.
	 *
	 * @param array  $action_settings Action settings.
	 * @param string $key             Setting key.
	 * @return string|null
	 */
	public static function get_page_link_from_action_settings( $action_settings, $key ) {
		if ( ! \array_key_exists( $key, $action_settings ) ) {
			return null;
		}

		$page_id = $action_settings[ $key ];

		if ( 'publish' !== get_post_status( $page_id ) ) {
			return null;
		}

		return \get_permalink( $page_id );
	}

	/**
	 * Get value from array.
	 *
	 * @param array  $array Array.
	 * @param string $key   Key.
	 * @return string|null
	 */
	private static function get_value_from_array( $array, $key ) {
		if ( ! array_key_exists( $key, $array ) ) {
			return null;
		}

		return $array[ $key ];
	}

	/**
	 * Get customer from order.
	 */
	public static function get_customer( $action_settings ) {
		return CustomerHelper::from_array(
			array(
				'name'    => self::get_name( $action_settings ),
				'email'   => self::get_value_from_array( $action_settings, 'knit_pay_email' ),
				'phone'   => self::get_value_from_array( $action_settings, 'knit_pay_phone' ),
				'user_id' => null,
			)
		);
	}

	/**
	 * Get name from order.
	 *
	 * @return ContactName|null
	 */
	public static function get_name( $action_settings ) {
		return ContactNameHelper::from_array(
			array(
				'first_name' => self::get_value_from_array( $action_settings, 'knit_pay_fname' ),
				'last_name'  => self::get_value_from_array( $action_settings, 'knit_pay_lname' ),
			)
		);
	}

	/**
	 * Get address from order.
	 *
	 * @return Address|null
	 */
	public static function get_address( $action_settings ) {
		$country = self::get_value_from_array( $action_settings, 'knit_pay_country' );
		if ( ! empty( $country ) && 2 !== strlen( $country ) ) {
			$country = '';
		}

		return AddressHelper::from_array(
			array(
				'name'         => self::get_name( $action_settings ),
				'line_1'       => self::get_value_from_array( $action_settings, 'knit_pay_address' ),
				'line_2'       => null,
				'postal_code'  => self::get_value_from_array( $action_settings, 'knit_pay_zip' ),
				'city'         => self::get_value_from_array( $action_settings, 'knit_pay_city' ),
				'region'       => self::get_value_from_array( $action_settings, 'knit_pay_state' ),
				'country_code' => $country,
				'email'        => self::get_value_from_array( $action_settings, 'knit_pay_email' ),
				'phone'        => self::get_value_from_array( $action_settings, 'knit_pay_phone' ),
			)
		);
	}

	/**
	 * Get subscription.
	 *
	 * @since 2.3.2
	 * @return Subscription|null
	 */
	public static function get_subscription( $action_settings, $data, $description, $amount ) {
		if ( empty( $action_settings['knit_pay_frequency'] ) || empty( $action_settings['knit_pay_interval'] ) ) {
			return;
		}

		$interval_period = self::get_value_from_array( $action_settings, 'knit_pay_interval_period' );
		$interval        = self::get_value_from_array( $action_settings, 'knit_pay_interval' );
		$frequency       = self::get_value_from_array( $action_settings, 'knit_pay_frequency' );

		if ( empty( $interval_period ) ) {
			foreach ( $data['fields'] as $field ) {
				if ( 'knit_pay_recurring_interval_period' !== $field['type'] ) {
					continue;
				}

				$interval_period = $field['value'];

				if ( empty( $interval_period ) ) {
					return;
				}
				break;
			}
			return;
		}

		// Subscription.
		$subscription = new Subscription();

		$subscription->set_description( $description );

		// Phase.
		$phase = new SubscriptionPhase(
			$subscription,
			new \DateTimeImmutable(),
			new SubscriptionInterval( 'P' . $interval . Util::to_period( $interval_period ) ),
			$amount
		);

		$phase->set_total_periods( $frequency );

		$subscription->add_phase( $phase );

		return $subscription;
	}

	/**
	 * Get session cookie.
	 *
	 * @return string|null
	 */
	public static function get_session_cookie() {
		// Determine session cookie name.
		$wp_session_cookie = 'nf_wp_session';

		if ( defined( '\WP_SESSION_COOKIE' ) ) {
			$wp_session_cookie = \WP_SESSION_COOKIE;
		}

		// Get cookie from headers.
		$headers = headers_list();

		foreach ( $headers as $header ) {
			// Check header name.
			if ( 'set-cookie' !== substr( strtolower( $header ), 0, 10 ) ) {
				continue;
			}

			// Get cookie name and value.
			$cookie = \explode( ';', $header );

			$cookie = trim( \substr( $cookie[0], 12 ) );

			$cookie = \explode( '=', $cookie );

			if ( $cookie[0] !== $wp_session_cookie ) {
				continue;
			}

			// Return cookie value.
			return $cookie[1];
		}

		return null;
	}
}
