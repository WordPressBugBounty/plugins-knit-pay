<?php

namespace KnitPay\Gateways\Razorpay;

use Exception;
use KnitPay\Utils as KnitPayUtils;
use Konekt\PdfInvoice\InvoicePrinter;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\PhpseclibV3\SftpAdapter;
use League\Flysystem\PhpseclibV3\SftpConnectionProvider;
use League\Flysystem\UnableToWriteFile;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic\WordPress\Pay\Payments\PaymentStatus as Core_Statuses;

/**
 * Title: Razorpay Invoice Uploader
 * Copyright: 2020-2026 Knit Pay
 *
 * @author Knit Pay
 * @version 9.1.0.0
 * @since   9.1.0.0
 */
class InvoiceUploader extends InvoicePrinter {
	const RAZORPAY_SFTP_HOST = 'sftp.razorpay.com';
	const RAZORPAY_SFTP_PATH = '/invoiceUpload/automated/';
	const RAZORPAY_SFTP_PORT = 22;

	private $config;

	private static $instance;

	/**
	 * Get instance.
	 *
	 * @return static
	 */
	public static function get_instance() {
		if ( null === static::$instance ) {
			self::$instance = new static();
		}

		return self::$instance;
	}

	/**
	 * Construct invoice uploader object.
	 * Private constructor to enforce singleton pattern.
	 */
	private function __construct() {
		add_action( 'knit_pay_razorpay_invoice_upload', [ $this, 'upload_invoice' ] );
	}

	/**
	 * Check if phpseclib3 is available.
	 *
	 * @return bool
	 */
	public static function phpseclib_exists() {
		// Try to load phpseclib3 dependencies from available plugins.
		InvoiceUploader::load_dependencies();

		return class_exists( 'phpseclib3\Net\SFTP' );
	}

	/**
	 * Load phpseclib3 dependencies from available plugins.
	 *
	 * @return void
	 */
	public static function load_dependencies() {
		// Include composer autoload.php of other plugins to load phpseclib3 library.
		if ( is_readable( WP_PLUGIN_DIR . '/ssh-sftp-updater-support/vendor/autoload.php' ) ) {
			require_once WP_PLUGIN_DIR . '/ssh-sftp-updater-support/vendor/autoload.php';
		} elseif ( is_readable( WP_PLUGIN_DIR . '/wp-database-backup/includes/admin/Destination/SFTP/vendor/autoload.php' ) ) {
			require_once WP_PLUGIN_DIR . '/wp-database-backup/includes/admin/Destination/SFTP/vendor/autoload.php';
		} elseif ( is_readable( WP_PLUGIN_DIR . '/backwpup/vendor/autoload.php' ) ) {
			require_once WP_PLUGIN_DIR . '/backwpup/vendor/autoload.php';
		} elseif ( is_readable( WP_PLUGIN_DIR . '/wp-social/lib/composer/vendor/autoload.php' ) ) {
			require_once WP_PLUGIN_DIR . '/wp-social/lib/composer/vendor/autoload.php';
		} elseif ( is_readable( WP_PLUGIN_DIR . '/boldgrid-backup/vendor/autoload.php' ) ) {
			require_once WP_PLUGIN_DIR . '/boldgrid-backup/vendor/autoload.php';
		} elseif ( is_readable( WP_PLUGIN_DIR . '/instawp-connect/vendor/autoload.php' ) ) {
			require_once WP_PLUGIN_DIR . '/instawp-connect/vendor/autoload.php';
		} elseif ( is_readable( WP_PLUGIN_DIR . '/jc-importer/libs/autoload.php' ) ) {
			require_once WP_PLUGIN_DIR . '/jc-importer/libs/autoload.php';
		} elseif ( is_readable( WP_PLUGIN_DIR . '/wp-stateless/lib/Google/vendor/autoload.php' ) ) {
			require_once WP_PLUGIN_DIR . '/wp-stateless/lib/Google/vendor/autoload.php';
		} elseif ( is_readable( WP_PLUGIN_DIR . '/wpo365-login/vendor/autoload.php' ) ) {
			require_once WP_PLUGIN_DIR . '/wpo365-login/vendor/autoload.php';
		} elseif ( is_readable( WP_PLUGIN_DIR . '/wpo365-msgraphmailer/vendor/autoload.php' ) ) {
			require_once WP_PLUGIN_DIR . '/wpo365-msgraphmailer/vendor/autoload.php';
		} elseif ( is_readable( WP_PLUGIN_DIR . '/woocommerce-pdf-ips-pro/vendor/autoload.php' ) ) {
			require_once WP_PLUGIN_DIR . '/woocommerce-pdf-ips-pro/vendor/autoload.php';
		}
	}

