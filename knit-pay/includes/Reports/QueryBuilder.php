<?php
/**
 * Knit Pay Reports – Query Builder
 *
 * Fluent API for building optimized direct $wpdb queries against the
 * pronamic_payment CPT. Extracts amount, currency, mode, payment_method,
 * and gateway from JSON in post_content. Performs SQL-level aggregation
 * (SUM, COUNT, AVG) in grouped queries for performance.
 *
 * @package KnitPay\Reports
 */

namespace KnitPay\Reports;

use DateTimeInterface;
use Pronamic\WordPress\Pay\Core\PaymentMethods as Core_PaymentMethods;

class QueryBuilder {

	private string $post_type             = 'pronamic_payment';
	private ?DateTimeInterface $date_from = null;
	private ?DateTimeInterface $date_to   = null;
	private array $gateways               = [];
	private array $statuses               = [];
	private array $sources                = [];
	private ?string $mode                 = null;
	private array $currencies             = [];
	private array $payment_methods        = [];
	private ?string $search               = null;
	private array $post_ids               = [];
	private string $group_by              = '';
	private int $page                     = 1;
	private int $per_page                 = 25;
	private string $orderby               = 'date';
	private string $order                 = 'DESC';
	private bool $refund_mode             = false;

	public function date_range( DateTimeInterface $from, DateTimeInterface $to ): self {
		$this->date_from = $from;
		$this->date_to   = $to;
		return $this;
	}

	public function gateway( int|array $config_id ): self {
		$this->gateways = is_array( $config_id ) ? $config_id : [ $config_id ];
		return $this;
	}

	public function status( string|array $status ): self {
		$this->statuses = is_array( $status ) ? $status : [ $status ];
		return $this;
	}

	public function source( string|array $source ): self {
		$this->sources = is_array( $source ) ? $source : [ $source ];
		return $this;
	}

	public function mode( string $mode ): self {
		$this->mode = $mode;
		return $this;
	}

	public function currency( string|array $currency ): self {
		$this->currencies = is_array( $currency ) ? $currency : [ $currency ];
		return $this;
	}

	public function payment_method( string|array $method ): self {
		$this->payment_methods = is_array( $method ) ? $method : [ $method ];
		return $this;
	}

	public function search( string $query ): self {
		$this->search = $query;
		return $this;
	}

	public function refund_mode( bool $enabled = true ): self {
		$this->refund_mode = $enabled;
		return $this;
	}

	public function post_ids( array $ids ): self {
		$this->post_ids = array_map( 'absint', $ids );
		return $this;
	}

	public function group_by( string $field ): self {
		$this->group_by = $field;
		return $this;
	}

	public function page( int $page ): self {
		$this->page = max( 1, $page );
		return $this;
	}

	public function per_page( int $per_page ): self {
		$this->per_page = max( 1, min( 1000, $per_page ) );
		return $this;
	}

	public function orderby( string $field ): self {
		$this->orderby = $field;
		return $this;
	}

	public function order( string $direction ): self {
		$this->order = strtoupper( $direction ) === 'ASC' ? 'ASC' : 'DESC';
		return $this;
	}

	private function is_trash_request(): bool {
		return ! empty( $this->statuses ) && in_array( 'trash', $this->statuses, true );
	}

	private static function refunded_value_expr(): string {
		return "CAST(JSON_EXTRACT(p.post_content, '$.refunded_amount.value') AS DECIMAL(20,6))";
	}

	private static function total_value_expr(): string {
		return "CAST(JSON_EXTRACT(p.post_content, '$.total_amount.value') AS DECIMAL(20,6))";
	}

	private static function refunded_exists_expr(): string {
		return "JSON_EXTRACT(p.post_content, '$.refunded_amount') IS NOT NULL";
	}

	private function build_virtual_status_clauses( array $virtual_statuses ): array {
		$clauses = [];
		$rv      = self::refunded_value_expr();
		$tv      = self::total_value_expr();
		$re      = self::refunded_exists_expr();
		foreach ( $virtual_statuses as $status ) {
			if ( 'payment_partially_refunded' === $status ) {
				$clauses[] = "({$re} AND {$rv} > 0 AND {$rv} < {$tv})";
			}
		}
		return $clauses;
	}

