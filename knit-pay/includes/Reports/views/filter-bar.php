<?php
/**
 * Knit Pay Reports – Filter Bar
 *
 * @var array $filter_data Output of ReportsApiHelper::get_filter_options().
 *
 * @package KnitPay\Reports
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gateways        = $filter_data['gateways'] ?? [];
$currencies      = $filter_data['currencies'] ?? [];
$sources         = $filter_data['sources'] ?? [];
$statuses        = $filter_data['statuses'] ?? [];
$trash_count     = $filter_data['trash_count'] ?? 0;
$payment_methods = $filter_data['payment_methods'] ?? [];

$preset_ranges = [
	'rolling'        => [
		'today'        => __( 'Today', 'knit-pay-lang' ),
		'yesterday'    => __( 'Yesterday', 'knit-pay-lang' ),
		'last_7_days'  => __( 'Last 7 Days', 'knit-pay-lang' ),
		'last_30_days' => __( 'Last 30 Days', 'knit-pay-lang' ),
	],
	'calendar'       => [
		'this_month'   => __( 'This Month', 'knit-pay-lang' ),
		'last_month'   => __( 'Last Month', 'knit-pay-lang' ),
		'this_quarter' => __( 'This Quarter', 'knit-pay-lang' ),
		'last_quarter' => __( 'Last Quarter', 'knit-pay-lang' ),
		'this_year'    => __( 'This Year', 'knit-pay-lang' ),
		'last_year'    => __( 'Last Year', 'knit-pay-lang' ),
	],
	'financial_year' => [
		'this_fy' => __( 'This Financial Year', 'knit-pay-lang' ),
		'last_fy' => __( 'Last Financial Year', 'knit-pay-lang' ),
	],
];
?>
<div class="knit-pay-reports-filter-bar" id="knit-pay-reports-filter-bar">
	<div class="knit-pay-reports-filters" @change="saveFilterState()">

		<!-- Row 1: Date Range Filters -->
		<div class="knit-pay-filter-row knit-pay-filter-row-date">
			<div class="knit-pay-filter-group">
				<label for="knit-pay-report-range-preset"><?php esc_html_e( 'Quick Range', 'knit-pay-lang' ); ?></label>
				<select id="knit-pay-report-range-preset" class="knit-pay-filter-select" x-model="presetRange" @change="applyPreset($event.target.value)">
					<option value=""><?php esc_html_e( 'Custom', 'knit-pay-lang' ); ?></option>
					<?php foreach ( $preset_ranges as $group => $options ) : ?>
						<?php
						$group_label = '';
						switch ( $group ) {
							case 'rolling':
								$group_label = __( 'Rolling', 'knit-pay-lang' );
								break;
							case 'calendar':
								$group_label = __( 'Calendar', 'knit-pay-lang' );
								break;
							case 'financial_year':
								$group_label = __( 'Financial Year (starts 1 Apr)', 'knit-pay-lang' );
								break;
						}
						?>
						<optgroup label="<?php echo esc_attr( $group_label ); ?>">
							<?php foreach ( $options as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</optgroup>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="knit-pay-filter-group">
				<label for="knit-pay-report-from"><?php esc_html_e( 'From', 'knit-pay-lang' ); ?></label>
				<input type="date" id="knit-pay-report-from" class="knit-pay-filter-input" x-model="filters.from" @input="presetRange = ''">
			</div>

			<div class="knit-pay-filter-group">
				<label for="knit-pay-report-to"><?php esc_html_e( 'To', 'knit-pay-lang' ); ?></label>
				<input type="date" id="knit-pay-report-to" class="knit-pay-filter-input" x-model="filters.to" @input="presetRange = ''">
			</div>

			<div class="knit-pay-filter-group">
				<label for="knit-pay-report-mode"><?php esc_html_e( 'Mode', 'knit-pay-lang' ); ?></label>
				<select id="knit-pay-report-mode" class="knit-pay-filter-select" x-model="filters.mode">
					<option value="live"><?php esc_html_e( 'Live', 'knit-pay-lang' ); ?></option>
					<option value="test"><?php esc_html_e( 'Test', 'knit-pay-lang' ); ?></option>
				</select>
			</div>
		</div>

		<!-- Row 2: Multi-select Filters -->
		<div class="knit-pay-filter-row knit-pay-filter-row-multiselect">
			<?php
			require __DIR__ . '/../../Components/views/multiselect.php';
			render_knit_pay_multiselect(
				[
					'label'        => __( 'Gateway', 'knit-pay-lang' ),
					'placeholder'  => __( 'All gateways', 'knit-pay-lang' ),
					'options_var'  => 'gatewayOptions',
					'selected_var' => 'filters.gateway',
				]
			);

			render_knit_pay_multiselect(
				[
					'label'        => __( 'Status', 'knit-pay-lang' ),
					'placeholder'  => __( 'All statuses', 'knit-pay-lang' ),
					'options_var'  => 'statusOptions',
					'selected_var' => 'filters.status',
					'disabled'     => 'viewingTrash',
				]
			);

			render_knit_pay_multiselect(
				[
					'label'        => __( 'Integration', 'knit-pay-lang' ),
					'placeholder'  => __( 'All integrations', 'knit-pay-lang' ),
					'options_var'  => 'sourceOptions',
					'selected_var' => 'filters.source',
				]
			);

			render_knit_pay_multiselect(
				[
					'label'        => __( 'Payment Method', 'knit-pay-lang' ),
					'placeholder'  => __( 'All payment methods', 'knit-pay-lang' ),
					'options_var'  => 'paymentMethodOptions',
					'selected_var' => 'filters.payment_method',
				]
			);

			render_knit_pay_multiselect(
				[
					'label'        => __( 'Currency', 'knit-pay-lang' ),
					'placeholder'  => __( 'All currencies', 'knit-pay-lang' ),
					'options_var'  => 'currencyOptions',
					'selected_var' => 'filters.currency',
				]
			);
			?>
		</div>

		<!-- Row 3: Action buttons -->
		<div class="knit-pay-filter-row knit-pay-filter-row-actions">
			<button type="button" class="button button-primary" @click="applyFilters()" :disabled="loading">
				<template x-if="activeAction !== 'apply'"><span class="dashicons dashicons-filter"></span></template>
				<template x-if="activeAction === 'apply'"><span class="spinner is-active knit-pay-btn-spinner"></span></template>
				<?php esc_html_e( 'Apply', 'knit-pay-lang' ); ?>
			</button>
			<button type="button" class="button" @click="resetFilters()" :disabled="loading">
				<template x-if="activeAction !== 'reset'"><span class="dashicons dashicons-undo"></span></template>
				<template x-if="activeAction === 'reset'"><span class="spinner is-active knit-pay-btn-spinner"></span></template>
				<?php esc_html_e( 'Reset', 'knit-pay-lang' ); ?>
			</button>
			<button type="button" class="button" @click="exportCSV()" :disabled="loading || activeAction !== ''">
				<template x-if="activeAction !== 'export-csv'"><span class="dashicons dashicons-media-spreadsheet"></span></template>
				<template x-if="activeAction === 'export-csv'"><span class="spinner is-active knit-pay-btn-spinner"></span></template>
				<?php esc_html_e( 'Export CSV', 'knit-pay-lang' ); ?>
			</button>
			<button type="button" class="button" @click="exportPDF()" :disabled="loading || activeAction !== '' || activeTab === 'payments'">
				<template x-if="activeAction !== 'export-pdf'"><span class="dashicons dashicons-media-document"></span></template>
				<template x-if="activeAction === 'export-pdf'"><span class="spinner is-active knit-pay-btn-spinner"></span></template>
				<?php esc_html_e( 'Export PDF', 'knit-pay-lang' ); ?>
			</button>
		</div>

	</div>
</div>
