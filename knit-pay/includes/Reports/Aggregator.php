<?php
/**
 * Computes metrics from SQL-aggregated query results. Each row has:
 *   derived_status, cnt, total_amount, refunded_amount, currency
 * Plus optional: period, config_id, payment_method, source
 *
 * @package KnitPay\Reports
 */

namespace KnitPay\Reports;

class Aggregator {

	private array $raw_results;

	private static function success_statuses(): array {
		return [ 'payment_completed', 'payment_authorized', 'payment_partially_refunded' ];
	}

	private static function net_revenue_statuses(): array {
		return [ 'payment_completed', 'payment_authorized', 'payment_partially_refunded' ];
	}

	public function __construct( array $raw_results ) {
		$this->raw_results = $raw_results;
	}

	public function total_count(): int {
		$total = 0;
		foreach ( $this->raw_results as $row ) {
			$total += (int) ( $row->cnt ?? 1 );
		}
		return $total;
	}

	public function count_by_status(): array {
		$counts = [];
		foreach ( $this->raw_results as $row ) {
			$status = $row->derived_status ?? 'unknown';
			$cnt    = (int) ( $row->cnt ?? 1 );
			if ( ! isset( $counts[ $status ] ) ) {
				$counts[ $status ] = 0;
			}
			$counts[ $status ] += $cnt;
		}
		return $counts;
	}

	public function sum_amount_by_currency(): array {
		$sums = [];
		foreach ( $this->raw_results as $row ) {
			$cur = $row->currency ?? '';
			$cur = empty( $cur ) ? __( 'Unknown', 'knit-pay-lang' ) : $cur;
			$amt = (float) ( $row->total_amount ?? 0 );
			if ( ! isset( $sums[ $cur ] ) ) {
				$sums[ $cur ] = 0.0;
			}
			$sums[ $cur ] += $amt;
		}
		return $sums;
	}

	public function success_amount_by_currency(): array {
		$sums = [];
		foreach ( $this->raw_results as $row ) {
			$derived = $row->derived_status ?? '';
			if ( ! in_array( $derived, self::net_revenue_statuses(), true ) ) {
				continue;
			}
			$cur = $row->currency ?? '';
			$cur = empty( $cur ) ? __( 'Unknown', 'knit-pay-lang' ) : $cur;
			$amt = (float) ( $row->total_amount ?? 0 );
			$ref = (float) ( $row->refunded_amount ?? 0 );
			$net = $amt - $ref;
			if ( ! isset( $sums[ $cur ] ) ) {
				$sums[ $cur ] = 0.0;
			}
			$sums[ $cur ] += $net;
		}
		return $sums;
	}

	public function success_rate(): float {
		$total   = 0;
		$success = 0;
		foreach ( $this->raw_results as $row ) {
			$cnt     = (int) ( $row->cnt ?? 1 );
			$total  += $cnt;
			$derived = $row->derived_status ?? '';
			if ( in_array( $derived, self::success_statuses(), true ) ) {
				$success += $cnt;
			}
		}
		return $total > 0 ? round( ( $success / $total ) * 100, 1 ) : 0.0;
	}

	public function by_status(): array {
		$groups = [];
		foreach ( $this->raw_results as $row ) {
			$status = $row->derived_status ?? 'unknown';
			if ( ! isset( $groups[ $status ] ) ) {
				$groups[ $status ] = [];
			}
			$groups[ $status ][] = $row;
		}

		$result = [];
		foreach ( $groups as $status => $rows ) {
			$agg               = new self( $rows );
			$result[ $status ] = [
				'label'   => ReportsApiHelper::get_status_map()[ $status ] ?? $status,
				'count'   => $agg->total_count(),
				'amounts' => $agg->sum_amount_by_currency(),
				'avg'     => $agg->weighted_avg_per_currency(),
				'rate'    => $agg->success_rate(),
			];
		}
		return $result;
	}

	public function by_gateway( array $gateway_names ): array {
		$groups = [];
		foreach ( $this->raw_results as $row ) {
			$raw_id = $row->config_id ?? $row->gateway_id ?? null;
			$key    = $raw_id ?? 'unknown';
			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = [];
			}
			$groups[ $key ][] = $row;
		}

