<?php
/**
 * Knit Pay – Payment API Helper
 *
 * Single source of truth for:
 *  - the complete payment input/output JSON Schema
 *  - the create-payment execution logic
 *  - the get-payment execution logic
 *
 * Used by both PaymentRestController (REST API) and PaymentAbilities
 * (WordPress Abilities API) so that schema definitions and business logic
 * are never duplicated.
 *
 * Schema design
 * -------------
 * get_input_schema()  — writable fields only (no readonly marker).
 *                       Used as-is for the Abilities input_schema.
 *                       Used as the writable portion of the REST item schema.
 *
 * get_readonly_schema() — fields that only appear in responses and can never
 *                         be written: id, pay_redirect_url, status,
 *                         transaction_id, mode, gateway.
 *                         Intentionally has NO overlap with get_input_schema()
 *                         so that array_merge() in get_item_schema() is safe.
 *
 * Keeping the two schemas disjoint prevents the REST infrastructure method
 * get_endpoint_args_for_item_schema() from silently dropping writable fields
 * that accidentally get a readonly=true marker via a bad merge.
 *
 * @package KnitPay
 */

namespace KnitPay;

use Pronamic\WordPress\Pay\Core\PaymentMethods;
use Pronamic\WordPress\Pay\MoneyJsonTransformer;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentInfoHelper;
use Pronamic\WordPress\Pay\Plugin;
use WP_Error;

class PaymentApiHelper {

	// -------------------------------------------------------------------------
	// Private sub-schema builders
	// -------------------------------------------------------------------------

	/**
	 * Sub-schema for a Name object (first_name + last_name).
	 */
	private static function name_schema(): array {
		return [
			'type'        => 'object',
			'description' => __( 'Name split into first and last.', 'knit-pay-lang' ),
			'properties'  => [
				'first_name' => [
					'type'        => 'string',
					'description' => __( 'First name.', 'knit-pay-lang' ),
				],
				'last_name'  => [
					'type'        => 'string',
					'description' => __( 'Last name.', 'knit-pay-lang' ),
				],
			],
		];
	}

	/**
	 * Sub-schema for an Address object.
	 */
	private static function address_schema(): array {
		return [
			'type'        => 'object',
			'description' => __( 'Address details.', 'knit-pay-lang' ),
			'properties'  => [
				'name'         => self::name_schema(),
				'phone'        => [
					'type'        => 'string',
					'description' => __( 'Phone number.', 'knit-pay-lang' ),
				],
				'email'        => [
					'type'        => 'string',
					'format'      => 'email',
					'description' => __( 'Email address.', 'knit-pay-lang' ),
				],
				'line_1'       => [
					'type'        => 'string',
					'description' => __( 'Address line 1.', 'knit-pay-lang' ),
				],
				'street_name'  => [
					'type'        => 'string',
					'description' => __( 'Street name.', 'knit-pay-lang' ),
				],
				'house_number' => [
					'type'        => 'object',
					'description' => __( 'House / building number.', 'knit-pay-lang' ),
					'properties'  => [
						'value' => [
							'type'        => 'string',
							'description' => __( 'Full house number, e.g. "12 A".', 'knit-pay-lang' ),
						],
						'base'  => [
							'type'        => 'string',
							'description' => __( 'Numeric base of the house number, e.g. "12".', 'knit-pay-lang' ),
						],
					],
				],
				'postal_code'  => [
					'type'        => 'string',
					'description' => __( 'Postal / ZIP code.', 'knit-pay-lang' ),
				],
				'city'         => [
					'type'        => 'string',
					'description' => __( 'City name.', 'knit-pay-lang' ),
				],
				'country'      => [
					'type'        => 'object',
					'description' => __( 'Country.', 'knit-pay-lang' ),
					'properties'  => [
						'code' => [
							'type'        => 'string',
							'description' => __( 'ISO 3166-1 alpha-2 country code, e.g. "IN", "NL", "US".', 'knit-pay-lang' ),
							'minLength'   => 2,
							'maxLength'   => 2,
						],
					],
				],
			],
		];
	}

	/**
	 * Sub-schema for a Customer object.
	 */
	private static function customer_schema(): array {
		return [
			'type'        => 'object',
			'description' => __( 'Customer details.', 'knit-pay-lang' ),
			'properties'  => [
				'name'       => self::name_schema(),
				'phone'      => [
					'type'        => 'string',
					'description' => __( 'Customer phone number, e.g. "+919999999999".', 'knit-pay-lang' ),
				],
				'email'      => [
					'type'        => 'string',
					'format'      => 'email',
					'description' => __( 'Customer email address.', 'knit-pay-lang' ),
				],
				'ip_address' => [
					'type'        => 'string',
					'description' => __( 'Customer IP address (IPv4 or IPv6).', 'knit-pay-lang' ),
				],
				'language'   => [
					'type'        => 'string',
					'description' => __( 'ISO 639-1 language code, e.g. "en".', 'knit-pay-lang' ),
				],
				'locale'     => [
					'type'        => 'string',
					'description' => __( 'Locale string, e.g. "en_US".', 'knit-pay-lang' ),
				],
			],
		];
	}

