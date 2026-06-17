<?php
/**
 * Knit Pay Reports – CSV Exporter
 *
 * Generates CSV output for each report type.
 *
 * @package KnitPay\Reports\Exporter
 */

namespace KnitPay\Reports\Exporter;

use KnitPay\Reports\ReportsApiHelper;

class CsvExporter {

	private string $eol = "\r\n";

	public function stream_transactions_header(): string {
		return implode(
			',',
			[
				__( 'ID', 'knit-pay-lang' ),
				__( 'Status', 'knit-pay-lang' ),
				__( 'Transaction ID', 'knit-pay-lang' ),
				__( 'Amount', 'knit-pay-lang' ),
				__( 'Currency', 'knit-pay-lang' ),
				__( 'Date', 'knit-pay-lang' ),
				__( 'Gateway', 'knit-pay-lang' ),
				__( 'Customer', 'knit-pay-lang' ),
				__( 'Payment Method', 'knit-pay-lang' ),
				__( 'Description', 'knit-pay-lang' ),
				__( 'Source', 'knit-pay-lang' ),
				__( 'Source ID', 'knit-pay-lang' ),
				__( 'Source Description', 'knit-pay-lang' ),
				__( 'Refunded Amount', 'knit-pay-lang' ),
				__( 'Charged Back Amount', 'knit-pay-lang' ),
			] 
		);
	}

	public function format_transaction_row( array $txn ): string {
		return implode(
			',',
			array_map(
				[ $this, 'escape_csv_field' ],
				[
					$txn['id'],
					$txn['status_label'] ?? $txn['status'],
					$txn['transaction_id'],
					$txn['amount'],
					$txn['currency'],
					$txn['date'],
					$txn['gateway_name'],
					$txn['customer'] ?? '',
					$txn['payment_method_name'] ?? $txn['payment_method'] ?? '',
					$txn['description'] ?? '',
					$txn['source'],
					$txn['source_id'],
					$txn['source_description'] ?? '',
					$txn['refunded_amount'] ?? '',
					$txn['charged_back_amount'] ?? '',
				] 
			) 
		);
	}

	public function export_overview( array $data ): string {
		$lines   = [];
		$lines[] = 'Metric,Value';
		$lines[] = 'Total Transactions,' . $this->escape_csv_field( $data['total_count'] ?? 0 );
		$lines[] = 'Successful Transactions (incl. partial refunds),' . $this->escape_csv_field( $data['success_count'] ?? 0 );
		$lines[] = 'Success Rate (incl. partial refunds),' . $this->escape_csv_field( ( $data['success_rate'] ?? 0 ) . '%' );

		if ( ! empty( $data['avg_amount_by_currency'] ) ) {
			$lines[] = '';
			$lines[] = 'Currency,Average Transaction Value';
			foreach ( $data['avg_amount_by_currency'] as $currency => $avg ) {
				$lines[] = $this->escape_csv_field( $currency ) . ',' . $this->escape_csv_field( $avg );
			}
		}

		if ( ! empty( $data['revenue_by_currency'] ) ) {
			$lines[] = '';
			$lines[] = 'Currency,Revenue';
			foreach ( $data['revenue_by_currency'] as $currency => $amount ) {
				$lines[] = $this->escape_csv_field( $currency ) . ',' . $this->escape_csv_field( $amount );
			}
		}

		$status_labels = ReportsApiHelper::get_status_map();
		if ( ! empty( $data['status_breakdown'] ) ) {
			$lines[] = '';
			$lines[] = __( 'Status', 'knit-pay-lang' ) . ',' . __( 'Count', 'knit-pay-lang' );
			foreach ( $data['status_breakdown'] as $status => $count ) {
				$label   = $status_labels[ $status ] ?? $status;
				$lines[] = $this->escape_csv_field( $label ) . ',' . $this->escape_csv_field( $count );
			}
		}

		return implode( $this->eol, $lines );
	}

