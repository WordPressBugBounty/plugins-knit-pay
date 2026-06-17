<?php
/**
 * Knit Pay Reports – Transactions Tab (Alpine.js)
 *
 * @var array $filter_data Available from parent scope.
 *
 * @package KnitPay\Reports
 */

require_once __DIR__ . '/../../Components/views/pagination.php';

$trash_count = $filter_data['trash_count'] ?? 0;

$columns = [
	'status'      => __( 'Status', 'knit-pay-lang' ),
	'transaction' => __( 'Transaction ID', 'knit-pay-lang' ),
	'amount'      => __( 'Amount', 'knit-pay-lang' ),
	'date'        => __( 'Date', 'knit-pay-lang' ),
	'gateway'     => __( 'Gateway', 'knit-pay-lang' ),
	'customer'    => __( 'Customer', 'knit-pay-lang' ),
	'method'      => __( 'Method', 'knit-pay-lang' ),
	'description' => __( 'Description', 'knit-pay-lang' ),
];
?>
<div class="knit-pay-reports-transactions" id="knit-pay-reports-transactions">
	<div class="knit-pay-transactions-header">
		<h2 class="knit-pay-tab-heading" x-show="!viewingTrash"><?php esc_html_e( 'Payments', 'knit-pay-lang' ); ?></h2>
		<h2 class="knit-pay-tab-heading" x-show="viewingTrash"><?php esc_html_e( 'Trash', 'knit-pay-lang' ); ?></h2>
		<span class="knit-pay-transactions-count" x-text="transactionsTotal + ' payment' + (transactionsTotal === 1 ? '' : 's')"></span>
		<div class="knit-pay-header-actions">
			<button
				type="button" class="button"
				x-show="!viewingTrash && <?php echo (int) $trash_count; ?> > 0"
				@click="enterTrashView()"
				:disabled="loading"
			>
				<span class="dashicons dashicons-trash" aria-hidden="true"></span>
				<?php esc_html_e( 'Trash', 'knit-pay-lang' ); ?> (<?php echo (int) $trash_count; ?>)
			</button>
			<button
				type="button" class="button"
				x-show="viewingTrash"
				@click="exitTrashView()"
				:disabled="loading"
			>
				<span class="dashicons dashicons-arrow-left-alt" aria-hidden="true"></span>
				<?php esc_html_e( 'Back to Payments', 'knit-pay-lang' ); ?>
			</button>
			<div class="knit-pay-bulk-actions-wrap" x-show="selectedIds.length > 0" x-transition>
				<span class="knit-pay-bulk-count" x-text="selectedIds.length + ' selected'"></span>
				<template x-if="isTrashView()">
					<select class="knit-pay-bulk-select" x-model="bulkAction" :disabled="bulkActionLoading">
						<option value=""><?php esc_html_e( 'Actions', 'knit-pay-lang' ); ?></option>
						<option value="restore"><?php esc_html_e( 'Restore', 'knit-pay-lang' ); ?></option>
						<option value="delete_permanently"><?php esc_html_e( 'Delete Permanently', 'knit-pay-lang' ); ?></option>
					</select>
				</template>
				<template x-if="!isTrashView()">
					<select class="knit-pay-bulk-select" x-model="bulkAction" :disabled="bulkActionLoading">
						<option value=""><?php esc_html_e( 'Actions', 'knit-pay-lang' ); ?></option>
						<option value="check_status"><?php esc_html_e( 'Check Status', 'knit-pay-lang' ); ?></option>
						<option value="trash"><?php esc_html_e( 'Move to Trash', 'knit-pay-lang' ); ?></option>
					</select>
				</template>
				<button type="button" class="button button-small" @click="applyBulkAction()" :disabled="loading || bulkActionLoading"><?php esc_html_e( 'Apply', 'knit-pay-lang' ); ?><template x-if="bulkActionLoading"><span class="spinner is-active knit-pay-btn-spinner" style="margin:0 0 0 4px;"></span></template></button>
			</div>
		</div>
	</div>

	<div class="knit-pay-transactions-toolbar" id="knit-pay-transactions-toolbar">
		<div class="knit-pay-toolbar-left">
			<div class="knit-pay-search-wrap">
				<input type="search" class="knit-pay-search-input" placeholder="<?php esc_attr_e( 'Search payments…', 'knit-pay-lang' ); ?>" aria-label="<?php esc_attr_e( 'Search payments', 'knit-pay-lang' ); ?>" x-model="searchQuery" @keydown.enter.prevent="if(!loading) loadTab('payments')">
				<button type="button" class="button knit-pay-search-btn" @click="if(!loading) loadTab('payments')" :disabled="loading" title="<?php esc_attr_e( 'Search', 'knit-pay-lang' ); ?>">
					<span class="dashicons dashicons-search" aria-hidden="true"></span>
				</button>
			</div>
			<div class="knit-pay-per-page-wrap">
				<select class="knit-pay-per-page-select" x-model.number="transactionsPerPage" @change="if(!loading) loadTab('payments')" :disabled="loading">
					<option value="10">10</option>
					<option value="25">25</option>
					<option value="50">50</option>
					<option value="100">100</option>
					<option value="250">250</option>
				</select>
				<label class="knit-pay-per-page-label"><?php esc_html_e( 'per page', 'knit-pay-lang' ); ?></label>
			</div>
			<div class="knit-pay-col-visibility-wrap" @click.outside="colDropdownOpen = false">
				<button type="button" class="button button-small knit-pay-col-toggle-btn" title="<?php esc_attr_e( 'Columns', 'knit-pay-lang' ); ?>" @click="colDropdownOpen = !colDropdownOpen">
					<span class="dashicons dashicons-columns" aria-hidden="true"></span>
					<span class="knit-pay-col-toggle-text"><?php esc_html_e( 'Columns', 'knit-pay-lang' ); ?></span>
				</button>
				<div class="knit-pay-col-dropdown" x-show="colDropdownOpen">
					<?php foreach ( $columns as $key => $label ) : ?>
					<label class="knit-pay-col-option">
						<input type="checkbox" value="1" :checked="isColVisible('<?php echo esc_attr( $key ); ?>')" @change="toggleCol('<?php echo esc_attr( $key ); ?>')">
						<?php echo esc_html( $label ); ?>
					</label>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<div class="knit-pay-toolbar-right">
			<div class="knit-pay-toolbar-pagination">
				<?php render_knit_pay_pagination( [ 'compact' => true ] ); ?>
			</div>
		</div>
	</div>

	<div class="knit-pay-search-notice" x-show="searchQuery" x-transition>
		<span x-html="i18n.search_all_results.replace('{query}', '<strong>' + escHtml(searchQuery) + '</strong>')"></span>
		<button type="button" class="button-link knit-pay-search-clear" @click="searchQuery = ''; loadTab('payments')" :disabled="loading"><?php esc_html_e( 'Clear search', 'knit-pay-lang' ); ?></button>
	</div>

	<div class="knit-pay-table-scroll-wrap">
		<table class="knit-pay-report-table knit-pay-transactions-table" id="knit-pay-transactions-table">
			<thead>
				<tr>
					<th class="knit-pay-col-cb" data-col="cb">
						<input type="checkbox" :checked="allSelected" @change="toggleSelectAll($event.target.checked)" :disabled="bulkActionLoading" aria-label="<?php esc_attr_e( 'Select all', 'knit-pay-lang' ); ?>">
					</th>
					<th x-show="isColVisible('status')"><?php esc_html_e( 'Status', 'knit-pay-lang' ); ?></th>
					<th class="knit-pay-sortable" :class="sortClass('ID')">
						<a href="#" @click.prevent="toggleSort('ID')"><?php esc_html_e( 'ID', 'knit-pay-lang' ); ?></a>
					</th>
					<th x-show="isColVisible('transaction')"><?php esc_html_e( 'Transaction ID', 'knit-pay-lang' ); ?></th>
					<th class="knit-pay-sortable knit-pay-col-amount" :class="sortClass('amount')">
						<a href="#" @click.prevent="toggleSort('amount')"><?php esc_html_e( 'Amount', 'knit-pay-lang' ); ?></a>
					</th>
					<th class="knit-pay-sortable" :class="sortClass('date')">
						<a href="#" @click.prevent="toggleSort('date')"><?php esc_html_e( 'Date', 'knit-pay-lang' ); ?></a>
					</th>
					<th x-show="isColVisible('gateway')"><?php esc_html_e( 'Gateway', 'knit-pay-lang' ); ?></th>
					<th x-show="isColVisible('customer')"><?php esc_html_e( 'Customer', 'knit-pay-lang' ); ?></th>
					<th x-show="isColVisible('method')"><?php esc_html_e( 'Method', 'knit-pay-lang' ); ?></th>
					<th x-show="isColVisible('description')"><?php esc_html_e( 'Description', 'knit-pay-lang' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr x-show="loading && transactions.length === 0">
					<td :colspan="2 + visibleCols.length" class="knit-pay-loading">
						<span class="spinner is-active" style="float:none;margin:0 8px 0 0;"></span>
						<span x-text="i18n.loading || 'Loading…'"></span>
					</td>
				</tr>
				<tr x-show="!loading && transactions.length === 0">
					<td :colspan="2 + visibleCols.length" class="knit-pay-loading" x-text="i18n.no_data || 'No data available for the selected filters.'"></td>
				</tr>
				<template x-for="t in transactions" :key="t.id">
					<tr>
						<td class="knit-pay-col-cb">
							<input type="checkbox" class="knit-pay-txn-checkbox" :value="t.id" :checked="isSelected(t.id)" @change="toggleSelectId(t.id)" :disabled="bulkActionLoading">
						</td>
						<td class="knit-pay-col-status" data-label="<?php esc_attr_e( 'Status', 'knit-pay-lang' ); ?>" x-show="isColVisible('status')">
							<span class="knit-pay-status-icon dashicons" :class="statusIcon(t.status)" :title="t.status_label" :style="'color:' + statusColor(t.status)"></span>
							<span class="screen-reader-text" x-text="t.status_label"></span>
						</td>
						<td class="knit-pay-col-id" data-label="<?php esc_attr_e( 'ID', 'knit-pay-lang' ); ?>">
							<a :href="t.edit_url" x-text="'#' + t.id"></a>
							<template x-if="t.source_description || t.source_id">
								<span class="knit-pay-source-desc">
									<span x-text="t.source_description || ''"></span>
									<template x-if="t.source_link">
										<a :href="t.source_link" x-text="t.source_id ? ' #' + t.source_id : ''"></a>
									</template>
									<template x-if="!t.source_link">
										<span x-show="t.source_id" x-text="t.source_id ? ' #' + t.source_id : ''"></span>
									</template>
								</span>
							</template>
						</td>
						<td class="knit-pay-col-txnid" data-label="<?php esc_attr_e( 'Transaction ID', 'knit-pay-lang' ); ?>" x-show="isColVisible('transaction')"><template x-if="t.provider_link"><a :href="t.provider_link" x-text="t.transaction_id || '\u2014'" target="_blank"></a></template><template x-if="!t.provider_link"><span x-text="t.transaction_id || '\u2014'"></span></template></td>
						<td class="knit-pay-col-amount" data-label="<?php esc_attr_e( 'Amount', 'knit-pay-lang' ); ?>" x-show="isColVisible('amount')" x-html="buildAmountCell(t)"></td>
						<td class="knit-pay-col-date" data-label="<?php esc_attr_e( 'Date', 'knit-pay-lang' ); ?>" x-show="isColVisible('date')" x-text="t.date"></td>
						<td class="knit-pay-col-gateway" data-label="<?php esc_attr_e( 'Gateway', 'knit-pay-lang' ); ?>" x-show="isColVisible('gateway')"><template x-if="t.gateway_edit_url"><a :href="t.gateway_edit_url" x-text="t.gateway_name || '\u2014'"></a></template><template x-if="!t.gateway_edit_url"><span x-text="t.gateway_name || '\u2014'"></span></template></td>
						<td class="knit-pay-col-customer" data-label="<?php esc_attr_e( 'Customer', 'knit-pay-lang' ); ?>" x-show="isColVisible('customer')" x-text="t.customer || '\u2014'"></td>
						<td class="knit-pay-col-method" data-label="<?php esc_attr_e( 'Method', 'knit-pay-lang' ); ?>" x-show="isColVisible('method')" x-text="t.payment_method ? methodName(t.payment_method) : '\u2014'"></td>
						<td class="knit-pay-col-description" data-label="<?php esc_attr_e( 'Description', 'knit-pay-lang' ); ?>" x-show="isColVisible('description')" x-text="t.description || '\u2014'"></td>
					</tr>
				</template>
			</tbody>
		</table>
	</div>

	<?php render_knit_pay_pagination(); ?>
</div>
