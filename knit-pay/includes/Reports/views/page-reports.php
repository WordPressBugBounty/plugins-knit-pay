<?php
/**
 * Knit Pay Reports – Main Page Wrapper
 *
 * @var string $active_tab Currently active tab slug.
 * @var array  $tabs        Available tabs [slug => label].
 * @var array  $filter_data Filter options from ReportsApiHelper::get_filter_options().
 *
 * @package KnitPay\Reports
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap knit-pay-reports-wrap" x-data="knitPayReports()" x-init="init()" x-cloak>

	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<?php require __DIR__ . '/filter-bar.php'; ?>

	<nav class="nav-tab-wrapper knit-pay-reports-nav-tab-wrapper" id="knit-pay-reports-nav">
		<?php foreach ( $tabs as $slug => $label ) : ?>
			<a href="#<?php echo esc_attr( $slug ); ?>"
				class="nav-tab knit-pay-report-tab"
				:class="{ 'nav-tab-active': activeTab === '<?php echo esc_attr( $slug ); ?>' }"
				@click.prevent="switchTab('<?php echo esc_attr( $slug ); ?>')">
				<?php echo esc_html( $label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="knit-pay-reports-content" id="knit-pay-reports-content" :class="{ 'is-loading': loading }">

		<template x-if="appliedMode === 'test'">
			<div class="knit-pay-test-mode-badge">
				<span class="dashicons dashicons-flag"></span>
				<?php esc_html_e( 'Test Mode Data', 'knit-pay-lang' ); ?>
			</div>
		</template>

		<template x-if="errorMessage">
			<div class="knit-pay-error-notice" role="alert">
				<span class="dashicons dashicons-warning"></span>
				<span class="knit-pay-error-notice-message" x-text="errorMessage"></span>
				<button type="button" class="knit-pay-error-notice-dismiss" @click="dismissError()" aria-label="<?php esc_attr_e( 'Dismiss error', 'knit-pay-lang' ); ?>">
					<span class="dashicons dashicons-dismiss"></span>
				</button>
			</div>
		</template>

		<div class="knit-pay-progress-bar" x-show="loading"></div>

		<div class="knit-pay-tab-pane" :class="{ 'active': activeTab === 'overview' }" id="tab-pane-overview">
			<?php require __DIR__ . '/overview.php'; ?>
		</div>

		<div class="knit-pay-tab-pane" :class="{ 'active': activeTab === 'payments' }" id="tab-pane-payments">
			<?php require __DIR__ . '/transactions.php'; ?>
		</div>

		<div class="knit-pay-tab-pane" :class="{ 'active': activeTab === 'gateways' }" id="tab-pane-gateways">
			<?php require __DIR__ . '/gateway-performance.php'; ?>
		</div>

		<div class="knit-pay-tab-pane" :class="{ 'active': activeTab === 'payment-methods' }" id="tab-pane-payment-methods">
			<?php require __DIR__ . '/payment-methods.php'; ?>
		</div>

		<div class="knit-pay-tab-pane" :class="{ 'active': activeTab === 'integrations' }" id="tab-pane-integrations">
			<?php require __DIR__ . '/sources.php'; ?>
		</div>

		<div class="knit-pay-tab-pane" :class="{ 'active': activeTab === 'refunds' }" id="tab-pane-refunds">
			<?php require __DIR__ . '/refunds.php'; ?>
		</div>
	</div>

	<div id="knit-pay-table-aria-live" class="screen-reader-text" aria-live="polite" aria-atomic="true"></div>
</div>
