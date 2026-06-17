<?php
/**
 * Knit Pay Reports – Sources Tab (Alpine.js)
 *
 * @package KnitPay\Reports
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="knit-pay-reports-sources" id="knit-pay-reports-sources">
	<h2 class="knit-pay-tab-heading"><?php esc_html_e( 'Sources', 'knit-pay-lang' ); ?></h2>

	<table class="wp-list-table widefat fixed striped knit-pay-report-table" id="knit-pay-source-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Source', 'knit-pay-lang' ); ?></th>
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
		<tbody id="knit-pay-source-body">
			<template x-if="Object.keys(sourcesData).length === 0">
				<tr>
					<td colspan="6" class="knit-pay-loading" x-text="i18n.no_data || 'No data available for the selected filters.'"></td>
				</tr>
			</template>
			<template x-for="src in sourceRows()" :key="src.key">
				<tr>
					<td x-text="src.name"></td>
					<td x-text="src.count"></td>
					<td x-text="src.success_rate + '%'"></td>
					<td x-html="buildCurrencyList(src.avg, Object.keys(src.avg || {}), (a, c) => formatMoney(a, c))"></td>
					<td x-html="buildCurrencyList(src.success_amounts, Object.keys(src.success_amounts || {}), (a, c) => formatMoney(a, c))"></td>
					<td x-html="buildCurrencyList(src.amounts, Object.keys(src.amounts || {}), (a, c) => formatMoney(a, c))"></td>
				</tr>
			</template>
		</tbody>
	</table>

	<div class="knit-pay-charts-grid">
		<div class="knit-pay-chart-container">
			<h3 x-text="i18n.source_distribution || 'Source Distribution'"></h3>
			<canvas id="knit-pay-chart-source-distribution" width="400" height="250"></canvas>
		</div>
		<div class="knit-pay-chart-container">
			<h3><?php esc_html_e( 'Success Rate by Source', 'knit-pay-lang' ); ?></h3>
			<canvas id="knit-pay-chart-source-success" width="500" height="250"></canvas>
		</div>
		<div class="knit-pay-chart-container">
			<h3 x-text="i18n.source_revenue || 'Revenue per Source'"></h3>
			<canvas id="knit-pay-chart-source-revenue" width="500" height="250"></canvas>
		</div>
	</div>
</div>