	private function build_where(): array {
		global $wpdb;

		$where = [ 'p.post_type = %s' ];
		$args  = [ $this->post_type ];

		$has_trash = $this->is_trash_request();

		if ( $has_trash ) {
			$non_trash = array_filter( $this->statuses, fn( $s ) => 'trash' !== $s );
			if ( ! empty( $non_trash ) ) {
				$virtual = [];
				$real    = [];
				foreach ( $non_trash as $s ) {
					if ( ReportsApiHelper::is_virtual_status( $s ) ) {
						$virtual[] = $s;
					} else {
						$real[] = $s;
					}
				}
				$status_clauses = [ "p.post_status = 'trash'" ];
				if ( ! empty( $real ) ) {
					$placeholders     = implode( ',', array_fill( 0, count( $real ), '%s' ) );
					$status_clauses[] = "p.post_status IN ($placeholders)";
					$args             = array_merge( $args, $real );
				}
				$status_clauses = array_merge( $status_clauses, $this->build_virtual_status_clauses( $virtual ) );
				$where[]        = '(' . implode( ' OR ', $status_clauses ) . ')';
			} else {
				$where[] = "p.post_status = 'trash'";
			}
		} elseif ( ! empty( $this->statuses ) ) {
			$virtual = [];
			$real    = [];
			foreach ( $this->statuses as $s ) {
				if ( ReportsApiHelper::is_virtual_status( $s ) ) {
					$virtual[] = $s;
				} else {
					$real[] = $s;
				}
			}

			$all_clauses = [];

			if ( ! empty( $real ) ) {
				$has_completed = in_array( 'payment_completed', $real, true );
				$has_refunded  = in_array( 'payment_refunded', $real, true );
				$other_real    = array_filter( $real, fn( $s ) => 'payment_completed' !== $s && 'payment_refunded' !== $s );

				$rv = self::refunded_value_expr();
				$tv = self::total_value_expr();
				$re = self::refunded_exists_expr();

				if ( $has_completed && $has_refunded ) {
					$all_clauses[] = "((p.post_status = 'payment_completed' AND (NOT {$re} OR {$rv} = 0)) OR p.post_status = 'payment_refunded' OR (p.post_status = 'payment_completed' AND {$re} AND {$rv} >= {$tv}))";
				} elseif ( $has_completed ) {
					$all_clauses[] = "(p.post_status = 'payment_completed' AND (NOT {$re} OR {$rv} = 0))";
				} elseif ( $has_refunded ) {
					$all_clauses[] = "(p.post_status = 'payment_refunded' OR (p.post_status = 'payment_completed' AND {$re} AND {$rv} >= {$tv}))";
				}

				if ( ! empty( $other_real ) ) {
					$placeholders  = implode( ',', array_fill( 0, count( $other_real ), '%s' ) );
					$all_clauses[] = "p.post_status IN ($placeholders)";
					$args          = array_merge( $args, $other_real );
				}
			}

			$all_clauses = array_merge( $all_clauses, $this->build_virtual_status_clauses( $virtual ) );

			if ( count( $all_clauses ) === 1 ) {
				$where[] = $all_clauses[0];
			} elseif ( count( $all_clauses ) > 1 ) {
				$where[] = '(' . implode( ' OR ', $all_clauses ) . ')';
			}
		} else {
			$where[] = "p.post_status NOT IN ('trash', 'auto-draft')";
		}

		if ( $this->date_from && $this->date_to ) {
			$where[] = 'p.post_date BETWEEN %s AND %s';
			$args[]  = $this->date_from->format( 'Y-m-d 00:00:00' );
			$args[]  = $this->date_to->format( 'Y-m-d 23:59:59' );
		} elseif ( $this->date_from ) {
			$where[] = 'p.post_date >= %s';
			$args[]  = $this->date_from->format( 'Y-m-d 00:00:00' );
		} elseif ( $this->date_to ) {
			$where[] = 'p.post_date <= %s';
			$args[]  = $this->date_to->format( 'Y-m-d 23:59:59' );
		}

		if ( ! empty( $this->search ) ) {
			$search_lower = '%' . $wpdb->esc_like( mb_strtolower( $this->search ) ) . '%';
			$search_like  = '%' . $wpdb->esc_like( $this->search ) . '%';
			$where[]      = '(' .
				'p.ID LIKE %s ' .
				'OR p.post_title LIKE %s ' .
				"OR LOWER(JSON_UNQUOTE(JSON_EXTRACT(p.post_content, '$.transaction_id'))) LIKE %s " .
				"OR LOWER(JSON_UNQUOTE(JSON_EXTRACT(p.post_content, '$.source.value'))) LIKE %s " .
				"OR LOWER(JSON_UNQUOTE(JSON_EXTRACT(p.post_content, '$.source.key'))) LIKE %s " .
				"OR LOWER(JSON_UNQUOTE(JSON_EXTRACT(p.post_content, '$.order_id'))) LIKE %s " .
				"OR LOWER(JSON_UNQUOTE(JSON_EXTRACT(p.post_content, '$.customer.name.first_name'))) LIKE %s " .
				"OR LOWER(JSON_UNQUOTE(JSON_EXTRACT(p.post_content, '$.customer.name.last_name'))) LIKE %s " .
				"OR LOWER(CONCAT(JSON_UNQUOTE(JSON_EXTRACT(p.post_content, '$.customer.name.first_name')), ' ', JSON_UNQUOTE(JSON_EXTRACT(p.post_content, '$.customer.name.last_name')))) LIKE %s " .
				"OR LOWER(JSON_UNQUOTE(JSON_EXTRACT(p.post_content, '$.customer.email'))) LIKE %s " .
				"OR LOWER(JSON_UNQUOTE(JSON_EXTRACT(p.post_content, '$.billing_address.phone'))) LIKE %s " .
			')';
			$args[]       = $search_like;
			$args[]       = $search_like;
			$args[]       = $search_lower;
			$args[]       = $search_lower;
			$args[]       = $search_lower;
			$args[]       = $search_lower;
			$args[]       = $search_lower;
			$args[]       = $search_lower;
			$args[]       = $search_lower;
			$args[]       = $search_lower;
			$args[]       = $search_lower;
		}

		if ( $this->refund_mode ) {
			$re      = self::refunded_exists_expr();
			$where[] = "({$re} OR p.post_status = 'payment_refunded')";
		}

		if ( ! empty( $this->post_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $this->post_ids ), '%d' ) );
			$where[]      = "p.ID IN ($placeholders)";
			$args         = array_merge( $args, $this->post_ids );
		}

		return [ $where, $args ];
	}

	private function build_json_filters(): array {
		$conditions = [];
		$args       = [];

		if ( ! empty( $this->gateways ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $this->gateways ), '%d' ) );
			$conditions[] = "CAST(JSON_EXTRACT(p.post_content, '$.gateway.post_id') AS UNSIGNED) IN ($placeholders)";
			$args         = array_merge( $args, $this->gateways );
		}
		if ( ! empty( $this->sources ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $this->sources ), '%s' ) );
			$conditions[] = "JSON_UNQUOTE(JSON_EXTRACT(p.post_content, '$.source.key')) IN ($placeholders)";
			$args         = array_merge( $args, $this->sources );
		}
		if ( null !== $this->mode ) {
			$conditions[] = "JSON_UNQUOTE(JSON_EXTRACT(p.post_content, '$.mode')) = %s";
			$args[]       = $this->mode;
		}
		if ( ! empty( $this->currencies ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $this->currencies ), '%s' ) );
			$conditions[] = "JSON_UNQUOTE(JSON_EXTRACT(p.post_content, '$.total_amount.currency')) IN ($placeholders)";
			$args         = array_merge( $args, $this->currencies );
		}
		if ( ! empty( $this->payment_methods ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $this->payment_methods ), '%s' ) );
			$conditions[] = "JSON_UNQUOTE(JSON_EXTRACT(p.post_content, '$.payment_method')) IN ($placeholders)";
			$args         = array_merge( $args, $this->payment_methods );
		}

		return [ $conditions, $args ];
	}

	private function build_full_where(): array {
		[ $where_clauses, $where_args ]      = $this->build_where();
		[ $filter_conditions, $filter_args ] = $this->build_json_filters();

		$all_conditions = array_merge( $where_clauses, $filter_conditions );
		$all_args       = array_merge( $where_args, $filter_args );

		$this->assert_safe_conditions( $all_conditions );

		return [ $all_conditions, $all_args ];
	}

	/**
	 * Runtime validation: every WHERE clause must contain ONLY hardcoded SQL
	 * plus placeholders (%%s/%%d). No unsanitized user input is permitted.
	 * Throws RuntimeException on failure so regressions are caught in testing.
	 */
	private function assert_safe_conditions( array $conditions ): void {
		foreach ( $conditions as $condition ) {
			if ( ! is_string( $condition ) ) {
				throw new \RuntimeException( 'QueryBuilder: Non-string WHERE clause detected.' );
			}

			$stripped = preg_replace( "/'[^']*'/", 'S', $condition );
			$stripped = preg_replace( '/"[^"]*"/', 'S', $stripped );

			if ( preg_match( '/[^a-zA-Z0-9_\s.,=()*%:;!?<>!\-+\/]/u', $stripped ) ) {
				throw new \RuntimeException( 'QueryBuilder: Unsafe character found in WHERE clause.' );
			}
		}
	}

	private function build_transaction_select( bool $cast_amount = false ): string {
		$amount_select = $cast_amount
			? "CAST(JSON_EXTRACT(p.post_content, '$.total_amount.value') AS DECIMAL(20,6)) AS amount,"
			: "JSON_UNQUOTE(JSON_EXTRACT(p.post_content, '$.total_amount.value')) AS amount,";
		return "p.ID, p.post_status, p.post_date, {$amount_select} JSON_UNQUOTE(JSON_EXTRACT(p.post_content, '$.total_amount.currency')) AS currency, JSON_UNQUOTE(JSON_EXTRACT(p.post_content, '$.mode')) AS payment_mode, JSON_UNQUOTE(JSON_EXTRACT(p.post_content, '$.payment_method')) AS payment_method_val, JSON_EXTRACT(p.post_content, '$.gateway.post_id') AS gateway_id, JSON_UNQUOTE(JSON_EXTRACT(p.post_content, '$.source.key')) AS source_key";
	}

	public function get_results(): array {
		global $wpdb;

		[ $conditions, $args ] = $this->build_full_where();

		$select = $this->build_transaction_select( false );
		$sql    = "SELECT {$select} FROM {$wpdb->posts} p";
		$sql   .= ' WHERE ' . implode( ' AND ', $conditions );

		$order_col = 'p.post_date';
		if ( 'amount' === $this->orderby ) {
			$order_col = "CAST(JSON_EXTRACT(p.post_content, '$.total_amount.value') AS DECIMAL(20,6))";
		}
		if ( 'ID' === $this->orderby ) {
			$order_col = 'p.ID';
		}

		$sql .= " ORDER BY {$order_col} {$this->order}";
		$sql .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $this->per_page, ( $this->page - 1 ) * $this->per_page );

		return $wpdb->get_results(
			$wpdb->prepare( $sql, ...$args ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			OBJECT
		);
	}

	public function get_all_results(): array {
		global $wpdb;

		[ $conditions, $args ] = $this->build_full_where();

		$select = $this->build_transaction_select( true );
		$sql    = "SELECT {$select} FROM {$wpdb->posts} p";
		$sql   .= ' WHERE ' . implode( ' AND ', $conditions );
		$sql   .= ' ORDER BY p.post_date DESC';
		$sql   .= $wpdb->prepare( ' LIMIT %d', min( $this->per_page, 50000 ) );

		return $wpdb->get_results(
			$wpdb->prepare( $sql, ...$args ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			OBJECT
		);
	}

	public function get_count(): int {
		global $wpdb;

		[ $conditions, $args ] = $this->build_full_where();

		$sql = "SELECT COUNT(*) FROM {$wpdb->posts} p WHERE " . implode( ' AND ', $conditions );

		return (int) $wpdb->get_var(
			$wpdb->prepare( $sql, ...$args ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
	}

	public function get_aggregated(): array {
		global $wpdb;

		[ $conditions, $args ] = $this->build_full_where();

		$rv = self::refunded_value_expr();
		$tv = self::total_value_expr();
		$re = self::refunded_exists_expr();

		$select  = "CASE WHEN p.post_status = 'payment_completed' AND {$re} AND {$rv} > 0 THEN CASE WHEN {$rv} >= {$tv} AND {$tv} > 0 THEN 'payment_refunded' ELSE 'payment_partially_refunded' END ELSE p.post_status END AS derived_status,";
		$select .= ' COUNT(*) AS cnt,';
		$select .= " SUM({$tv}) AS total_amount,";
		$select .= " SUM(CASE WHEN {$re} THEN {$rv} ELSE 0 END) AS refunded_amount,";
		$select .= " JSON_UNQUOTE(JSON_EXTRACT(p.post_content, '$.total_amount.currency')) AS currency";

		if ( $this->refund_mode ) {
			$select .= ", {$rv} AS refund_amount";
			$select .= ", JSON_UNQUOTE(JSON_EXTRACT(p.post_content, '$.refunded_amount.currency')) AS refund_currency";
		}

		$group_by = "derived_status, JSON_UNQUOTE(JSON_EXTRACT(p.post_content, '$.total_amount.currency'))";

		switch ( $this->group_by ) {
			case 'day':
				$select  .= ', DATE(p.post_date) AS period';
				$group_by = 'DATE(p.post_date), derived_status, JSON_UNQUOTE(JSON_EXTRACT(p.post_content, \'$.total_amount.currency\'))';
				break;
			case 'week':
				$select  .= ', YEARWEEK(p.post_date, 1) AS period';
				$group_by = 'YEARWEEK(p.post_date, 1), derived_status, JSON_UNQUOTE(JSON_EXTRACT(p.post_content, \'$.total_amount.currency\'))';
				break;
			case 'month':
				$select  .= ", DATE_FORMAT(p.post_date, '%%Y-%%m') AS period";
				$group_by = "DATE_FORMAT(p.post_date, '%%Y-%%m'), derived_status, JSON_UNQUOTE(JSON_EXTRACT(p.post_content, '$.total_amount.currency'))";
				break;
			case 'gateway':
				$select  .= ", CAST(JSON_EXTRACT(p.post_content, '$.gateway.post_id') AS UNSIGNED) AS config_id";
				$group_by = 'CAST(JSON_EXTRACT(p.post_content, \'$.gateway.post_id\') AS UNSIGNED), derived_status, JSON_UNQUOTE(JSON_EXTRACT(p.post_content, \'$.total_amount.currency\'))';
				break;
			case 'status':
				break;
			case 'currency':
				break;
			case 'payment_method':
				$select  .= ", JSON_UNQUOTE(JSON_EXTRACT(p.post_content, '$.payment_method')) AS payment_method";
				$group_by = "JSON_UNQUOTE(JSON_EXTRACT(p.post_content, '$.payment_method')), derived_status, JSON_UNQUOTE(JSON_EXTRACT(p.post_content, '$.total_amount.currency'))";
				break;
			case 'gateway_payment_method':
				$select  .= ", CAST(JSON_EXTRACT(p.post_content, '$.gateway.post_id') AS UNSIGNED) AS config_id";
				$select  .= ", JSON_UNQUOTE(JSON_EXTRACT(p.post_content, '$.payment_method')) AS payment_method";
				$group_by = "CAST(JSON_EXTRACT(p.post_content, '$.gateway.post_id') AS UNSIGNED), JSON_UNQUOTE(JSON_EXTRACT(p.post_content, '$.payment_method')), derived_status, JSON_UNQUOTE(JSON_EXTRACT(p.post_content, '$.total_amount.currency'))";
				break;
			case 'payment_method_gateway':
				$select  .= ", JSON_UNQUOTE(JSON_EXTRACT(p.post_content, '$.payment_method')) AS payment_method";
				$select  .= ", CAST(JSON_EXTRACT(p.post_content, '$.gateway.post_id') AS UNSIGNED) AS config_id";
				$group_by = "JSON_UNQUOTE(JSON_EXTRACT(p.post_content, '$.payment_method')), CAST(JSON_EXTRACT(p.post_content, '$.gateway.post_id') AS UNSIGNED), derived_status, JSON_UNQUOTE(JSON_EXTRACT(p.post_content, '$.total_amount.currency'))";
				break;
			case 'source':
				$select  .= ", JSON_UNQUOTE(JSON_EXTRACT(p.post_content, '$.source.key')) AS source";
				$group_by = "JSON_UNQUOTE(JSON_EXTRACT(p.post_content, '$.source.key')), derived_status, JSON_UNQUOTE(JSON_EXTRACT(p.post_content, '$.total_amount.currency'))";
				break;
		}

		$sql  = "SELECT {$select} FROM {$wpdb->posts} p";
		$sql .= ' WHERE ' . implode( ' AND ', $conditions );
		$sql .= " GROUP BY {$group_by}";

		return $wpdb->get_results(
			$wpdb->prepare( $sql, ...$args ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			OBJECT
		);
	}

	public function get_gateway_list(): array {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID AS config_id, post_title AS gateway_name
				 FROM {$wpdb->posts}
				 WHERE post_type = 'pronamic_gateway'
				 AND post_status = 'publish'
				 ORDER BY gateway_name
				 LIMIT %d",
				500
			),
			OBJECT
		);

		$gateways = [];
		foreach ( $results as $row ) {
			$gateways[ (int) $row->config_id ] = $row->gateway_name;
		}

		return $gateways;
	}

	public function get_currency_list(): array {
		$cache_key = 'knit_pay_reports_currencies';
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT JSON_UNQUOTE(JSON_EXTRACT(post_content, '$.total_amount.currency')) AS currency
				 FROM {$wpdb->posts}
				 WHERE post_type = 'pronamic_payment'
				 AND post_status NOT IN ('trash', 'auto-draft')
				 AND JSON_EXTRACT(post_content, '$.total_amount.currency') IS NOT NULL
				 ORDER BY currency
				 LIMIT %d",
				500
			),
			OBJECT
		);

		$values = array_filter( array_column( $results, 'currency' ) );
		set_transient( $cache_key, $values, HOUR_IN_SECONDS );
		return $values;
	}

	public function get_source_list(): array {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT JSON_UNQUOTE(JSON_EXTRACT(post_content, '$.source.key')) AS source
				 FROM {$wpdb->posts}
				 WHERE post_type = 'pronamic_payment'
				 AND post_status NOT IN ('trash', 'auto-draft')
				 AND JSON_EXTRACT(post_content, '$.source.key') IS NOT NULL
				 AND JSON_UNQUOTE(JSON_EXTRACT(post_content, '$.source.key')) != ''
				 ORDER BY source
				 LIMIT %d",
				500
			),
			OBJECT
		);

		$raw = array_filter( array_column( $results, 'source' ) );

		$values = [];
		foreach ( $raw as $slug ) {
			$values[ $slug ] = ReportsApiHelper::source_display_name( $slug );
		}

		return $values;
	}

	public function get_payment_method_list(): array {
		$methods = [ 'Knit Pay' => __( 'Knit Pay', 'knit-pay-lang' ) ];

		$active = Core_PaymentMethods::get_active_payment_methods();
		foreach ( $active as $method_id ) {
			if ( 'knit_pay' === $method_id || empty( $method_id ) ) {
				continue;
			}
			$name                  = Core_PaymentMethods::get_name( $method_id, $method_id );
			$methods[ $method_id ] = $name ?? $method_id;
		}

		ksort( $methods );
		return $methods;
	}
}
