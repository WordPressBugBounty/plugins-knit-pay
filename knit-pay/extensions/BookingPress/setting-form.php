<?php
/**
 * Knit Pay settings form for BookingPress.
 *
 * Serves bp-ui-* markup for the Vue 3 admin (BookingPress >= 1.5.6) and
 * legacy el-* markup for older versions, detected via the presence of
 * \BookingPress\admin\Settings. The new REST-based settings loader never
 * calls the bookingpress_add_setting_dynamic_data_fields filter, so the
 * configuration options are rendered server-side here.
 *
 * TODO: Remove the legacy el-* branch after 8 September 2028.
 */

defined( 'ABSPATH' ) || exit;

use Pronamic\WordPress\Pay\Plugin;

// New Vue 3 admin (bp-ui-*) vs legacy Vue 2 admin (el-*).
$knit_pay_is_bpa_vue3_admin = class_exists( '\BookingPress\admin\Settings' );

$knit_pay_configurations = Plugin::get_config_select_options( 'knit_pay' );

if ( $knit_pay_is_bpa_vue3_admin ) :
	?>
	<div class="bpa-pst-is-single-payment-box">
		<bp-ui-row type="flex" class="bpa-gs--tabs-pb__cb-item-row">
			<bp-ui-col :xs="12" :sm="12" :md="12" :lg="8" :xl="8" class="bpa-gs__cb-item-left --bpa-is-not-input-control">
				<h4> <?php esc_html_e( 'Knit Pay', 'knit-pay-lang' ); ?></h4>
			</bp-ui-col>
			<bp-ui-col :xs="12" :sm="12" :md="12" :lg="16" :xl="16" class="bpa-gs__cb-item-right">
				<bp-ui-form-item prop="knit_pay_payment">
					<bp-ui-switch class="bpa-swtich-control" v-model="payment_setting_form.knit_pay_payment"></bp-ui-switch>
				</bp-ui-form-item>
			</bp-ui-col>
		</bp-ui-row>
		<div class="bpa-ns--sub-module__card" v-if="payment_setting_form.knit_pay_payment == true">
			<bp-ui-row type="flex" class="bpa-ns--sub-module__card--row">
				<bp-ui-col :xs="12" :sm="12" :md="12" :lg="8" :xl="8" class="bpa-gs__cb-item-left">
					<h4><?php esc_html_e( 'Configuration', 'knit-pay-lang' ); ?></h4>
				</bp-ui-col>
				<bp-ui-col :xs="12" :sm="12" :md="12" :lg="16" :xl="16" class="bpa-gs__cb-item-right">
					<bp-ui-form-item prop="knit_pay_config_id">
						<bp-ui-select class="bpa-form-control" v-model="payment_setting_form.knit_pay_config_id"
							popper-class="bpa-el-select--is-with-navbar">
							<?php foreach ( $knit_pay_configurations as $knit_pay_config_key => $knit_pay_config_label ) : ?>
								<bp-ui-option value="<?php echo esc_attr( $knit_pay_config_key ); ?>" label="<?php echo esc_attr( $knit_pay_config_label ); ?>"></bp-ui-option>
							<?php endforeach; ?>
						</bp-ui-select>
					</bp-ui-form-item>
				</bp-ui-col>
			</bp-ui-row>
		</div>
	</div>
	<?php
else :
	?>
	<div class="bpa-pst-is-single-payment-box">
		<el-row type="flex" class="bpa-gs--tabs-pb__cb-item-row">
			<el-col :xs="12" :sm="12" :md="12" :lg="8" :xl="8" class="bpa-gs__cb-item-left --bpa-is-not-input-control">
				<h4> <?php esc_html_e( 'Knit Pay', 'knit-pay-lang' ); ?></h4>
			</el-col>
			<el-col :xs="12" :sm="12" :md="12" :lg="16" :xl="16" class="bpa-gs__cb-item-right">
				<el-form-item prop="knit_pay_payment">
					<el-switch class="bpa-swtich-control" v-model="payment_setting_form.knit_pay_payment"></el-switch>
				</el-form-item>
			</el-col>
		</el-row>
		<div class="bpa-ns--sub-module__card" v-if="payment_setting_form.knit_pay_payment == true">
			<el-row type="flex" class="bpa-ns--sub-module__card--row">
				<el-col :xs="12" :sm="12" :md="12" :lg="8" :xl="8" class="bpa-gs__cb-item-left">
					<h4><?php esc_html_e( 'Configuration', 'knit-pay-lang' ); ?></h4>
				</el-col>
				<el-col :xs="12" :sm="12" :md="12" :lg="16" :xl="16" class="bpa-gs__cb-item-right">
					<el-form-item prop="knit_pay_config_id">
					<el-select  class="bpa-form-control" v-model="payment_setting_form.knit_pay_config_id"
						popper-class="bpa-el-select--is-with-navbar">
						<el-option v-for="configuration in knit_pay_configurations" :value="configuration.value" :label="configuration.text">
							{{ configuration.text }}
						</el-option>
					</el-select>
					</el-form-item>
				</el-col>
			</el-row>
		</div>
	</div>
	<?php
endif;