	/**
	 * Prevent cloning of the instance.
	 */
	private function __clone() {}

	/**
	 * Prevent unserialization of the instance.
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton' );
	}

	/**
	 * Upload invoice scheduler.
	 *
	 * @param Payment $payment Payment.
	 */
	public function upload_invoice_scheduler( $payment ) {
		/** @var Gateway $gateway */
		$gateway      = $payment->get_gateway();
		$this->config = $gateway->config;

		if ( empty( $this->config->sftp_username ) ) {
			return;
		} elseif ( $payment->get_meta( 'razorpay_invoice_uploaded' ) ) {
			if ( filter_has_var( INPUT_GET, 'pronamic_pay_check_status' ) ) {
				return $this->create_invoice( $payment, 'D' );
			}
			return;
		}

		if ( ! InvoiceUploader::phpseclib_exists() ) {
			throw new Exception( 'Invoice upload failed. phpseclib3 library is not available. Kindly activate any plugin which has phpseclib3 library available.' );
		}

		$scheduler_args = [ 'payment_id' => $payment->get_id() ];
		// Check if task is already scheduled to avoid duplicates.
		if ( \as_has_scheduled_action( 'knit_pay_razorpay_invoice_upload', $scheduler_args, 'knit-pay' ) ) {
			return;
		}

		// if debug mode is not enabled then don't upload test payment invoices.
		if ( 'test' === $payment->get_mode() && ! knit_pay_plugin()->is_debug_mode() ) {
			return;
		}

		// No need to wait, upload directly if payment status is getting checked by cron.
		if ( ! ( array_key_exists( 'payment', $_GET ) && array_key_exists( 'key', $_GET ) ) ) {
			$this->upload_invoice( $payment->get_id() );
			return;
		}

		// Schedule invoice upload at Razorpay SFTP Server.
		\as_enqueue_async_action(
			'knit_pay_razorpay_invoice_upload',
			$scheduler_args,
			'knit-pay'
		);
	}

	/**
	 * Create invoice to upload.
	 * 
	 * @param Payment $payment Payment.
	 * @return String
	 */
	private function create_invoice( Payment $payment, $destination = 'S' ) {
		$customer         = $payment->get_customer();
		$customer_address = ( null === $payment->get_shipping_address() ) ? $payment->get_billing_address() : $payment->get_shipping_address();
		$currency_code    = $payment->get_total_amount()->get_currency()->get_alphabetic_code();
		$lang             = $customer->get_language();
		$invoice_number   = $payment->get_meta( 'razorpay_order_id' );
		$total_amount     = $payment->get_total_amount()->get_value();

		$invoice                      = new InvoicePrinter( InvoicePrinter::INVOICE_SIZE_A4, $currency_code, $lang );
		$this->config->checkout_image = get_post_meta( $this->config->config_id, '_pronamic_gateway_razorpay_checkout_image', true );
		if ( ! empty( $this->config->checkout_image ) ) {
			$this->config->checkout_image = ABSPATH . ltrim( $this->config->checkout_image, '/' );
		}

		/* Header Settings */
		if ( ! empty( $this->config->checkout_image ) && is_readable( $this->config->checkout_image ) ) {
			$invoice->setLogo( $this->config->checkout_image );
		}

		$invoice->setColor( '#007fff' );
		$invoice->setType( 'Invoice' );
		$invoice->setReference( $invoice_number );
		$invoice->setDate( $payment->get_date()->format( 'd M Y' ) );
		$invoice->addCustomHeader( 'Payment ID', $payment->get_transaction_id() );
		$invoice->addCustomHeader( 'Order ID', $payment->get_order_id() );
		
		// Store Address from WooCommerce
		$brand_name = $this->config->company_name;
		if ( empty( $brand_name ) ) {
			$brand_name = get_bloginfo( 'name' );
		}
		$default_country = get_option( 'woocommerce_default_country' );
		$country_parts   = $default_country ? explode( ':', $default_country ) : [ '', '' ];
		$country_code    = $country_parts[0] ?? '';
		$state_code      = $country_parts[1] ?? '';

		$store_address_array = $this->filter_address(
			[
				$brand_name,
				get_option( 'woocommerce_store_address' ),
				get_option( 'woocommerce_store_address_2' ),
				get_option( 'woocommerce_store_city' ),
				KnitPayUtils::get_state_name( $state_code, $country_code ),
				get_option( 'woocommerce_store_postcode' ),
				KnitPayUtils::get_country_name( $country_code ),
			]
		);
		$invoice->setFrom( $store_address_array );

		// Customer Billing Address
		$customer_name = $customer_address->get_name();
		$address_array = $this->filter_address(
			[
				$customer_name ? (string) $customer_name : '',
				$customer_address->get_company_name(),
				$customer_address->get_line_1(),
				$customer_address->get_line_2(),
				$customer_address->get_city(),
				KnitPayUtils::get_state_name( $customer_address->get_region(), $customer_address->get_country() ),
				$customer_address->get_postal_code(),
				KnitPayUtils::get_country_name( $customer_address->get_country_code() ),
			]
		);
		$invoice->setTo( $address_array );
		
		// Adding Items in table.
		$lines = $payment->get_lines();
		if ( isset( $lines ) ) {
			foreach ( $lines as $line ) {
				$description = is_null( $line->get_description() ) ? '' : $line->get_description();
				$invoice->addItem( $line->get_name(), $description, $line->get_quantity(), false, $line->get_unit_price()->get_value(), false, $line->get_total_amount()->get_value() );
			}
		} else {
			$invoice->addItem( $payment->get_description(), '', 1, false, $total_amount, false, $total_amount );
		}

		/*
		Set totals alignment */
		// $invoice->setTotalsAlignment( 'horizontal' );
		/* Add totals */
		$invoice->addTotal( 'Total', $total_amount, true );

		/* Set badge */
		$invoice->addBadge( 'Paid' );

		/*
		Set footer note */
		$invoice->setFooternote( $brand_name );

		/* Render */
		try {
			return $invoice->render( $invoice_number . '.pdf', $destination ); /* I => Display on browser, D => Force Download, F => local path save, S => return document path */
		} catch ( Exception $e ) {
			throw new Exception( 'Error: ' . $e->getMessage() );
		}
	}

