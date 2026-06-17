<?php
/**
 * Knit Pay Reports – REST Controller
 *
 * Extends WP_REST_Controller following the same pattern as
 * PaymentRestController. Serves report data at:
 *   /wp-json/knit-pay/v1/reports/overview
 *   /wp-json/knit-pay/v1/reports/transactions
 *   /wp-json/knit-pay/v1/reports/gateway-performance
 *   /wp-json/knit-pay/v1/reports/payment-methods
 *   /wp-json/knit-pay/v1/reports/filter-options
 *   /wp-json/knit-pay/v1/reports/export
 *
 * @package KnitPay\Reports
 */

namespace KnitPay\Reports;

use KnitPay\Reports\Exporter\CsvExporter;
use KnitPay\Reports\Exporter\PdfExporter;
use Pronamic\WordPress\Pay\Payments\PaymentStatus;
use Pronamic\WordPress\Pay\Plugin;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class ReportsRestController extends WP_REST_Controller {

	protected $rest_base = 'knit-pay';

	public function __construct() {
		$this->namespace     = $this->rest_base . '/v1';
		$this->resource_name = 'reports';
	}

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->resource_name . '/overview',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_overview' ],
					'permission_callback' => [ $this, 'reports_permissions_check' ],
					'args'                => $this->get_filter_args(),
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->resource_name . '/transactions',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_transactions' ],
					'permission_callback' => [ $this, 'reports_permissions_check' ],
					'args'                => array_merge(
						$this->get_filter_args(),
						[
							'page'     => [
								'description' => __( 'Current page of the collection.', 'knit-pay-lang' ),
								'type'        => 'integer',
								'default'     => 1,
								'minimum'     => 1,
							],
							'per_page' => [
								'description' => __( 'Maximum number of items to be returned in result set.', 'knit-pay-lang' ),
								'type'        => 'integer',
								'default'     => 25,
								'minimum'     => 1,
								'maximum'     => 250,
							],
						]
					),
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->resource_name . '/gateway-performance',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_gateway_performance' ],
					'permission_callback' => [ $this, 'reports_permissions_check' ],
					'args'                => $this->get_filter_args(),
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->resource_name . '/payment-methods',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_payment_methods' ],
					'permission_callback' => [ $this, 'reports_permissions_check' ],
					'args'                => $this->get_filter_args(),
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->resource_name . '/sources',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_sources' ],
					'permission_callback' => [ $this, 'reports_permissions_check' ],
					'args'                => $this->get_filter_args(),
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->resource_name . '/refunds',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_refunds' ],
					'permission_callback' => [ $this, 'reports_permissions_check' ],
					'args'                => $this->get_filter_args(),
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->resource_name . '/filter-options',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_filter_options' ],
					'permission_callback' => [ $this, 'reports_permissions_check' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->resource_name . '/export',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'export_csv' ],
					'permission_callback' => [ $this, 'export_permissions_check' ],
					'args'                => array_merge(
						$this->get_filter_args(),
						[
							'report' => [
								'description' => __( 'Report type to export.', 'knit-pay-lang' ),
								'type'        => 'string',
								'enum'        => [ 'overview', 'transactions', 'gateway-performance', 'payment-methods', 'sources', 'refunds' ],
								'default'     => 'transactions',
							],
							'format' => [
								'description' => __( 'Export format.', 'knit-pay-lang' ),
								'type'        => 'string',
								'enum'        => [ 'csv', 'pdf' ],
								'default'     => 'csv',
							],
							'charts' => [
								'description' => __( 'Chart images as base64 PNG strings (for PDF export).', 'knit-pay-lang' ),
								'type'        => 'object',
							],
						]
					),
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->resource_name . '/bulk-action',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'handle_bulk_action' ],
					'permission_callback' => [ $this, 'reports_permissions_check' ],
					'args'                => [
						'action' => [
							'required'    => true,
							'type'        => 'string',
							'enum'        => [ 'check_status', 'trash', 'restore', 'delete_permanently' ],
							'description' => __( 'Bulk action to perform.', 'knit-pay-lang' ),
						],
						'ids'    => [
							'required'    => true,
							'type'        => 'array',
							'items'       => [ 'type' => 'integer' ],
							'maxItems'    => 250,
							'description' => __( 'Payment post IDs.', 'knit-pay-lang' ),
						],
					],
				],
			]
		);
	}

	public function reports_permissions_check( WP_REST_Request $request ): bool|WP_Error {
		$post_type = get_post_type_object( 'pronamic_payment' );

		if ( ! $post_type || ! current_user_can( $post_type->cap->edit_posts ) ) {
			return new WP_Error(
				'rest_cannot_view_reports',
				__( 'Sorry, you are not allowed to view reports.', 'knit-pay-lang' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return true;
	}

	public function export_permissions_check( WP_REST_Request $request ): bool|WP_Error {
		$nonce = $request->get_header( 'x_wp_nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'rest_cookie_invalid_nonce',
				__( 'Cookie nonce is invalid.', 'knit-pay-lang' ),
				[ 'status' => 403 ]
			);
		}

		$post_type = get_post_type_object( 'pronamic_payment' );
		if ( ! $post_type || ! current_user_can( $post_type->cap->edit_posts ) ) {
			return new WP_Error(
				'rest_cannot_view_reports',
				__( 'Sorry, you are not allowed to export reports.', 'knit-pay-lang' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return true;
	}

	public function get_overview( WP_REST_Request $request ): WP_REST_Response {
		$params = $request->get_params();
		$data   = ReportsApiHelper::get_overview( $params );
		return rest_ensure_response( $data );
	}

	public function get_transactions( WP_REST_Request $request ): WP_REST_Response {
		$params = $request->get_params();
		$data   = ReportsApiHelper::get_transactions( $params );
		return rest_ensure_response( $data );
	}

	public function get_gateway_performance( WP_REST_Request $request ): WP_REST_Response {
		$params = $request->get_params();
		$data   = ReportsApiHelper::get_gateway_performance( $params );
		return rest_ensure_response( $data );
	}

	public function get_payment_methods( WP_REST_Request $request ): WP_REST_Response {
		$params = $request->get_params();
		$data   = ReportsApiHelper::get_payment_methods( $params );
		return rest_ensure_response( $data );
	}

	public function get_sources( WP_REST_Request $request ): WP_REST_Response {
		$params = $request->get_params();
		$data   = ReportsApiHelper::get_sources( $params );
		return rest_ensure_response( $data );
	}

	public function get_refunds( WP_REST_Request $request ): WP_REST_Response {
		$params = $request->get_params();
		$data   = ReportsApiHelper::get_refunds( $params );
		return rest_ensure_response( $data );
	}

	public function get_filter_options( WP_REST_Request $request ): WP_REST_Response {
		$data = ReportsApiHelper::get_filter_options();
		return rest_ensure_response( $data );
	}

	public function handle_bulk_action( WP_REST_Request $request ): WP_REST_Response {
		$action = $request->get_param( 'action' );
		$ids    = $request->get_param( 'ids' );

		if ( empty( $ids ) || ! is_array( $ids ) ) {
			return new WP_REST_Response( [ 'error' => 'No IDs provided' ], 400 );
		}

		$nonce = $request->get_header( 'x_wp_nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_REST_Response(
				[ 'error' => __( 'Nonce verification failed.', 'knit-pay-lang' ) ],
				403
			);
		}

		$ids = array_map( 'intval', $ids );
		$ids = array_filter( $ids );

		$result = [
			'action'           => $action,
			'processed'        => 0,
			'status_updated'   => 0,
			'skipped'          => 0,
			'skipped_not_open' => 0,
			'trashed'          => 0,
			'restored'         => 0,
			'deleted'          => 0,
			'unsupported'      => [],
		];

		if ( 'check_status' === $action ) {
			if ( ! current_user_can( 'edit_payments' ) ) {
				return new WP_REST_Response(
					[ 'error' => __( 'You do not have permission to check payment status.', 'knit-pay-lang' ) ],
					403
				);
			}
			foreach ( $ids as $post_id ) {
				++$result['processed'];
				$payment = \get_pronamic_payment( $post_id );

				if ( null === $payment ) {
					++$result['skipped'];
					continue;
				}

				if ( PaymentStatus::OPEN !== $payment->status && '' !== $payment->status ) {
					++$result['skipped_not_open'];
					continue;
				}

				$config_id = $payment->get_config_id();

				if ( null === $config_id ) {
					++$result['skipped'];
					continue;
				}

				if ( ! \in_array( $config_id, $result['unsupported'], true ) ) {
					$gateway = $payment->get_gateway();

					if ( null !== $gateway && ! $gateway->supports( 'payment_status_request' ) ) {
						$result['unsupported'][] = $config_id;
					}
				}

				if ( \in_array( $config_id, $result['unsupported'], true ) ) {
					continue;
				}

				Plugin::update_payment( $payment, false );
				++$result['status_updated'];
			}
		} elseif ( 'trash' === $action ) {
			if ( ! current_user_can( 'delete_payments' ) ) {
				return new WP_REST_Response(
					[ 'error' => __( 'You do not have permission to move payments to trash.', 'knit-pay-lang' ) ],
					403
				);
			}
			foreach ( $ids as $post_id ) {
				++$result['processed'];
				if ( get_post_type( $post_id ) !== 'pronamic_payment' ) {
					++$result['skipped'];
					continue;
				}
				if ( ! current_user_can( 'delete_post', $post_id ) ) {
					++$result['skipped'];
					continue;
				}
				if ( wp_trash_post( $post_id ) ) {
					++$result['trashed'];
				} else {
					++$result['skipped'];
				}
			}
		} elseif ( 'restore' === $action ) {
			if ( ! current_user_can( 'edit_payments' ) ) {
				return new WP_REST_Response(
					[ 'error' => __( 'You do not have permission to restore payments.', 'knit-pay-lang' ) ],
					403
				);
			}
			foreach ( $ids as $post_id ) {
				++$result['processed'];
				$post = get_post( $post_id );
				if ( ! $post || 'pronamic_payment' !== $post->post_type ) {
					++$result['skipped'];
					continue;
				}
				if ( 'trash' !== $post->post_status ) {
					++$result['skipped'];
					continue;
				}
				if ( ! current_user_can( 'delete_post', $post_id ) ) {
					++$result['skipped'];
					continue;
				}
				if ( wp_untrash_post( $post_id ) ) {
					++$result['restored'];
				} else {
					++$result['skipped'];
				}
			}
		} elseif ( 'delete_permanently' === $action ) {
			if ( ! current_user_can( 'delete_payments' ) ) {
				return new WP_REST_Response(
					[ 'error' => __( 'You do not have permission to permanently delete payments.', 'knit-pay-lang' ) ],
					403
				);
			}
			foreach ( $ids as $post_id ) {
				++$result['processed'];
				$post = get_post( $post_id );
				if ( ! $post || 'pronamic_payment' !== $post->post_type ) {
					++$result['skipped'];
					continue;
				}
				if ( 'trash' !== $post->post_status ) {
					++$result['skipped'];
					continue;
				}
				if ( ! current_user_can( 'delete_post', $post_id ) ) {
					++$result['skipped'];
					continue;
				}
				if ( wp_delete_post( $post_id, true ) ) {
					++$result['deleted'];
				} else {
					++$result['skipped'];
				}
			}
		}

		return rest_ensure_response( $result );
	}

	public function export_csv( WP_REST_Request $request ) {
		$params      = $request->get_params();
		$report_type = $params['report'] ?? 'transactions';
		$format      = $params['format'] ?? 'csv';

		if ( 'pdf' === $format ) {
			return $this->export_pdf( $params, $report_type );
		}

		// Hard cap for transaction CSV exports. Default is intentionally conservative for shared hosting.
		// TODO: Revisit this limit after issue #9 (Payment object hydration per row) is fixed.
		// When #9 is resolved, the cap can be raised because each exported row will no longer instantiate a full Payment object.
		$exporter = new CsvExporter();
		$max_rows = (int) apply_filters( 'knit_pay_reports_csv_max_rows', 15000 );

		if ( 'transactions' === $report_type ) {
			$count_qb = ReportsApiHelper::build_query_from_params( $params );
			$total    = $count_qb->get_count();

			if ( $total > $max_rows ) {
				return new WP_REST_Response(
					[
						'code'    => 'too_many_rows',
						'message' => sprintf(
							/* translators: 1: Number of rows, 2: Maximum allowed rows */
							__( 'This selection contains %1$s payments. CSV export is limited to %2$s rows. Please narrow your date range or filters and try again.', 'knit-pay-lang' ),
							number_format_i18n( $total ),
							number_format_i18n( $max_rows )
						),
						'total'   => $total,
						'limit'   => $max_rows,
					],
					400
				);
			}
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=knit-pay-report-' . $report_type . '-' . gmdate( 'Y-m-d' ) . '.csv' );
		header( 'Cache-Control: no-cache, must-revalidate' );

		switch ( $report_type ) {
			case 'overview':
				$data = ReportsApiHelper::get_overview( $params );
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $exporter->export_overview( $data );
				break;
			case 'gateway-performance':
				$data = ReportsApiHelper::get_gateway_performance( $params );
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $exporter->export_gateway_performance( $data );
				break;
			case 'payment-methods':
				$data = ReportsApiHelper::get_payment_methods( $params );
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $exporter->export_payment_methods( $data );
				break;
			case 'sources':
				$data = ReportsApiHelper::get_sources( $params );
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $exporter->export_sources( $data );
				break;
			case 'refunds':
				$data = ReportsApiHelper::get_refunds( $params );
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $exporter->export_refunds( $data );
				break;
			case 'transactions':
			default:
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $exporter->stream_transactions_header() . "\r\n";
				$offset     = 0;
				$chunk_size = 500;
				$exported   = 0;
				while ( $exported < $max_rows ) {
					$transactions = ReportsApiHelper::get_transactions_chunk( $params, $offset, $chunk_size );
					if ( empty( $transactions ) ) {
						break;
					}
					foreach ( $transactions as $txn ) {
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						echo $exporter->format_transaction_row( $txn ) . "\r\n";
						++$exported;
						if ( $exported >= $max_rows ) {
							break 2;
						}
					}
					$offset += $chunk_size;
				}
				break;
		}
		exit;
	}

	private function export_pdf( array $params, string $report_type ): void {
		$chart_images = $params['charts'] ?? [];

		$from = $params['from'] ?? '';
		$to   = $params['to'] ?? '';
		if ( $from && $to ) {
			$date_label = $from . ' - ' . $to;
		} else {
			$date_label = __( 'Current Period', 'knit-pay-lang' );
		}

		$data = $this->get_export_data( $params, $report_type );

		$exporter = new PdfExporter( $report_type, $date_label, $chart_images );
		$pdf_data = $exporter->generate( $data );

		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename=knit-pay-report-' . $report_type . '-' . gmdate( 'Y-m-d' ) . '.pdf' );
		header( 'Cache-Control: no-cache, must-revalidate' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $pdf_data;
		exit;
	}

	private function get_export_data( array $params, string $report_type ): array {
		switch ( $report_type ) {
			case 'overview':
				return ReportsApiHelper::get_overview( $params );
			case 'gateway-performance':
				return ReportsApiHelper::get_gateway_performance( $params );
			case 'payment-methods':
				return ReportsApiHelper::get_payment_methods( $params );
			case 'sources':
				return ReportsApiHelper::get_sources( $params );
			case 'refunds':
				return ReportsApiHelper::get_refunds( $params );
			case 'transactions':
			default:
				return ReportsApiHelper::get_transactions( $params );
		}
	}

	private function get_filter_args(): array {
		$status_enum = ReportsApiHelper::get_valid_statuses();

		return [
			'from'           => [
				'description' => __( 'Start date (YYYY-MM-DD).', 'knit-pay-lang' ),
				'type'        => 'string',
				'format'      => 'date',
			],
			'to'             => [
				'description' => __( 'End date (YYYY-MM-DD).', 'knit-pay-lang' ),
				'type'        => 'string',
				'format'      => 'date',
			],
			'gateway'        => [
				'description' => __( 'Gateway configuration IDs.', 'knit-pay-lang' ),
				'type'        => 'array',
				'items'       => [ 'type' => 'integer' ],
			],
			'status'         => [
				'description' => __( 'Payment statuses.', 'knit-pay-lang' ),
				'type'        => 'array',
				'items'       => [
					'type' => 'string',
					'enum' => $status_enum,
				],
			],
			'trash'          => [
				'description' => __( 'Show trashed payments only.', 'knit-pay-lang' ),
				'type'        => 'boolean',
				'default'     => false,
			],
			'source'         => [
				'description' => __( 'Source plugins.', 'knit-pay-lang' ),
				'type'        => 'array',
				'items'       => [ 'type' => 'string' ],
			],
			'mode'           => [
				'description' => __( 'Payment mode.', 'knit-pay-lang' ),
				'type'        => 'string',
				'enum'        => [ 'live', 'test' ],
			],
			'currency'       => [
				'description' => __( 'Currency codes.', 'knit-pay-lang' ),
				'type'        => 'array',
				'items'       => [ 'type' => 'string' ],
			],
			'payment_method' => [
				'description' => __( 'Payment methods.', 'knit-pay-lang' ),
				'type'        => 'array',
				'items'       => [ 'type' => 'string' ],
			],
			'search'         => [
				'description' => __( 'Search by payment ID, transaction ID, customer name/email, source, or order ID.', 'knit-pay-lang' ),
				'type'        => 'string',
			],
			'orderby'        => [
				'description' => __( 'Sort column.', 'knit-pay-lang' ),
				'type'        => 'string',
				'enum'        => [ 'date', 'ID', 'amount' ],
				'default'     => 'date',
			],
			'order'          => [
				'description' => __( 'Sort direction.', 'knit-pay-lang' ),
				'type'        => 'string',
				'enum'        => [ 'ASC', 'DESC' ],
				'default'     => 'DESC',
			],
		];
	}
}
