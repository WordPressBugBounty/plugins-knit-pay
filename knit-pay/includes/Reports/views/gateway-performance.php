<?php
/**
 * Knit Pay Reports – Gateway Performance Tab (Alpine.js)
 *
 * @package KnitPay\Reports
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="knit-pay-reports-gateway-performance" id="knit-pay-reports-gateway-performance">
	<h2 class="knit-pay-tab-heading"><?php esc_html_e( 'Gateway Performance', 'knit-pay-lang' ); ?></h2>

	<table class="wp-list-table widefat fixed striped knit-pay-report-table" id="knit-pay-gateway-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Gateway', 'knit-pay-lang' ); ?></th>
				<th><?php esc_html_e( 'Total', 'knit-pay-lang' ); ?></th>
				<th><?php esc_html_e( 'Success Rate', 'knit-pay-lang' ); ?></th>
				<th><?php esc_html_e( 'Avg. Amount', 'knit-pay-lang' ); ?></th>
				<th>
					<span class="knit-pay-th-tooltip" title="<?php esc_attr_e( 'Total amount from successful payments only.', 'knit-pay-lang' ); ?>">
						<?php esc_html_e( 'Revenue', 'knit-pay-lang' ); ?> <span class="kpi-info-icon">&#9432;</span>
					</span>
				</th>
				<th>
					<span class="knit-pay-th-tooltip" title="<?php esc_attr_e( 'Total amount from all payment attempts (successful + failed + pending).', 'knit-pay-lang' ); ?>">
						<?php esc_html_e( 'Gross Volume', 'knit-pay-lang' ); ?> <span class="kpi-info-icon">&#9432;</span>
					</span>
				</th>
			</tr>
		</thead>
		<tbody id="knit-pay-gateway-body">
			<template x-if="Object.keys(gatewayData).length === 0">
				<tr>
					<td colspan="6" class="knit-pay-loading" x-text="i18n.no_data || 'No data available for the selected filters.'"></td>
				</tr>
			</template>
			<template x-for="gw in gatewayRows()" :key="gw.key">
				<tr>
					<td x-text="gw.name"></td>
					<td x-text="gw.count"></td>
					<td x-text="gw.success_rate + '%'"></td>
					<td x-html="buildCurrencyList(gw.avg, Object.keys(gw.avg || {}), (a, c) => formatMoney(a, c))"></td>
					<td x-html="buildCurrencyList(gw.success_amounts, Object.keys(gw.success_amounts || {}), (a, c) => formatMoney(a, c))"></td>
					<td x-html="buildCurrencyList(gw.amounts, Object.keys(gw.amounts || {}), (a, c) => formatMoney(a, c))"></td>
				</tr>
			</template>
		</tbody>
	</table>

	<div class="knit-pay-charts-grid">
		<div class="knit-pay-chart-container">
			<h3><?php esc_html_e( 'Transaction Distribution', 'knit-pay-lang' ); ?></h3>
			<canvas id="knit-pay-chart-gateway-distribution" width="400" height="250"></canvas>
		</div>
		<div class="knit-pay-chart-container">
			<h3><?php esc_html_e( 'Success Rate by Method (per Gateway)', 'knit-pay-lang' ); ?></h3>
			<canvas id="knit-pay-chart-gateway-success" width="500" height="250"></canvas>
		</div>
		<div class="knit-pay-chart-container">
			<h3><?php esc_html_e( 'Revenue per Gateway', 'knit-pay-lang' ); ?></h3>
			<canvas id="knit-pay-chart-gateway-revenue" width="500" height="250"></canvas>
		</div>
	</div>
</div>
