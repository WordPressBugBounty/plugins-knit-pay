<?php
/**
 * Knit Pay Reports – API Helper
 *
 * Single source of truth for report schemas, filter validation,
 * and query execution. Used by both ReportsRestController (REST API)
 * and the admin page renderer (direct PHP call).
 *
 * Mirrors the PaymentApiHelper pattern.
 *
 * @package KnitPay\Reports
 */

namespace KnitPay\Reports;

use DateTimeImmutable;
use Pronamic\WordPress\Pay\Core\PaymentMethods as Core_PaymentMethods;
use Pronamic\WordPress\Pay\Payments\PaymentPostType;

class ReportsApiHelper {

	public const DEFAULT_PAYMENT_METHOD = 'Knit Pay';

	public static function get_status_map(): array {
		static $map = null;
		if ( null !== $map ) {
			return $map;
		}
		$map                               = PaymentPostType::get_payment_states();
		$map['payment_partially_refunded'] = __( 'Refunded (Partial)', 'knit-pay-lang' );
		return $map;
	}

	public static function get_valid_statuses(): array {
		return array_keys( self::get_status_map() );
	}

	public static function is_virtual_status( string $status ): bool {
		return 'payment_partially_refunded' === $status;
	}

	public static function gateway_display_name( int $config_id, array $gateway_names ): string {
		if ( isset( $gateway_names[ $config_id ] ) ) {
			return $gateway_names[ $config_id ];
		}

		$title = \get_the_title( $config_id );
		if ( ! \is_wp_error( $title ) && ! empty( $title ) && ! is_numeric( $title ) ) {
			return $title;
		}

		/* translators: %d: Gateway configuration ID */
		return \sprintf( \__( 'Gateway #%d', 'knit-pay-lang' ), $config_id );
	}

	public static function normalize_payment_method( string $method ): string {
		if ( empty( $method ) || 'knit_pay' === $method ) {
			return self::DEFAULT_PAYMENT_METHOD;
		}
		return $method;
	}

	public static function payment_method_name( string $method ): string {
		static $names = null;
		if ( null === $names ) {
			$names = ( new QueryBuilder() )->get_payment_method_list();
		}
		return $names[ $method ] ?? Core_PaymentMethods::get_name( $method, $method ) ?? $method;
	}

	public static function payment_method_names( array $methods ): array {
		$result = [];
		foreach ( $methods as $method ) {
			$result[ $method ] = self::payment_method_name( $method );
		}
		return $result;
	}