	/**
	 * Sub-schema for a Money / amount object.
	 *
	 * @param string $description Field description.
	 */
	private static function money_schema( string $description ): array {
		return [
			'type'        => 'object',
			'description' => $description,
			'properties'  => [
				'value'    => [
					'type'             => 'number',
					'description'      => __( 'Numeric amount value, e.g. 100 or 99.99.', 'knit-pay-lang' ),
					'minimum'          => 0,
					'exclusiveMinimum' => true,
				],
				'currency' => [
					'type'        => 'string',
					'description' => __( 'ISO 4217 currency code, e.g. "INR", "USD", "EUR".', 'knit-pay-lang' ),
					'minLength'   => 3,
					'maxLength'   => 3,
					'pattern'     => '[A-Z]{3}',
				],
			],
		];
	}

	// -------------------------------------------------------------------------
	// Public schema accessors
	// -------------------------------------------------------------------------

	/**
	 * Returns the writable (input) fields for a payment request.
	 *
	 * No readonly markers are set here. This ensures that when
	 * PaymentRestController::get_item_schema() merges these with
	 * get_readonly_schema(), get_endpoint_args_for_item_schema() sees every
	 * field here as writable and correctly generates create-endpoint args for
	 * them all.
	 *
	 * Used as:
	 *  - the `input_schema` for the Abilities API create-payment ability
	 *  - the writable portion of the REST API item schema
	 *
	 * @return array
	 */
	public static function get_input_schema(): array {
		return [
			'type'       => 'object',
			'required'   => [ 'total_amount' ],
			'properties' => [
				'config_id'        => [
					'type'        => 'integer',
					'description' => __( 'ID of the Knit Pay gateway configuration to use. Omit or set to 0 to use the default configuration. Configuration IDs are visible at Knit Pay → Configurations in the WordPress admin.', 'knit-pay-lang' ),
				],
				'total_amount'     => array_merge(
					self::money_schema( __( 'The total amount to charge the customer.', 'knit-pay-lang' ) ),
					[ 'required' => true ]
				),
				'payment_method'   => [
					'type'        => 'string',
					'description' => __( 'Payment method to pre-select for the customer. Pass "" or omit to let the customer choose.', 'knit-pay-lang' ),
					'enum'        => array_merge( [ '' ], PaymentMethods::get_active_payment_methods() ),
				],
				'description'      => [
					'type'        => 'string',
					'description' => __( 'Human-readable description of what the customer is paying for, e.g. "Order #123" or "Registration fee".', 'knit-pay-lang' ),
				],
				'order_id'         => [
					'type'        => 'string',
					'description' => __( 'Your internal order or invoice identifier. Stored alongside the payment for reconciliation.', 'knit-pay-lang' ),
				],
				'source'           => [
					'type'        => 'object',
					'description' => __( 'Source of the payment request, useful for identifying which system or integration initiated it.', 'knit-pay-lang' ),
					'properties'  => [
						'key'   => [
							'type'        => 'string',
							'description' => __( 'Source identifier key, e.g. "api", "ai", "woocommerce".', 'knit-pay-lang' ),
						],
						'value' => [
							'type'        => 'string',
							'description' => __( 'Source identifier value, e.g. an order ID in the source system.', 'knit-pay-lang' ),
						],
					],
				],
				'redirect_url'     => [
					'type'        => 'string',
					'format'      => 'uri',
					'description' => __( 'URL where the customer is redirected after a payment attempt (success or failure). A kp_payment_id query parameter is appended automatically.', 'knit-pay-lang' ),
				],
				'notify_url'       => [
					'type'        => 'string',
					'format'      => 'uri',
					'description' => __( 'Webhook URL that Knit Pay will POST payment data to whenever the payment status changes.', 'knit-pay-lang' ),
				],
				'customer'         => self::customer_schema(),
				'billing_address'  => self::address_schema(),
				'shipping_address' => self::address_schema(),
			],
		];
	}

