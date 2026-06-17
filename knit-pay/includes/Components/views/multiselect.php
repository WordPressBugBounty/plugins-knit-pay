<?php
/**
 * Knit Pay UI – Multiselect Dropdown Partial
 *
 * Renders a reusable multi-select dropdown using the Alpine.js knitPayMultiselect component.
 *
 * @param array $ms_config {
 *     Configuration for the multiselect dropdown.
 *
 *     @type string $label              Button label (e.g. 'Gateway')
 *     @type string $placeholder        Button text when nothing is selected (e.g. 'All gateways'). Defaults to $label.
 *     @type string $options_var        Alpine variable name for options array (e.g. 'gatewayOptions')
 *     @type string $selected_var       Alpine expression for selected array (e.g. 'filters.gateway')
 *     @type string $disabled           Alpine expression for disabled state (e.g. 'viewingTrash') or ''
 *     @type string $search_placeholder Search input placeholder text. Default 'Search…'
 *     @type string $all_label          'Select All' label. Default 'Select All'
 *     @type string $deselect_all_label 'Deselect All' label. Default 'Deselect All'
 * }
 *
 * @package KnitPay\Reports
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function render_knit_pay_multiselect( $ms_config ) {
	$label          = $ms_config['label'] ?? '';
	$placeholder    = $ms_config['placeholder'] ?? $label;
	$options_var    = $ms_config['options_var'] ?? '';
	$selected_var   = $ms_config['selected_var'] ?? '';
	$disabled       = $ms_config['disabled'] ?? '';
	$search_ph      = $ms_config['search_placeholder'] ?? __( 'Search…', 'knit-pay-lang' );
	$all_label      = $ms_config['all_label'] ?? __( 'Select All', 'knit-pay-lang' );
	$deselect_label = $ms_config['deselect_all_label'] ?? __( 'Deselect All', 'knit-pay-lang' );

	$label_esc          = esc_attr( $label );
	$placeholder_esc    = esc_attr( $placeholder );
	$search_ph_esc      = esc_attr( $search_ph );
	$all_label_esc      = esc_attr( $all_label );
	$deselect_label_esc = esc_attr( $deselect_label );

	$disabled_class = $disabled ? " :class=\"{ 'disabled': {$disabled} }\"" : '';
	$disabled_attr  = $disabled ? " :disabled=\"{$disabled}\"" : '';
	$disabled_show  = $disabled ? " && !{$disabled}" : '';
	$disabled_click = $disabled ? "if(!{$disabled}) " : '';

	?>
	<div class="knit-pay-filter-group"<?php echo $disabled_class; ?>>
		<label><?php echo esc_html( $label ); ?></label>
		<div class="knit-pay-multiselect" x-data="knitPayMultiselect(<?php echo $options_var; ?>, <?php echo $selected_var; ?>, '<?php echo $label_esc; ?>', '<?php echo $placeholder_esc; ?>', '<?php echo $search_ph_esc; ?>', '<?php echo $all_label_esc; ?>', '<?php echo $deselect_label_esc; ?>')" x-init="init()" @click.outside="closeDropdown()" @keydown="handleKeydown($event)">
			<button type="button" class="knit-pay-multiselect-btn" x-ref="triggerBtn" @click="<?php echo $disabled_click; ?>toggleOpen()"<?php echo $disabled_attr; ?>>
				<span class="knit-pay-multiselect-label" x-text="labelWithCount"></span>
				<template x-if="selected.length > 0"><span class="knit-pay-multiselect-count" x-text="selected.length"></span></template>
				<span class="dashicons dashicons-arrow-down-alt2"></span>
			</button>
			<div class="knit-pay-multiselect-dropdown" x-ref="dropdown" x-show="open<?php echo $disabled_show; ?>" x-transition>
				<div class="knit-pay-multiselect-search">
					<input type="text" x-ref="searchInput" x-model="search" :placeholder="searchPlaceholder" @click.stop @keydown="handleSearchKeydown($event)">
				</div>
				<div class="knit-pay-multiselect-all" @click="toggleAll()">
					<input type="checkbox" :checked="allSelected" :indeterminate="indeterminate">
					<span x-text="selectAllLabel"></span>
				</div>
				<div class="knit-pay-multiselect-options" x-ref="optionsList">
					<template x-for="(opt, index) in filteredOptions" :key="opt.value">
						<label class="knit-pay-multiselect-option" :class="{ 'is-highlighted': index === highlightedIndex }" @click.stop="handleOptionClick($event, index)">
							<input type="checkbox" :checked="isSelected(opt.value)" @change="toggleOption(opt.value)">
							<span class="knit-pay-multiselect-option-label" x-text="opt.label"></span>
						</label>
					</template>
				</div>
				<div class="knit-pay-multiselect-results" x-text="resultCountText"></div>
			</div>
		</div>
	</div>
	<?php
}
