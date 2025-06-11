<?php if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
use Bookly\Backend\Components\Controls\Elements;
use Bookly\Backend\Components\Settings\Inputs;
use Bookly\Backend\Components\Settings\Payments;
use Bookly\Backend\Components\Settings\Selects;
use Pronamic\WordPress\Pay\Plugin;

$id                     = $payment_method;
$payment_configurations = Plugin::get_config_select_options( $payment_method_id );
foreach ( $payment_configurations as $key => $payment_config ) {
	$payment_config_options[] = [
		$key,
		$payment_config,
	];
}
?>
<div class="card bookly-collapse-with-arrow" data-slug="bookly-addon-<?php echo $id; ?>">
	<div class="card-header d-flex align-items-center">
		<?php Elements::renderReorder(); ?>
		<a href="#bookly_pmt_<?php echo $id; ?>" class="ml-2" role="button" data-toggle="bookly-collapse">
			<?php echo $gateway_name; ?>
		</a>
	</div>
	<div id="bookly_pmt_<?php echo $id; ?>" class="bookly-collapse bookly-show">
		<div class="card-body">
			<?php Selects::renderSingle( 'bookly_' . $id . '_enabled' ); ?>
			<div class="bookly-knit-pay">
				<?php Inputs::renderText( 'bookly_l10n_label_pay_' . $id, __( 'Payment Label', 'knit-pay-lang' ) ); ?>
				<?php Selects::renderSingle( 'bookly_' . $id . '_config_id', __( 'Configuration', 'knit-pay-lang' ), __( 'Configurations can be created in Knit Pay gateway configurations page at <a href="' . admin_url( 'edit.php?post_type=pronamic_gateway' ) . '">"Knit Pay >> Configurations"</a>.', 'knit-pay-lang' ), $payment_config_options ); ?>
				<?php Inputs::renderText( 'bookly_' . $id . '_payment_description', __( 'Payment Description', 'knit-pay-lang' ), sprintf( __( 'Available tags: %s', 'knit-pay-lang' ), sprintf( '<code>%s</code>', '{form_id}, {service_name}, {payment_id}' ) ) ); ?>
				<?php Inputs::renderText( 'bookly_' . $id . '_icon_url', __( 'Icon URL', 'knit-pay-lang' ) ); ?>
				<?php Payments::renderPriceCorrection( $id ); ?>
				<?php Inputs::renderText( 'bookly_' . $id . '_timeout', __( 'Time interval of payment gateway (in Seconds)', 'knit-pay-lang' ), __( 'This setting determines the time limit after which the payment made via the payment gateway is considered to be incomplete. This functionality requires a scheduled cron job.', 'knit-pay-lang' ) ); ?>
			</div>
		</div>
	</div>
</div>