	public function export_gateway_performance( array $data ): string {
		$lines   = [];
		$lines[] = implode( ',', [ __( 'Gateway', 'knit-pay-lang' ), __( 'Total', 'knit-pay-lang' ), __( 'Success Rate', 'knit-pay-lang' ), __( 'Average Amount', 'knit-pay-lang' ), __( 'Revenue', 'knit-pay-lang' ), __( 'Volume', 'knit-pay-lang' ) ] );

		foreach ( $data as $gateway_id => $gw ) {
			$volume_parts = [];
			foreach ( $gw['amounts'] ?? [] as $cur => $amt ) {
				$volume_parts[] = $cur . ' ' . $amt;
			}
			$volume = ! empty( $volume_parts ) ? implode( '; ', $volume_parts ) : '0';

			$avg_parts = [];
			foreach ( $gw['avg'] ?? [] as $cur => $avg ) {
				$avg_parts[] = $cur . ' ' . $avg;
			}
			$avg_str = ! empty( $avg_parts ) ? implode( '; ', $avg_parts ) : '0';

			$rev_parts = [];
			foreach ( $gw['success_amounts'] ?? [] as $cur => $amt ) {
				$rev_parts[] = $cur . ' ' . $amt;
			}
			$rev_str = ! empty( $rev_parts ) ? implode( '; ', $rev_parts ) : '0';

			$lines[] = implode(
				',',
				[
					$this->escape_csv_field( $gw['name'] ),
					$gw['count'],
					$gw['success_rate'] . '%',
					$this->escape_csv_field( $avg_str ),
					$this->escape_csv_field( $rev_str ),
					$this->escape_csv_field( $volume ),
				] 
			);
		}

		return implode( $this->eol, $lines );
	}

	public function export_payment_methods( array $data ): string {
		$method_names = ReportsApiHelper::payment_method_names( array_keys( $data ) );
		$lines        = [];
		$lines[]      = implode( ',', [ __( 'Payment Method', 'knit-pay-lang' ), __( 'Total', 'knit-pay-lang' ), __( 'Success Rate', 'knit-pay-lang' ), __( 'Average Amount', 'knit-pay-lang' ), __( 'Revenue', 'knit-pay-lang' ), __( 'Volume', 'knit-pay-lang' ) ] );

		foreach ( $data as $method => $info ) {
			$volume_parts = [];
			foreach ( $info['amounts'] ?? [] as $cur => $amt ) {
				$volume_parts[] = $cur . ' ' . $amt;
			}
			$volume = ! empty( $volume_parts ) ? implode( '; ', $volume_parts ) : '0';

			$avg_parts = [];
			foreach ( $info['avg'] ?? [] as $cur => $avg ) {
				$avg_parts[] = $cur . ' ' . $avg;
			}
			$avg_str = ! empty( $avg_parts ) ? implode( '; ', $avg_parts ) : '0';

			$rev_parts = [];
			foreach ( $info['success_amounts'] ?? [] as $cur => $amt ) {
				$rev_parts[] = $cur . ' ' . $amt;
			}
			$rev_str = ! empty( $rev_parts ) ? implode( '; ', $rev_parts ) : '0';

			$lines[] = implode(
				',',
				[
					$this->escape_csv_field( $method_names[ $method ] ?? $method ),
					$info['count'],
					$info['success_rate'] . '%',
					$this->escape_csv_field( $avg_str ),
					$this->escape_csv_field( $rev_str ),
					$this->escape_csv_field( $volume ),
				] 
			);
		}

		return implode( $this->eol, $lines );
	}

	public function export_sources( array $data ): string {
		$lines   = [];
		$lines[] = implode( ',', [ __( 'Source', 'knit-pay-lang' ), __( 'Total', 'knit-pay-lang' ), __( 'Success Rate', 'knit-pay-lang' ), __( 'Average Amount', 'knit-pay-lang' ), __( 'Revenue', 'knit-pay-lang' ), __( 'Volume', 'knit-pay-lang' ) ] );

		foreach ( $data as $source => $info ) {
			$volume_parts = [];
			foreach ( $info['amounts'] ?? [] as $cur => $amt ) {
				$volume_parts[] = $cur . ' ' . $amt;
			}
			$volume = ! empty( $volume_parts ) ? implode( '; ', $volume_parts ) : '0';

			$avg_parts = [];
			foreach ( $info['avg'] ?? [] as $cur => $avg ) {
				$avg_parts[] = $cur . ' ' . $avg;
			}
			$avg_str = ! empty( $avg_parts ) ? implode( '; ', $avg_parts ) : '0';

			$rev_parts = [];
			foreach ( $info['success_amounts'] ?? [] as $cur => $amt ) {
				$rev_parts[] = $cur . ' ' . $amt;
			}
			$rev_str = ! empty( $rev_parts ) ? implode( '; ', $rev_parts ) : '0';

			$lines[] = implode(
				',',
				[
					$this->escape_csv_field( $info['name'] ?? $source ),
					$info['count'],
					$info['success_rate'] . '%',
					$this->escape_csv_field( $avg_str ),
					$this->escape_csv_field( $rev_str ),
					$this->escape_csv_field( $volume ),
				] 
			);
		}

		return implode( $this->eol, $lines );
	}