	/**
	 * Returns only the truly read-only response fields.
	 *
	 * These fields are NEVER accepted as input and have NO overlap with
	 * get_input_schema(). This is intentional — keeping them disjoint means
	 * PaymentRestController::get_item_schema() can safely merge both with
	 * array_merge() without any writable field accidentally gaining readonly.
	 *
	 * Fields that exist in both input and response (total_amount, description,
	 * order_id, source, payment_method, customer, billing_address,
	 * shipping_address) are defined only in get_input_schema() and will appear
	 * in responses without a readonly marker, which is correct behaviour.
	 *
	 * Used as:
	 *  - the `output_schema` for both Abilities
	 *  - the readonly portion of the REST API item schema
	 *
	 * @return array
	 */
	public static function get_readonly_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'id'               => [
					'type'        => 'integer',
					'description' => __( 'Unique Knit Pay payment ID. Use with knit-pay/get-payment to check status later.', 'knit-pay-lang' ),
					'readonly'    => true,
					'context'     => [ 'view' ],
				],
				'pay_redirect_url' => [
					'type'        => 'string',
					'format'      => 'uri',
					'description' => __( 'Payment link to share with the customer. Opening this URL takes them to the gateway to complete payment.', 'knit-pay-lang' ),
					'readonly'    => true,
					'context'     => [ 'view' ],
				],
				'status'           => [
					'type'        => 'string',
					'description' => __( 'Current payment status. Values: open (not yet paid), success (completed), failure (failed), cancelled (customer cancelled), expired, reserved.', 'knit-pay-lang' ),
					'readonly'    => true,
					'context'     => [ 'view' ],
				],
				'transaction_id'   => [
					'type'        => 'string',
					'description' => __( 'Transaction ID assigned by the payment gateway. Available after the customer completes or attempts payment.', 'knit-pay-lang' ),
					'readonly'    => true,
					'context'     => [ 'view' ],
				],
				'mode'             => [
					'type'        => 'string',
					'description' => __( 'Payment mode: "live" or "test".', 'knit-pay-lang' ),
					'readonly'    => true,
					'context'     => [ 'view' ],
				],
				'gateway'          => [
					'type'        => 'object',
					'description' => __( 'Gateway configuration reference.', 'knit-pay-lang' ),
					'readonly'    => true,
					'context'     => [ 'view' ],
				],
			],
		];
	}

	/**
	 * Convenience wrapper: combined input + readonly schema as a single object.
	 *
	 * Used as the output_schema for both Abilities. It represents the full
	 * shape of a payment response.
	 *
	 * @return array
	 */
	public static function get_output_schema(): array {
		$input_props    = self::get_input_schema()['properties'];
		$readonly_props = self::get_readonly_schema()['properties'];

		return [
			'type'       => 'object',
			'properties' => array_merge( $input_props, $readonly_props ),
		];
	}

	// -------------------------------------------------------------------------
	// Execution logic
	// -------------------------------------------------------------------------

	/**
	 * Create a payment from an input array.
	 *
	 * Shared by PaymentRestController::create_item() and the
	 * knit-pay/create-payment Ability execute callback.
	 *
	 * Returns the same full payment data as get_payment() so that callers
	 * (including existing REST API integrations) receive the complete payment
	 * object, not just a subset.
	 *
	 * @param array $params Associative array of payment parameters.
	 * @return array|WP_Error Full payment data array or WP_Error on failure.
	 */
	public static function create_payment( array $params ) {
		try {
			// Normalise optional string fields: treat empty string as not provided.
			foreach ( [ 'payment_method', 'description', 'order_id', 'redirect_url', 'notify_url' ] as $field ) {
				if ( isset( $params[ $field ] ) && '' === $params[ $field ] ) {
					unset( $params[ $field ] );
				}
			}

			// Normalise optional object fields: unset when empty.
			//
			// The Abilities API and REST API parse JSON objects as PHP arrays.
			// wp_json_encode() encodes an empty PHP array as `[]` (JSON array),
			// not `{}` (JSON object). The downstream from_json() calls (Customer,
			// Address, etc.) check is_object() and throw when they receive an array.
			// Unsetting empty objects avoids passing meaningless data and prevents
			// that exception.
			foreach ( [ 'source', 'customer', 'billing_address', 'shipping_address' ] as $field ) {
				if ( isset( $params[ $field ] ) && is_array( $params[ $field ] ) && empty( $params[ $field ] ) ) {
					unset( $params[ $field ] );
				}
			}

			// Validate total_amount has the required value and currency.
			// An empty total_amount: {} passes schema validation because the
			// Abilities API does not fully validate nested object properties.
			if ( empty( $params['total_amount'] ) || ! is_array( $params['total_amount'] )
				|| empty( $params['total_amount']['value'] ) || empty( $params['total_amount']['currency'] ) ) {
				return new WP_Error(
					'knit_pay_invalid_amount',
					__( 'total_amount must include a non-zero "value" and a "currency" code, e.g. {"value": 100, "currency": "INR"}.', 'knit-pay-lang' ),
					[ 'status' => 400 ]
				);
			}

			// config_id: treat missing, null, or <= 0 as "use default gateway".
			$config_id = isset( $params['config_id'] ) && (int) $params['config_id'] > 0
				? (int) $params['config_id']
				: null;

			if ( null !== $config_id ) {
				$gateway = Plugin::get_gateway( $config_id );
				if ( ! $gateway ) {
					return new WP_Error(
						'knit_pay_invalid_config',
						sprintf(
							/* translators: %d: Gateway configuration ID */
							__( 'Payment failed because gateway configuration with ID `%d` does not exist.', 'knit-pay-lang' ),
							$config_id
						),
						[ 'status' => 400 ]
					);
				}
			}

			$req_object = json_decode( wp_json_encode( $params ) );

			$payment = new Payment();

			PaymentInfoHelper::from_json( $req_object, $payment );

			if ( isset( $req_object->title ) ) {
				$payment->title = $req_object->title;
			}

			$payment->set_total_amount( MoneyJsonTransformer::from_json( $req_object->total_amount ) );
			$payment->config_id = $config_id;

			$payment = Plugin::start_payment( $payment );

			// redirect_url / notify_url: only store if non-empty string.
			if ( ! empty( $params['redirect_url'] ) ) {
				$payment->set_meta( 'rest_redirect_url', $params['redirect_url'] );
			}
			if ( ! empty( $params['notify_url'] ) ) {
				$payment->set_meta( 'rest_notify_url', $params['notify_url'] );
			}
			$payment->save();

			// Return the full payment data for backward compatibility with
			// existing REST API integrations that read fields from the create
			// response (action_url, payment_method, etc.).
			return self::build_payment_data( $payment );
		} catch ( \Exception $e ) {
			return new WP_Error( 'knit_pay_create_failed', $e->getMessage(), [ 'status' => 500 ] );
		}
	}

	/**
	 * Retrieve a payment by ID and return its data.
	 *
	 * Shared by PaymentRestController::get_item() and the
	 * knit-pay/get-payment Ability execute callback.
	 *
	 * @param int $payment_id Knit Pay payment ID.
	 * @return array|WP_Error Payment data array or WP_Error if not found.
	 */
	public static function get_payment( int $payment_id ) {
		$payment = \get_pronamic_payment( $payment_id );

		if ( null === $payment ) {
			return new WP_Error(
				'knit_pay_payment_not_found',
				sprintf(
					/* translators: %s: payment ID */
					__( 'Could not find payment with ID `%s`.', 'knit-pay-lang' ),
					$payment_id
				),
				[ 'status' => 404 ]
			);
		}

		return self::build_payment_data( $payment );
	}

	/**
	 * Build the payment data array from a Payment object.
	 *
	 * Single place where the response shape is defined. Both create_payment()
	 * and get_payment() call this so the response structure is always identical,
	 * ensuring backward compatibility across both endpoints.
	 *
	 * @param Payment $payment Payment object.
	 * @return array
	 */
	private static function build_payment_data( Payment $payment ): array {
		$payment_json = $payment->get_json();

		$data = [];

		// Always-present readonly fields.
		$data['id']               = $payment->get_id();
		$data['pay_redirect_url'] = $payment->get_pay_redirect_url();

		// Scalar readonly fields — only include when the payment actually has a value.
		// Omitting absent/null fields prevents the Abilities API output validator from
		// rejecting a null where the schema declares type: string/object.
		if ( isset( $payment_json->status ) ) {
			$data['status'] = $payment_json->status;
		}
		if ( isset( $payment_json->transaction_id ) ) {
			$data['transaction_id'] = $payment_json->transaction_id;
		}
		if ( isset( $payment_json->mode ) ) {
			$data['mode'] = $payment_json->mode;
		}
		if ( isset( $payment_json->gateway ) ) {
			$data['gateway'] = (array) $payment_json->gateway;
		}

		// Input fields echoed back — only when present.
		if ( isset( $payment_json->total_amount ) ) {
			$data['total_amount'] = (array) $payment_json->total_amount;
		}
		if ( isset( $payment_json->payment_method ) ) {
			$data['payment_method'] = $payment_json->payment_method;
		}
		if ( isset( $payment_json->description ) ) {
			$data['description'] = $payment_json->description;
		}
		if ( isset( $payment_json->order_id ) ) {
			$data['order_id'] = $payment_json->order_id;
		}
		if ( isset( $payment_json->source ) ) {
			$data['source'] = (array) $payment_json->source;
		}
		if ( isset( $payment_json->customer ) ) {
			$data['customer'] = (array) $payment_json->customer;
		}
		if ( isset( $payment_json->billing_address ) ) {
			$data['billing_address'] = (array) $payment_json->billing_address;
		}
		if ( isset( $payment_json->shipping_address ) ) {
			$data['shipping_address'] = (array) $payment_json->shipping_address;
		}

		return $data;
	}
}