		$result = [];
		foreach ( $groups as $config_id => $rows ) {
			$agg = new self( $rows );
			/* translators: %d: Gateway configuration ID */
			$name                 = ReportsApiHelper::gateway_display_name( (int) $config_id, $gateway_names );
			$result[ $config_id ] = [
				'name'            => $name,
				'count'           => $agg->total_count(),
				'amounts'         => $agg->sum_amount_by_currency(),
				'success_amounts' => $agg->success_amount_by_currency(),
				'avg'             => $agg->weighted_avg_per_currency(),
				'success_rate'    => $agg->success_rate(),
				'statuses'        => $agg->by_status(),
			];
		}
		return $result;
	}

	public function by_period( string $interval = 'day' ): array {
		$groups = [];
		foreach ( $this->raw_results as $row ) {
			$period = $row->period ?? 'unknown';
			if ( ! isset( $groups[ $period ] ) ) {
				$groups[ $period ] = [];
			}
			$groups[ $period ][] = $row;
		}

		$result = [];
		foreach ( $groups as $period => $rows ) {
			$agg               = new self( $rows );
			$result[ $period ] = [
				'period_label'     => self::format_period_label( $period, $interval ),
				'count'            => $agg->total_count(),
				'amounts'          => $agg->sum_amount_by_currency(),
				'success_amounts'  => $agg->success_amount_by_currency(),
				'success_rate'     => $agg->success_rate(),
				'status_breakdown' => $agg->count_by_status(),
			];
		}
		ksort( $result );
		return $result;
	}

	public static function format_period_label( string $period, string $interval ): string {
		if ( 'week' === $interval && ctype_digit( $period ) && 6 === strlen( $period ) ) {
			$year         = (int) substr( $period, 0, 4 );
			$week         = (int) substr( $period, 4, 2 );
			$jan4         = new \DateTime( "$year-01-04" );
			$day_of_week  = (int) $jan4->format( 'N' );
			$week1_monday = clone $jan4;
			$week1_monday->modify( '-' . ( $day_of_week - 1 ) . ' days' );
			$week_monday = clone $week1_monday;
			$week_monday->modify( '+' . ( ( $week - 1 ) * 7 ) . ' days' );
			$week_sunday = clone $week_monday;
			$week_sunday->modify( '+6 days' );
			$mon = date_i18n( 'j M', $week_monday->getTimestamp() );
			$sun = date_i18n( 'j M', $week_sunday->getTimestamp() );
			return $mon . ' - ' . $sun;
		}

		if ( 'month' === $interval && preg_match( '/^(\d{4})-(\d{2})$/', $period, $m ) ) {
			$ts = mktime( 0, 0, 0, (int) $m[2], 1, (int) $m[1] );
			return date_i18n( 'F Y', $ts );
		}

		if ( 'day' === $interval && preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $period, $m ) ) {
			$ts = mktime( 0, 0, 0, (int) $m[2], (int) $m[3], (int) $m[1] );
			return date_i18n( 'j M Y', $ts );
		}

		return $period;
	}

	public function overview_kpis(): array {
		$total_count           = $this->total_count();
		$success_count         = 0;
		$success_amount_by_cur = [];
		$success_count_by_cur  = [];

		foreach ( $this->raw_results as $row ) {
			$derived = $row->derived_status ?? '';

			if ( in_array( $derived, self::success_statuses(), true ) ) {
				$success_count += (int) ( $row->cnt ?? 1 );
			}

			if ( ! in_array( $derived, self::net_revenue_statuses(), true ) ) {
				continue;
			}
			$cur = $row->currency ?? '';
			$cur = empty( $cur ) ? __( 'Unknown', 'knit-pay-lang' ) : $cur;
			$cnt = (int) ( $row->cnt ?? 1 );
			$amt = (float) ( $row->total_amount ?? 0 );
			$ref = (float) ( $row->refunded_amount ?? 0 );
			$net = $amt - $ref;

			if ( ! isset( $success_amount_by_cur[ $cur ] ) ) {
				$success_amount_by_cur[ $cur ] = 0.0;
				$success_count_by_cur[ $cur ]  = 0;
			}
			$success_amount_by_cur[ $cur ] += $net;
			$success_count_by_cur[ $cur ]  += $cnt;
		}

		$avg_by_cur = [];
		foreach ( $success_amount_by_cur as $cur => $amt ) {
			$avg_by_cur[ $cur ] = $success_count_by_cur[ $cur ] > 0
				? round( $amt / $success_count_by_cur[ $cur ], 2 )
				: 0.0;
		}

		return [
			'total_count'            => $total_count,
			'success_count'          => $success_count,
			'success_rate'           => $total_count > 0 ? round( ( $success_count / $total_count ) * 100, 1 ) : 0.0,
			'revenue_by_currency'    => $success_amount_by_cur,
			'avg_amount_by_currency' => $avg_by_cur,
			'status_breakdown'       => $this->count_by_status(),
		];
	}

	public function weighted_avg_per_currency(): array {
		$sums_by_cur = [];
		$cnts_by_cur = [];

		foreach ( $this->raw_results as $row ) {
			$cur = $row->currency ?? '';
			$cur = empty( $cur ) ? __( 'Unknown', 'knit-pay-lang' ) : $cur;
			$cnt = (int) ( $row->cnt ?? 1 );
			$amt = (float) ( $row->total_amount ?? 0 );

			if ( ! isset( $sums_by_cur[ $cur ] ) ) {
				$sums_by_cur[ $cur ] = 0.0;
				$cnts_by_cur[ $cur ] = 0;
			}
			$sums_by_cur[ $cur ] += $amt;
			$cnts_by_cur[ $cur ] += $cnt;
		}

		$result = [];
		foreach ( $sums_by_cur as $cur => $total ) {
			$result[ $cur ] = $cnts_by_cur[ $cur ] > 0
				? round( $total / $cnts_by_cur[ $cur ], 2 )
				: 0.0;
		}
		return $result;
	}

	public function by_source(): array {
		$groups = [];
		foreach ( $this->raw_results as $row ) {
			$source = $row->source ?? 'unknown';
			if ( ! isset( $groups[ $source ] ) ) {
				$groups[ $source ] = [];
			}
			$groups[ $source ][] = $row;
		}

		$result = [];
		foreach ( $groups as $source => $rows ) {
			$agg               = new self( $rows );
			$result[ $source ] = [
				'count'           => $agg->total_count(),
				'amounts'         => $agg->sum_amount_by_currency(),
				'success_amounts' => $agg->success_amount_by_currency(),
				'avg'             => $agg->weighted_avg_per_currency(),
				'success_rate'    => $agg->success_rate(),
				'statuses'        => $agg->by_status(),
			];
		}
		return $result;
	}

	public function refund_stats(): array {
		$total_count     = 0;
		$refunded_count  = 0;
		$amounts_by_cur  = [];
		$refunded_by_cur = [];

		foreach ( $this->raw_results as $row ) {
			$cnt          = (int) ( $row->cnt ?? 1 );
			$total_count += $cnt;
			$cur          = $row->currency ?? '';
			$cur          = empty( $cur ) ? __( 'Unknown', 'knit-pay-lang' ) : $cur;
			$amt          = (float) ( $row->total_amount ?? 0 );
			$ref          = (float) ( $row->refunded_amount ?? 0 );
			if ( ! isset( $amounts_by_cur[ $cur ] ) ) {
				$amounts_by_cur[ $cur ] = 0.0;
			}
			$amounts_by_cur[ $cur ] += $amt;

			$derived = $row->derived_status ?? '';
			if ( in_array( $derived, [ 'payment_refunded', 'payment_partially_refunded' ], true ) ) {
				$refunded_count += $cnt;
				if ( ! isset( $refunded_by_cur[ $cur ] ) ) {
					$refunded_by_cur[ $cur ] = 0.0;
				}
				$refunded_by_cur[ $cur ] += $ref;
			}
		}

		return [
			'total_count'      => $total_count,
			'refunded_count'   => $refunded_count,
			'refunded_amounts' => $refunded_by_cur,
			'total_amounts'    => $amounts_by_cur,
			'refund_rate'      => $total_count > 0 ? round( ( $refunded_count / $total_count ) * 100, 1 ) : 0.0,
		];
	}

	public static function cross_dimension_success_rate( array $raw_results, string $label_key ): array {
		$groups = [];
		foreach ( $raw_results as $row ) {
			$label = $row->{$label_key} ?? '';
			$label = ReportsApiHelper::normalize_payment_method( (string) $label );
			if ( ! isset( $groups[ $label ] ) ) {
				$groups[ $label ] = [];
			}
			$groups[ $label ][] = $row;
		}

		$result = [];
		foreach ( $groups as $label => $rows ) {
			$agg              = new self( $rows );
			$result[ $label ] = round( $agg->success_rate(), 1 );
		}
		return $result;
	}
}