	public function export_refunds( array $data ): string {
		$lines    = [];
		$overview = $data['overview'] ?? [];

		$lines[] = __( 'Metric', 'knit-pay-lang' ) . ',' . __( 'Value', 'knit-pay-lang' );
		$lines[] = __( 'Total Payments', 'knit-pay-lang' ) . ',' . $this->escape_csv_field( (string) ( $overview['total_count'] ?? 0 ) );
		$lines[] = __( 'Refunded Payments', 'knit-pay-lang' ) . ',' . $this->escape_csv_field( (string) ( $overview['refunded_count'] ?? 0 ) );
		$lines[] = __( 'Refund Rate', 'knit-pay-lang' ) . ',' . $this->escape_csv_field( ( $overview['refund_rate'] ?? 0 ) . '%' );

		if ( ! empty( $overview['refunded_amounts'] ) ) {
			$lines[] = '';
			$lines[] = __( 'Currency', 'knit-pay-lang' ) . ',' . __( 'Refunded Amount', 'knit-pay-lang' );
			foreach ( $overview['refunded_amounts'] as $currency => $amount ) {
				$lines[] = $this->escape_csv_field( $currency ) . ',' . $this->escape_csv_field( (string) $amount );
			}
		}

		if ( ! empty( $data['by_gateway'] ) ) {
			$lines[] = '';
			$lines[] = implode( ',', [ __( 'Gateway', 'knit-pay-lang' ), __( 'Refunded Count', 'knit-pay-lang' ), __( 'Refunded Amount', 'knit-pay-lang' ) ] );
			foreach ( $data['by_gateway'] as $gw_id => $gw ) {
				$refunded_by_cur = [];
				foreach ( $gw['amounts'] ?? [] as $cur => $amt ) {
					$refunded_by_cur[] = $cur . ' ' . $amt;
				}
				$lines[] = implode(
					',',
					[
						$this->escape_csv_field( $gw['name'] ?? '' ),
						$gw['count'] ?? 0,
						$this->escape_csv_field( ! empty( $refunded_by_cur ) ? implode( '; ', $refunded_by_cur ) : '0' ),
					]
				);
			}
		}

		if ( ! empty( $data['by_source'] ) ) {
			$lines[] = '';
			$lines[] = implode( ',', [ __( 'Source', 'knit-pay-lang' ), __( 'Refunded Count', 'knit-pay-lang' ), __( 'Refunded Amount', 'knit-pay-lang' ) ] );
			foreach ( $data['by_source'] as $src => $src_data ) {
				$refunded_by_cur = [];
				foreach ( $src_data['amounts'] ?? [] as $cur => $amt ) {
					$refunded_by_cur[] = $cur . ' ' . $amt;
				}
				$lines[] = implode(
					',',
					[
						$this->escape_csv_field( $src_data['name'] ?? $src ),
						$src_data['count'] ?? 0,
						$this->escape_csv_field( ! empty( $refunded_by_cur ) ? implode( '; ', $refunded_by_cur ) : '0' ),
					]
				);
			}
		}

		return implode( $this->eol, $lines );
	}

	private function escape_csv_field( string $field ): string {
		if ( preg_match( '/(?:^|\n)[=+\-@\t\r]/', $field ) ) {
			$field = "'" . $field;
		}
		if ( str_contains( $field, ',' ) || str_contains( $field, '"' ) || str_contains( $field, "\n" ) ) {
			return '"' . str_replace( '"', '""', $field ) . '"';
		}
		return $field;
	}
}
