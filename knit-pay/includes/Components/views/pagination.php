<?php
/**
 * Knit Pay UI – Pagination Partial
 *
 * Renders a WP admin-style pagination bar using official WP admin CSS classes
 * (tablenav-pages, pagination-links, button, displaying-num, etc.).
 * Only a small override is needed for SPA disabled-state behavior.
 *
 * Uses Alpine.js state from the parent component.
 *
 * @param array $pg_config {
 *     Optional configuration.
 *
 *     @type string $page_var        Alpine expression for current page. Default 'transactionsPage'
 *     @type string $total_pages_var Alpine expression for total pages. Default 'transactionsTotalPages'
 *     @type string $total_var       Alpine expression for total items. Default 'transactionsTotal'
 *     @type string $item_label      Singular item label. Default 'payment'
 *     @type string $go_to_fn        Alpine function to call for page change. Default 'goToPage'
 *     @type string $loading_var     Alpine expression for loading state. Default 'loading'
 * }
 *
 * @package KnitPay\Reports
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function render_knit_pay_pagination( $pg_config = [] ) {
	$page_var        = $pg_config['page_var'] ?? 'transactionsPage';
	$total_pages_var = $pg_config['total_pages_var'] ?? 'transactionsTotalPages';
	$total_var       = $pg_config['total_var'] ?? 'transactionsTotal';
	$item_label      = $pg_config['item_label'] ?? 'payment';
	$go_to_fn        = $pg_config['go_to_fn'] ?? 'goToPage';
	$loading_var     = $pg_config['loading_var'] ?? 'loading';
	$compact         = $pg_config['compact'] ?? false;

	$first_disabled = "{$page_var} <= 1 || {$loading_var}";
	$last_disabled  = "{$page_var} >= {$total_pages_var} || {$loading_var}";
	$prev_disabled  = $first_disabled;
	$next_disabled  = $last_disabled;

	$first_click = "if (!({$first_disabled})) {$go_to_fn}(1)";
	$prev_click  = "if (!({$prev_disabled})) {$go_to_fn}({$page_var} - 1)";
	$next_click  = "if (!({$next_disabled})) {$go_to_fn}({$page_var} + 1)";
	$last_click  = "if (!({$last_disabled})) {$go_to_fn}({$total_pages_var})";

	?>
	<div class="tablenav">
		<div class="tablenav-pages" x-show="<?php echo $total_var; ?> > 0">
			<?php if ( ! $compact ) : ?>
			<span class="displaying-num" x-text="<?php echo $total_var; ?> + ' <?php echo esc_attr( $item_label ); ?>' + (<?php echo $total_var; ?> === 1 ? '' : 's')"></span>
			<?php endif; ?>
			<span class="pagination-links">
				<a href="#" class="first-page button" :class="{ 'tablenav-pages-navspan disabled': <?php echo $first_disabled; ?> }" @click.prevent="<?php echo $first_click; ?>" aria-label="First page">&laquo;</a>
				<a href="#" class="prev-page button" :class="{ 'tablenav-pages-navspan disabled': <?php echo $prev_disabled; ?> }" @click.prevent="<?php echo $prev_click; ?>" aria-label="Previous page">&lsaquo;</a>
				<span class="paging-input">
					<input class="current-page" type="number" :value="<?php echo $page_var; ?>" @keydown.enter.prevent="<?php echo $go_to_fn; ?>($event.target.value)" min="1" :max="<?php echo $total_pages_var; ?>" aria-label="Current page">
					<span class="tablenav-paging-text"> of <span class="total-pages" x-text="<?php echo $total_pages_var; ?>"></span></span>
				</span>
				<a href="#" class="next-page button" :class="{ 'tablenav-pages-navspan disabled': <?php echo $next_disabled; ?> }" @click.prevent="<?php echo $next_click; ?>" aria-label="Next page">&rsaquo;</a>
				<a href="#" class="last-page button" :class="{ 'tablenav-pages-navspan disabled': <?php echo $last_disabled; ?> }" @click.prevent="<?php echo $last_click; ?>" aria-label="Last page">&raquo;</a>
			</span>
		</div>
	</div>
	<?php
}