	/**
	 * Upload invoice.
	 *
	 * @param int|string $payment_id Payment ID.
	 * @return void
	 */
	public function upload_invoice( $payment_id ) {
		$payment = \get_pronamic_payment( $payment_id );

		// No payment found, unable to check status.
		if ( null === $payment ) {
			return;
		}

		// Don't proceed if invoice already uploaded.
		if ( $payment->get_meta( 'razorpay_invoice_uploaded' ) ) {
			return;
		}

		/** @var Gateway $gateway */
		$gateway      = $payment->get_gateway();
		$this->config = $gateway->config;

		if ( 'in-import-flow' !== $this->config->country
		|| Core_Statuses::SUCCESS !== $payment->get_status()
		|| empty( $this->config->sftp_username ) ) {
			return;
		}

		// Create Invoice
		$invoice_string = $this->create_invoice( $payment );

		if ( empty( $invoice_string ) ) {
			throw new Exception( 'Error: Could not create invoice.' );
		}

		$sftp_connection_provider = new SftpConnectionProvider(
			self::RAZORPAY_SFTP_HOST, // host (required)
			$this->config->sftp_username, // username (required)
			null, // password (optional, default: null) set to null if privateKey is used
			$this->config->sftp_private_key,
			null,
			self::RAZORPAY_SFTP_PORT,
			false,
			10,
			2
		);

		// $adapter = new LocalFilesystemAdapter( __DIR__ . '/storage' ); // Upload on local storage for testing only.
		$adapter = new SftpAdapter( $sftp_connection_provider, self::RAZORPAY_SFTP_PATH );

		try {
			$filesystem = new Filesystem( $adapter );

			$is_debug       = knit_pay_plugin()->is_debug_mode();
			$invoice_number = $payment->get_meta( 'razorpay_order_id' );
			$date           = $payment->get_date()->format( 'Y-m-d' );

			if ( 'test' === $payment->get_mode() ) {
				$invoice_suffix = '-test';
			} elseif ( $is_debug ) {
				$invoice_suffix = '-draft';
			} else {
				$invoice_suffix = '';
			}

			$path = '/' . $this->config->merchant_id . '/' . $date . '/' . $invoice_number . $invoice_suffix . '.pdf';

			$filesystem->write( $path, $invoice_string );

			if ( $filesystem->fileExists( $path ) ) {
				// Allow re-upload if debug mode is enabled.
				if ( ! $is_debug ) {
					$payment->set_meta( 'razorpay_invoice_uploaded', true );
				}
				$payment->add_note( 'Razorpay invoice uploaded at <strong>' . $path . '</strong>' );
				$payment->save();
			}
		} catch ( FilesystemException | UnableToWriteFile $exception ) {
			as_schedule_single_action(
				time() + 3 * HOUR_IN_SECONDS,
				'knit_pay_razorpay_invoice_upload',
				[
					'payment_id' => $payment->get_id(),
				],
				'knit-pay'
			);

			throw new Exception( 'Error: ' . $exception->getMessage() );
		}
	}

	private function filter_address( $address_array ) {
		$filtered_address = array_filter(
			$address_array,
			function ( $value ) {
				return $value !== null && $value !== '';
			}
		);

		return array_values( $filtered_address );
	}
}