	public static function get_filter_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'from'           => [
					'type'        => 'string',
					'format'      => 'date',
					'description' => __( 'Start date (YYYY-MM-DD). Defaults to start of current month.', 'knit-pay-lang' ),
				],
				'to'             => [
					'type'        => 'string',
					'format'      => 'date',
					'description' => __( 'End date (YYYY-MM-DD). Defaults to today.', 'knit-pay-lang' ),
				],
				'gateway'        => [
					'type'        => 'array',
					'items'       => [ 'type' => 'integer' ],
					'description' => __( 'Gateway configuration IDs to filter by.', 'knit-pay-lang' ),
				],
				'status'         => [
					'type'        => 'array',
					'items'       => [
						'type' => 'string',
						'enum' => self::get_valid_statuses(),
					],
					'description' => __( 'Payment statuses to include.', 'knit-pay-lang' ),
				],
				'source'         => [
					'type'        => 'array',
					'items'       => [ 'type' => 'string' ],
					'description' => __( 'Source plugins to filter by.', 'knit-pay-lang' ),
				],
				'mode'           => [
					'type'        => 'string',
					'enum'        => [ 'live', 'test' ],
					'description' => __( 'Payment mode.', 'knit-pay-lang' ),
				],
				'currency'       => [
					'type'        => 'array',
					'items'       => [ 'type' => 'string' ],
					'description' => __( 'ISO 4217 currency codes.', 'knit-pay-lang' ),
				],
				'payment_method' => [
					'type'        => 'array',
					'items'       => [ 'type' => 'string' ],
					'description' => __( 'Payment methods to filter by.', 'knit-pay-lang' ),
				],
			],
		];
	}

	public static function build_query_from_params( array $params ): QueryBuilder {
		$qb = new QueryBuilder();

		$has_search = ! empty( $params['search'] );

		$from = $params['from'] ?? '';
		$to   = $params['to'] ?? '';

		if ( $has_search ) {
			// When searching, do not apply date range — search across all time.
		} elseif ( $from && $to ) {
			$date_from = DateTimeImmutable::createFromFormat( 'Y-m-d', $from );
			$date_to   = DateTimeImmutable::createFromFormat( 'Y-m-d', $to );
			if ( $date_from && $date_to ) {
				$qb->date_range( $date_from, $date_to );
			} else {
				$qb->date_range(
					new DateTimeImmutable( 'first day of this month' ),
					new DateTimeImmutable( 'today' )
				);
			}
		} elseif ( $from ) {
			$date_from = DateTimeImmutable::createFromFormat( 'Y-m-d', $from );
			if ( $date_from ) {
				$qb->date_range( $date_from, new DateTimeImmutable( 'today' ) );
			}
		} elseif ( $to ) {
			$date_to = DateTimeImmutable::createFromFormat( 'Y-m-d', $to );
			if ( $date_to ) {
				$qb->date_range( new DateTimeImmutable( 'first day of this month' ), $date_to );
			}
		} else {
			$qb->date_range(
				new DateTimeImmutable( 'first day of this month' ),
				new DateTimeImmutable( 'today' )
			);
		}

		if ( ! empty( $params['gateway'] ) ) {
			$gateways = is_array( $params['gateway'] ) ? array_map( 'intval', $params['gateway'] ) : [ (int) $params['gateway'] ];
			$qb->gateway( $gateways );
		}

		if ( ! empty( $params['trash'] ) ) {
			$qb->status( 'trash' );
		} elseif ( ! empty( $params['status'] ) ) {
			$statuses = is_array( $params['status'] ) ? $params['status'] : [ $params['status'] ];
			$statuses = array_filter( $statuses, fn( $s ) => in_array( $s, self::get_valid_statuses(), true ) );
			if ( ! empty( $statuses ) ) {
				$qb->status( $statuses );
			}
		}

		if ( ! empty( $params['source'] ) ) {
			$sources = is_array( $params['source'] ) ? $params['source'] : [ $params['source'] ];
			$qb->source( $sources );
		}

		if ( ! empty( $params['mode'] ) && in_array( $params['mode'], [ 'live', 'test' ], true ) ) {
			$qb->mode( $params['mode'] );
		}

		if ( ! empty( $params['currency'] ) ) {
			$currencies = is_array( $params['currency'] ) ? $params['currency'] : [ $params['currency'] ];
			$qb->currency( $currencies );
		}

		if ( ! empty( $params['payment_method'] ) ) {
			$methods = is_array( $params['payment_method'] ) ? $params['payment_method'] : [ $params['payment_method'] ];
			$qb->payment_method( $methods );
		}

		if ( ! empty( $params['search'] ) ) {
			$qb->search( sanitize_text_field( wp_unslash( $params['search'] ) ) );
		}

		if ( ! empty( $params['orderby'] ) ) {
			$qb->orderby( sanitize_key( $params['orderby'] ) );
		}

		if ( ! empty( $params['order'] ) ) {
			$qb->order( sanitize_key( $params['order'] ) );
		}

		return $qb;
	}

	public static function auto_trend_interval( array $params ): string {
		$from  = $params['from'] ?? $params['date_start'] ?? '';
		$to    = $params['to'] ?? $params['date_end'] ?? '';
		$end   = $to ? strtotime( $to ) : time();
		$start = $from ? strtotime( $from ) : strtotime( '-30 days', $end );
		$days  = max( 1, (int) round( ( $end - $start ) / 86400 ) );

		if ( $days <= 31 ) {
			return 'day';
		}
		if ( $days <= 183 ) {
			return 'week';
		}
		return 'month';
	}

	public static function get_overview( array $params ): array {
		$kpi_qb = self::build_query_from_params( $params );
		$kpi_qb->group_by( '' );
		$kpi_results = $kpi_qb->get_aggregated();
		$kpi_agg     = new Aggregator( $kpi_results );
		$data        = $kpi_agg->overview_kpis();

		$trend_interval = self::auto_trend_interval( $params );
		$trend_qb       = self::build_query_from_params( $params );
		$trend_qb->group_by( $trend_interval );
		$trend_results          = $trend_qb->get_aggregated();
		$trend_agg              = new Aggregator( $trend_results );
		$data['trend']          = $trend_agg->by_period( $trend_interval );
		$data['trend_interval'] = $trend_interval;

		$gw_qb = self::build_query_from_params( $params );
		$gw_qb->group_by( 'gateway' );
		$gateway_names        = ( new QueryBuilder() )->get_gateway_list();
		$gw_results           = $gw_qb->get_aggregated();
		$gw_agg               = new Aggregator( $gw_results );
		$data['top_gateways'] = $gw_agg->by_gateway( $gateway_names );

		return $data;
	}

	public static function get_transactions( array $params ): array {
		$qb = self::build_query_from_params( $params );

		$page     = max( 1, (int) ( $params['page'] ?? 1 ) );
		$per_page = min( 250, max( 1, (int) ( $params['per_page'] ?? 25 ) ) );

		$qb->page( $page );
		$qb->per_page( $per_page );

		$count_qb = self::build_query_from_params( $params );
		$total    = $count_qb->get_count();

		$results  = $qb->get_results();
		$gateways = ( new QueryBuilder() )->get_gateway_list();

		if ( ! empty( $results ) ) {
			$ids = wp_list_pluck( $results, 'ID' );
			update_meta_cache( 'post', $ids );
		}

		$transactions = self::map_transaction_rows( $results, $gateways );

		return [
			'transactions' => $transactions,
			'total'        => $total,
			'page'         => $page,
			'per_page'     => $per_page,
			'total_pages'  => (int) ceil( $total / $per_page ),
		];
	}

	public static function get_all_transactions( array $params ): array {
		$qb = self::build_query_from_params( $params );
		$qb->per_page( 1000 );

		$results  = $qb->get_all_results();
		$gateways = ( new QueryBuilder() )->get_gateway_list();

		if ( ! empty( $results ) ) {
			$ids = wp_list_pluck( $results, 'ID' );
			update_meta_cache( 'post', $ids );
		}

		return self::map_transaction_rows( $results, $gateways );
	}

	public static function get_transactions_chunk( array $params, int $offset, int $limit ): array {
		$qb = self::build_query_from_params( $params );
		$qb->per_page( $limit )->page( (int) floor( $offset / $limit ) + 1 );

		$results  = $qb->get_results();
		$gateways = ( new QueryBuilder() )->get_gateway_list();

		if ( ! empty( $results ) ) {
			$ids = wp_list_pluck( $results, 'ID' );
			update_meta_cache( 'post', $ids );
		}

		return self::map_transaction_rows( $results, $gateways );
	}

	private static function map_transaction_rows( array $results, array $gateways ): array {
		$transactions = [];
		foreach ( $results as $row ) {
			$config_id      = (int) ( $row->gateway_id ?? 0 );
			$source_key     = $row->source_key ?? '';
			$source         = ! empty( $source_key ) ? $source_key : get_post_meta( $row->ID, '_pronamic_payment_source', true );
			$source_id      = get_post_meta( $row->ID, '_pronamic_payment_source_id', true );
			$txn_id         = get_post_meta( $row->ID, '_pronamic_payment_transaction_id', true );
			$pm_val         = $row->payment_method_val ?? '';
			$payment_method = self::normalize_payment_method( $pm_val );

			$provider_link       = '';
			$source_desc         = '';
			$source_link         = '';
			$customer_name       = '';
			$customer_email      = '';
			$refunded_amount     = null;
			$charged_back_amount = null;
			$description         = '';

			// @todo Performance: avoid instantiating a full Payment object per row.
			// Issue #9 — N+1 hydration. Deferred to post-v9.5.0 stable release.
			$payment = \get_pronamic_payment( $row->ID );
			if ( null !== $payment ) {
				$provider_link = $payment->get_provider_link() ?? '';
				$source_desc   = (string) $payment->get_source_description();
				$src_link_obj  = $payment->get_source_link();
				$source_link   = null !== $src_link_obj ? (string) $src_link_obj : '';
				$description   = (string) $payment->get_description();

				$customer = $payment->get_customer();
				if ( null !== $customer ) {
					$customer_name  = (string) $customer->get_name();
					$customer_email = (string) $customer->get_email();
				}

				$refunded = $payment->get_refunded_amount();
				if ( null !== $refunded && ! $refunded->is_zero() ) {
					$refunded_amount = (float) $refunded->get_value();
				}

				$charged_back = $payment->get_charged_back_amount();
				if ( null !== $charged_back && ! $charged_back->is_zero() ) {
					$charged_back_amount = (float) $charged_back->get_value();
				}
			}

			$customer_display = $customer_name;
			if ( empty( $customer_display ) ) {
				$customer_display = $customer_email;
			}

			$gateway_edit_url = $config_id > 0 ? admin_url( 'post.php?post=' . $config_id . '&action=edit' ) : '';

			$status_map          = self::get_status_map();
			$status_map['trash'] = __( 'Trash', 'knit-pay-lang' );

			$derived_status = $row->post_status;
			if ( 'payment_completed' === $row->post_status && $refunded_amount > 0 ) {
				$total = (float) ( $row->amount ?? 0 );
				if ( $refunded_amount >= $total && $total > 0 ) {
					$derived_status = 'payment_refunded';
				} else {
					$derived_status = 'payment_partially_refunded';
				}
			}

			$transactions[] = [
				'id'                  => $row->ID,
				'date'                => $row->post_date,
				'status'              => $derived_status,
				'post_status'         => $row->post_status,
				'status_label'        => $status_map[ $derived_status ] ?? $status_map[ $row->post_status ] ?? $row->post_status,
				'amount'              => (float) ( $row->amount ?? 0 ),
				'currency'            => $row->currency ?? '',
				'gateway_id'          => $config_id,
				'gateway_name'        => self::gateway_display_name( $config_id, $gateways ),
				'gateway_edit_url'    => $gateway_edit_url,
				'source'              => $source,
				'source_id'           => $source_id,
				'source_description'  => $source_desc,
				'source_link'         => $source_link,
				'transaction_id'      => $txn_id,
				'provider_link'       => $provider_link,
				'payment_method'      => $payment_method,
				'payment_method_name' => self::payment_method_name( $payment_method ),
				'mode'                => $row->payment_mode ?? '',
				'customer'            => $customer_display,
				'description'         => $description,
				'refunded_amount'     => $refunded_amount,
				'charged_back_amount' => $charged_back_amount,
				'edit_url'            => admin_url( 'post.php?post=' . $row->ID . '&action=edit' ),
			];
		}
		return $transactions;
	}

	public static function get_gateway_performance( array $params ): array {
		$gateway_names = ( new QueryBuilder() )->get_gateway_list();

		$qb = self::build_query_from_params( $params );
		$qb->group_by( 'gateway' );
		$results    = $qb->get_aggregated();
		$aggregator = new Aggregator( $results );
		$data       = $aggregator->by_gateway( $gateway_names );

		$cross_qb = self::build_query_from_params( $params );
		$cross_qb->group_by( 'gateway_payment_method' );
		$cross_results = $cross_qb->get_aggregated();

		$gw_method_groups = [];
		foreach ( $cross_results as $row ) {
			$gw_id = (int) ( $row->config_id ?? $row->gateway_id ?? 0 );
			if ( ! isset( $gw_method_groups[ $gw_id ] ) ) {
				$gw_method_groups[ $gw_id ] = [];
			}
			$gw_method_groups[ $gw_id ][] = $row;
		}

		foreach ( $gw_method_groups as $gw_id => $rows ) {
			$key = (string) $gw_id;
			if ( isset( $data[ $key ] ) ) {
				$data[ $key ]['success_rate_by_method'] = Aggregator::cross_dimension_success_rate( $rows, 'payment_method' );
			}
		}

		return $data;
	}

	public static function get_payment_methods( array $params ): array {
		$qb = self::build_query_from_params( $params );
		$qb->group_by( 'payment_method' );

		$results = $qb->get_aggregated();

		$groups = [];
		foreach ( $results as $row ) {
			$method = self::normalize_payment_method( $row->payment_method ?? '' );
			if ( ! isset( $groups[ $method ] ) ) {
				$groups[ $method ] = [];
			}
			$groups[ $method ][] = $row;
		}

		$data = [];
		foreach ( $groups as $method => $rows ) {
			$agg             = new Aggregator( $rows );
			$data[ $method ] = [
				'count'                   => $agg->total_count(),
				'amounts'                 => $agg->sum_amount_by_currency(),
				'success_amounts'         => $agg->success_amount_by_currency(),
				'avg'                     => $agg->weighted_avg_per_currency(),
				'success_rate'            => $agg->success_rate(),
				'success_rate_by_gateway' => [],
				'statuses'                => $agg->by_status(),
			];
		}

		$gateway_names = ( new QueryBuilder() )->get_gateway_list();

		$cross_qb = self::build_query_from_params( $params );
		$cross_qb->group_by( 'payment_method_gateway' );
		$cross_results = $cross_qb->get_aggregated();

		$method_gw_groups = [];
		foreach ( $cross_results as $row ) {
			$method = self::normalize_payment_method( $row->payment_method ?? '' );
			if ( ! isset( $method_gw_groups[ $method ] ) ) {
				$method_gw_groups[ $method ] = [];
			}
			$method_gw_groups[ $method ][] = $row;
		}

		foreach ( $method_gw_groups as $method => $rows ) {
			if ( isset( $data[ $method ] ) ) {
				$data[ $method ]['success_rate_by_gateway'] = Aggregator::cross_dimension_success_rate( $rows, 'config_id' );
			}
		}

		return $data;
	}

	public static function get_sources( array $params ): array {
		$qb = self::build_query_from_params( $params );
		$qb->group_by( 'source' );
		$results    = $qb->get_aggregated();
		$aggregator = new Aggregator( $results );
		$data       = $aggregator->by_source();

		foreach ( $data as $src => &$src_data ) {
			$src_data['name'] = self::source_display_name( $src );
		}
		unset( $src_data );

		uasort( $data, fn( $a, $b ) => ( $b['count'] ?? 0 ) <=> ( $a['count'] ?? 0 ) );

		return $data;
	}

	public static function get_refunds( array $params ): array {
		$all_qb = self::build_query_from_params( $params );
		$all_qb->group_by( '' );
		$all_results = $all_qb->get_aggregated();
		$all_agg     = new Aggregator( $all_results );
		$total_count = $all_agg->total_count();

		$refund_qb = self::build_query_from_params( $params );
		$refund_qb->refund_mode( true );
		$refund_qb->group_by( '' );
		$refund_results   = $refund_qb->get_aggregated();
		$refunded_count   = count( $refund_results );
		$refunded_amounts = [];
		foreach ( $refund_results as $row ) {
			$cur = $row->refund_currency ?? ( $row->currency ?? __( 'Unknown', 'knit-pay-lang' ) );
			$amt = (float) ( $row->refund_amount ?? 0 );
			if ( ! isset( $refunded_amounts[ $cur ] ) ) {
				$refunded_amounts[ $cur ] = 0.0;
			}
			$refunded_amounts[ $cur ] += $amt;
		}

		$overview = [
			'total_count'      => $total_count,
			'refunded_count'   => $refunded_count,
			'refunded_amounts' => $refunded_amounts,
			'total_amounts'    => $all_agg->sum_amount_by_currency(),
			'refund_rate'      => $total_count > 0 ? round( ( $refunded_count / $total_count ) * 100, 1 ) : 0.0,
		];

		$by_gw_qb = self::build_query_from_params( $params );
		$by_gw_qb->refund_mode( true );
		$by_gw_qb->group_by( 'gateway' );
		$by_gw_results = $by_gw_qb->get_aggregated();
		$gateway_names = ( new QueryBuilder() )->get_gateway_list();
		$by_gateway    = [];
		foreach ( $by_gw_results as $row ) {
			$gw_id  = (int) ( $row->config_id ?? 0 );
			$gw_key = (string) $gw_id;
			if ( ! isset( $by_gateway[ $gw_key ] ) ) {
				$by_gateway[ $gw_key ] = [
					'name'            => self::gateway_display_name( $gw_id, $gateway_names ),
					'count'           => 0,
					'amounts'         => [],
					'success_amounts' => [],
					'avg'             => [],
					'success_rate'    => 0,
					'statuses'        => [],
				];
			}
			++$by_gateway[ $gw_key ]['count'];
			$cur = $row->refund_currency ?? ( $row->currency ?? __( 'Unknown', 'knit-pay-lang' ) );
			$amt = (float) ( $row->refund_amount ?? 0 );
			if ( ! isset( $by_gateway[ $gw_key ]['amounts'][ $cur ] ) ) {
				$by_gateway[ $gw_key ]['amounts'][ $cur ] = 0.0;
			}
			$by_gateway[ $gw_key ]['amounts'][ $cur ] += $amt;
		}

		$by_src_qb = self::build_query_from_params( $params );
		$by_src_qb->refund_mode( true );
		$by_src_qb->group_by( 'source' );
		$by_src_results = $by_src_qb->get_aggregated();
		$by_source      = [];
		foreach ( $by_src_results as $row ) {
			$src = $row->source ?? '';
			if ( ! isset( $by_source[ $src ] ) ) {
				$by_source[ $src ] = [
					'count'           => 0,
					'amounts'         => [],
					'success_amounts' => [],
					'avg'             => [],
					'success_rate'    => 0,
					'statuses'        => [],
					'name'            => self::source_display_name( $src ),
				];
			}
			++$by_source[ $src ]['count'];
			$cur = $row->refund_currency ?? ( $row->currency ?? __( 'Unknown', 'knit-pay-lang' ) );
			$amt = (float) ( $row->refund_amount ?? 0 );
			if ( ! isset( $by_source[ $src ]['amounts'][ $cur ] ) ) {
				$by_source[ $src ]['amounts'][ $cur ] = 0.0;
			}
			$by_source[ $src ]['amounts'][ $cur ] += $amt;
		}

		$trend_interval = self::auto_trend_interval( $params );
		$trend_qb       = self::build_query_from_params( $params );
		$trend_qb->refund_mode( true );
		$trend_qb->group_by( $trend_interval );
		$trend_results = $trend_qb->get_aggregated();
		$trend         = [];
		foreach ( $trend_results as $row ) {
			$period = $row->period ?? '';
			if ( ! isset( $trend[ $period ] ) ) {
				$trend[ $period ] = [
					'period'   => $period,
					'count'    => 0,
					'amounts'  => [],
					'statuses' => [],
				];
			}
			++$trend[ $period ]['count'];
			$cur = $row->refund_currency ?? ( $row->currency ?? __( 'Unknown', 'knit-pay-lang' ) );
			$amt = (float) ( $row->refund_amount ?? 0 );
			if ( ! isset( $trend[ $period ]['amounts'][ $cur ] ) ) {
				$trend[ $period ]['amounts'][ $cur ] = 0.0;
			}
			$trend[ $period ]['amounts'][ $cur ] += $amt;
		}

		$by_method_qb = self::build_query_from_params( $params );
		$by_method_qb->refund_mode( true );
		$by_method_qb->group_by( 'payment_method' );
		$by_method_results = $by_method_qb->get_aggregated();
		$by_method         = [];
		foreach ( $by_method_results as $row ) {
			$method = self::normalize_payment_method( $row->payment_method ?? '' );
			if ( ! isset( $by_method[ $method ] ) ) {
				$by_method[ $method ] = [
					'count'           => 0,
					'amounts'         => [],
					'success_amounts' => [],
					'avg'             => [],
					'success_rate'    => 0,
					'statuses'        => [],
				];
			}
			++$by_method[ $method ]['count'];
			$cur = $row->refund_currency ?? ( $row->currency ?? __( 'Unknown', 'knit-pay-lang' ) );
			$amt = (float) ( $row->refund_amount ?? 0 );
			if ( ! isset( $by_method[ $method ]['amounts'][ $cur ] ) ) {
				$by_method[ $method ]['amounts'][ $cur ] = 0.0;
			}
			$by_method[ $method ]['amounts'][ $cur ] += $amt;
		}

		$data = [
			'overview'         => $overview,
			'refunded_amounts' => $refunded_amounts,
			'by_gateway'       => $by_gateway,
			'by_source'        => $by_source,
			'by_method'        => $by_method,
			'trend'            => $trend,
			'trend_interval'   => $trend_interval,
		];

		return $data;
	}

	public static function source_display_name( string $source_key ): string {
		static $map = null;
		if ( null === $map ) {
			$map = self::build_source_display_names();
			$map = apply_filters( 'knit_pay_source_display_names', $map );
		}
		$normalized = self::normalize_source( $source_key );
		return $map[ $normalized ] ?? ucwords( str_replace( [ '-', '_' ], ' ', $normalized ) );
	}

	public static function build_source_display_names(): array {
		static $built = null;
		if ( null !== $built ) {
			return $built;
		}

		$built = [];

		$plugin = \Pronamic\WordPress\Pay\Plugin::instance();
		if ( $plugin && property_exists( $plugin, 'plugin_integrations' ) && is_array( $plugin->plugin_integrations ) ) {
			foreach ( $plugin->plugin_integrations as $integration ) {
				$slug = self::get_integration_slug( $integration );
				$name = $integration->get_name();

				if ( $slug && $name ) {
					$built[ $slug ] = $name;
				}
			}
		}

		$built['test'] = __( 'Test', 'knit-pay-lang' );

		return $built;
	}

	private static function get_integration_slug( $integration ): string {
		$reflection = new \ReflectionClass( $integration );

		if ( $reflection->hasConstant( 'SLUG' ) ) {
			return strtolower( $reflection->getConstant( 'SLUG' ) );
		}

		$slug = self::extract_slug_from_source_filters( $integration );
		if ( $slug ) {
			return $slug;
		}

		return '';
	}

	private static function extract_slug_from_source_filters( $integration ): string {
		global $wp_filter;

		$class_name = get_class( $integration );
		$pattern    = '/^pronamic_payment_source_text_([a-z0-9_-]+)$/';

		foreach ( $wp_filter as $hook_name => $hook ) {
			if ( ! preg_match( $pattern, $hook_name, $matches ) ) {
				continue;
			}

			if ( ! method_exists( $hook, 'callbacks' ) && ! property_exists( $hook, 'callbacks' ) ) {
				continue;
			}

			$callbacks = $hook->callbacks;
			if ( ! is_array( $callbacks ) ) {
				continue;
			}

			foreach ( $callbacks as $priority => $hooks ) {
				if ( ! is_array( $hooks ) ) {
					continue;
				}
				foreach ( $hooks as $hook_detail ) {
					if ( ! isset( $hook_detail['function'] ) || ! is_array( $hook_detail['function'] ) ) {
						continue;
					}
					if ( $hook_detail['function'][0] instanceof $class_name ) {
						return $matches[1];
					}
				}
			}
		}

		return '';
	}

	public static function normalize_source( string $source ): string {
		$source = strtolower( trim( $source ) );
		return $source ? $source : 'unknown';
	}

	public static function get_filter_options(): array {
		$qb = new QueryBuilder();

		$trash_count = wp_count_posts( 'pronamic_payment' );
		$trash_num   = isset( $trash_count->trash ) ? (int) $trash_count->trash : 0;

		return [
			'gateways'        => $qb->get_gateway_list(),
			'currencies'      => $qb->get_currency_list(),
			'sources'         => $qb->get_source_list(),
			'payment_methods' => $qb->get_payment_method_list(),
			'statuses'        => self::get_status_map(),
			'trash_count'     => $trash_num,
		];
	}
}
