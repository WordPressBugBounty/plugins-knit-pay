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
