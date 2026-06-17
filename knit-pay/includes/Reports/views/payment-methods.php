<?php
/**
 * Knit Pay Reports – Payment Methods Tab (Alpine.js)
 *
 * @package KnitPay\Reports
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="knit-pay-reports-payment-methods" id="knit-pay-reports-payment-methods">
	<h2 class="knit-pay-tab-heading"><?php esc_html_e( 'Payment Methods', 'knit-pay-lang' ); ?></h2>

	<table class="wp-list-table widefat fixed striped knit-pay-report-table" id="knit-pay-methods-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Payment Method', 'knit-pay-lang' ); ?></th>
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
		<tbody id="knit-pay-methods-body">
			<template x-if="Object.keys(methodsData).length === 0">
				<tr>
					<td colspan="6" class="knit-pay-loading" x-text="i18n.no_data || 'No data available for the selected filters.'"></td>
				</tr>
			</template>
			<template x-for="m in methodRows()" :key="m.key">
				<tr>
					<td x-text="methodName(m.key)"></td>
					<td x-text="m.count"></td>
					<td x-text="m.success_rate + '%'"></td>
					<td x-html="buildCurrencyList(m.avg, Object.keys(m.avg || {}), (a, c) => formatMoney(a, c))"></td>
					<td x-html="buildCurrencyList(m.success_amounts, Object.keys(m.success_amounts || {}), (a, c) => formatMoney(a, c))"></td>
					<td x-html="buildCurrencyList(m.amounts, Object.keys(m.amounts || {}), (a, c) => formatMoney(a, c))"></td>
				</tr>
			</template>
		</tbody>
	</table>

	<div class="knit-pay-charts-grid">
		<div class="knit-pay-chart-container">
			<h3><?php esc_html_e( 'Transaction Distribution', 'knit-pay-lang' ); ?></h3>
			<canvas id="knit-pay-chart-methods-distribution" width="400" height="250"></canvas>
		</div>
		<div class="knit-pay-chart-container">
			<h3><?php esc_html_e( 'Success Rate by Gateway (per Method)', 'knit-pay-lang' ); ?></h3>
			<canvas id="knit-pay-chart-methods-success" width="500" height="250"></canvas>
		</div>
		<div class="knit-pay-chart-container">
			<h3><?php esc_html_e( 'Revenue by Method', 'knit-pay-lang' ); ?></h3>
			<canvas id="knit-pay-chart-methods-revenue" width="500" height="250"></canvas>
		</div>
	</div>
</div>
