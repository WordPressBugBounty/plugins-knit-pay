<?php
/**
 * Knit Pay Reports – Refunds Tab (Alpine.js)
 *
 * @package KnitPay\Reports
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="knit-pay-reports-refunds" id="knit-pay-reports-refunds">
	<h2 class="knit-pay-tab-heading"><?php esc_html_e( 'Refunds', 'knit-pay-lang' ); ?></h2>

	<div class="knit-pay-kpi-cards">
		<div class="knit-pay-kpi-card" id="kpi-refund-total">
			<span class="kpi-label" title="<?php esc_attr_e( 'Total number of payments in selected period.', 'knit-pay-lang' ); ?>">
				<span x-text="i18n.total_payments || 'Total Payments'"></span> <span class="kpi-info-icon">&#9432;</span>
			</span>
			<span class="kpi-value" x-text="refundData.overview?.total_count || 0"></span>
		</div>
		<div class="knit-pay-kpi-card" id="kpi-refund-count">
			<span class="kpi-label" title="<?php esc_attr_e( 'Number of payments that were refunded.', 'knit-pay-lang' ); ?>">
				<span x-text="i18n.refunded_payments || 'Refunded Payments'"></span> <span class="kpi-info-icon">&#9432;</span>
			</span>
			<span class="kpi-value" x-text="refundData.overview?.refunded_count || 0"></span>
		</div>
		<div class="knit-pay-kpi-card" id="kpi-refund-rate">
			<span class="kpi-label" title="<?php esc_attr_e( 'Percentage of total payments that were refunded.', 'knit-pay-lang' ); ?>">
				<span x-text="i18n.refund_rate || 'Refund Rate'"></span> <span class="kpi-info-icon">&#9432;</span>
			</span>
			<span class="kpi-value" x-text="(refundData.overview?.refund_rate || 0) + '%'"></span>
		</div>
		<div class="knit-pay-kpi-card" id="kpi-refund-volume">
			<span class="kpi-label" title="<?php esc_attr_e( 'Total amount refunded across all currencies.', 'knit-pay-lang' ); ?>">
				<span x-text="i18n.refunded_amount || 'Refunded Amount'"></span> <span class="kpi-info-icon">&#9432;</span>
			</span>
			<div class="kpi-value" x-html="refundKpiRefundedHtml()"></div>
		</div>
	</div>

	<div class="knit-pay-charts-grid">
		<div class="knit-pay-chart-container">
			<h3 x-text="i18n.refund_trend || 'Refund Trend'"></h3>
			<canvas id="knit-pay-chart-refund-trend" width="400" height="250"></canvas>
		</div>
		<div class="knit-pay-chart-container">
			<h3 x-text="i18n.refunds_by_gateway || 'Refunds by Gateway'"></h3>
			<canvas id="knit-pay-chart-refund-gateway" width="500" height="250"></canvas>
		</div>
		<div class="knit-pay-chart-container">
			<h3 x-text="i18n.refunds_by_source || 'Refunds by Source'"></h3>
			<canvas id="knit-pay-chart-refund-source" width="500" height="250"></canvas>
		</div>
	</div>

	<h3 x-text="i18n.refunds_by_gateway || 'Refunds by Gateway'"></h3>
	<table class="wp-list-table widefat fixed striped knit-pay-report-table knit-pay-refund-gateway-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Gateway', 'knit-pay-lang' ); ?></th>
				<th><?php esc_html_e( 'Refunded Count', 'knit-pay-lang' ); ?></th>
				<th><?php esc_html_e( 'Refunded Amount', 'knit-pay-lang' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<template x-if="Object.keys(refundData.by_gateway || {}).length === 0">
				<tr>
					<td colspan="3" class="knit-pay-loading" x-text="i18n.no_data || 'No data available for the selected filters.'"></td>
				</tr>
			</template>
			<template x-for="gw in refundGatewayRows()" :key="gw.key">
				<tr>
					<td x-text="gw.name"></td>
					<td x-text="gw.count"></td>
					<td x-html="buildCurrencyList(gw.amounts || {}, Object.keys(gw.amounts || {}), (a, c) => formatMoney(a, c))"></td>
				</tr>
			</template>
		</tbody>
	</table>

	<h3 x-text="i18n.refunds_by_source || 'Refunds by Source'"></h3>
	<table class="wp-list-table widefat fixed striped knit-pay-report-table knit-pay-refund-source-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Source', 'knit-pay-lang' ); ?></th>
				<th><?php esc_html_e( 'Refunded Count', 'knit-pay-lang' ); ?></th>
				<th><?php esc_html_e( 'Refunded Amount', 'knit-pay-lang' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<template x-if="Object.keys(refundData.by_source || {}).length === 0">
				<tr>
					<td colspan="3" class="knit-pay-loading" x-text="i18n.no_data || 'No data available for the selected filters.'"></td>
				</tr>
			</template>
			<template x-for="src in refundSourceRows()" :key="src.key">
				<tr>
					<td x-text="src.name"></td>
					<td x-text="src.count"></td>
					<td x-html="buildCurrencyList(src.amounts || {}, Object.keys(src.amounts || {}), (a, c) => formatMoney(a, c))"></td>
				</tr>
			</template>
		</tbody>
	</table>

	<h3 x-text="i18n.refunds_by_method || 'Refunds by Payment Method'"></h3>
	<table class="wp-list-table widefat fixed striped knit-pay-report-table knit-pay-refund-method-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Payment Method', 'knit-pay-lang' ); ?></th>
				<th><?php esc_html_e( 'Refunded Count', 'knit-pay-lang' ); ?></th>
				<th><?php esc_html_e( 'Refunded Amount', 'knit-pay-lang' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<template x-if="Object.keys(refundData.by_method || {}).length === 0">
				<tr>
					<td colspan="3" class="knit-pay-loading" x-text="i18n.no_data || 'No data available for the selected filters.'"></td>
				</tr>
			</template>
			<template x-for="(info, method) in refundData.by_method" :key="method">
				<tr>
					<td x-text="methodName(method)"></td>
					<td x-text="info.count"></td>
					<td x-html="buildCurrencyList(info.amounts || {}, Object.keys(info.amounts || {}), (a, c) => formatMoney(a, c))"></td>
				</tr>
			</template>
		</tbody>
	</table>
</div>
