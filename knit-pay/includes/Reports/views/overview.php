<?php
/**
 * Knit Pay Reports – Overview Tab
 *
 * @package KnitPay\Reports
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="knit-pay-reports-overview" id="knit-pay-reports-overview">
	<h2 class="knit-pay-tab-heading"><?php esc_html_e( 'Overview', 'knit-pay-lang' ); ?></h2>

	<div class="knit-pay-kpi-cards" id="knit-pay-kpi-cards">
		<div class="knit-pay-kpi-card" id="kpi-total-revenue">
			<span class="kpi-label" title="<?php esc_attr_e( 'Total amount from successful payments only.', 'knit-pay-lang' ); ?>">
				<?php esc_html_e( 'Revenue', 'knit-pay-lang' ); ?> <span class="kpi-info-icon">&#9432;</span>
			</span>
			<div class="kpi-value" id="kpi-value-revenue" x-html="overviewKpiRevHtml()"></div>
		</div>
		<div class="knit-pay-kpi-card" id="kpi-total-transactions">
			<span class="kpi-label" title="<?php esc_attr_e( 'Successful payments out of total payment attempts. Includes partially refunded payments.', 'knit-pay-lang' ); ?>">
				<?php esc_html_e( 'Success / Total Payments', 'knit-pay-lang' ); ?> <span class="kpi-info-icon">&#9432;</span>
			</span>
			<span class="kpi-value" id="kpi-value-transactions" x-text="overviewKpiPaymentsHtml()"></span>
		</div>
		<div class="knit-pay-kpi-card" id="kpi-success-rate">
			<span class="kpi-label" title="<?php esc_attr_e( 'Percentage of payments that completed successfully. Includes partially refunded payments.', 'knit-pay-lang' ); ?>">
				<?php esc_html_e( 'Success Rate', 'knit-pay-lang' ); ?> <span class="kpi-info-icon">&#9432;</span>
			</span>
			<span class="kpi-value" id="kpi-value-success-rate" x-text="(overview.success_rate || 0) + '%'"></span>
		</div>
		<div class="knit-pay-kpi-card" id="kpi-avg-transaction">
			<span class="kpi-label" title="<?php esc_attr_e( 'Average amount per successful payment. Includes partially refunded payments.', 'knit-pay-lang' ); ?>">
				<?php esc_html_e( 'Avg. Amount', 'knit-pay-lang' ); ?> <span class="kpi-info-icon">&#9432;</span>
			</span>
			<div class="kpi-value" id="kpi-value-avg" x-html="overviewKpiAvgHtml()"></div>
		</div>
	</div>

	<div class="knit-pay-charts-grid">
		<div class="knit-pay-chart-container">
			<h3><?php esc_html_e( 'Revenue Trend', 'knit-pay-lang' ); ?></h3>
			<canvas id="knit-pay-chart-revenue-trend" width="400" height="200"></canvas>
		</div>

		<div class="knit-pay-chart-container">
			<h3><?php esc_html_e( 'Transaction Count', 'knit-pay-lang' ); ?></h3>
			<canvas id="knit-pay-chart-volume" width="400" height="200"></canvas>
		</div>

		<div class="knit-pay-chart-container">
			<h3><?php esc_html_e( 'Status Distribution', 'knit-pay-lang' ); ?></h3>
			<canvas id="knit-pay-chart-status" width="400" height="200"></canvas>
		</div>

		<div class="knit-pay-chart-container">
			<h3><?php esc_html_e( 'Top Gateways', 'knit-pay-lang' ); ?></h3>
			<canvas id="knit-pay-chart-gateways" width="400" height="200"></canvas>
		</div>
	</div>
</div>
