<?php
/**
 * Knit Pay Settings.
 */
use Pronamic\WordPress\Pay\Plugin;
use Pronamic\WordPress\Html\Element;


$wp_travel_engine_settings = get_option( 'wp_travel_engine_settings', [] );
$knit_pay_settings         = isset( $wp_travel_engine_settings['knit_pay_settings'] ) ? $wp_travel_engine_settings['knit_pay_settings'] : [];
$title                     = ! empty( $knit_pay_settings['title'] ) ? $knit_pay_settings['title'] : __( 'Online Payment', 'knit-pay-lang' );
$description               = ! empty( $knit_pay_settings['description'] ) ? $knit_pay_settings['description'] : '';
$icon                      = ! empty( $knit_pay_settings['icon'] ) ? $knit_pay_settings['icon'] : '';
$payment_description       = ! empty( $knit_pay_settings['payment_description'] ) ? $knit_pay_settings['payment_description'] : __( 'WTE Booking {booking_id}', 'knit-pay-lang' );
$config_id                 = ! empty( $knit_pay_settings['config_id'] ) ? $knit_pay_settings['config_id'] : get_option( 'pronamic_pay_config_id' );

function knit_pay_wte_render_setting_field( $args ) {

	printf( '<div class="wpte-field wpte-floated">' );

	printf(
		'<label class="wpte-field-label">%s</label>',
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$args['label']
	);


	$args['id']   = "wp_travel_engine_settings[knit_pay_settings][{$args['id']}]";
	$args['name'] = $args['id'];

	$element = new Element( 'input', $args );
	$element->output();

	if ( isset( $args['description'] ) ) {
		printf(
			'<span class="wpte-tooltip">%s</span>',
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$args['description']
		);
	}
	printf( '</div>' );
}

?>
<div style="margin-bottom: 40px;" class="wpte-info-block">
	<b><?php _e( 'Note:', 'knit-pay-lang' ); ?></b>
	<p><?php _e( 'WP Travel Engine has done major changes in payment processing in v 4.3.0 and the new version of WP Travel Engine Payments is currently not stable and still under development. Knit Pay is now compatible with the new version of WP Travel Engine and will not work with the old version of WP Travel Engine.', 'knit-pay-lang' ); ?></p>
	<p>This version is currently under Beta and you might face some issues while using it. Kindly report the issue to Knit Pay if you find any bugs.</p>
</div>
		
<?php
knit_pay_wte_render_setting_field(
	[
		'label'       => __( 'Title', 'knit-pay-lang' ),
		'description' => __( 'This controls the title which the user sees during checkout.', 'knit-pay-lang' ),
		'id'          => 'title',
		'type'        => 'text',
		'class'       => 'regular-text',
		'value'       => $title,
	]
);

knit_pay_wte_render_setting_field(
	[
		'label'       => __( 'Description', 'knit-pay-lang' ),
		'description' => sprintf(
			/* translators: %s: payment method title */
			__( 'Give the customer instructions for paying via %s.', 'knit-pay-lang' ),
			__( 'Knit Pay', 'knit-pay-lang' )
		),
		'id'          => 'description',
		'type'        => 'text',
		'class'       => 'regular-text',
		'value'       => $description,
	]
);

knit_pay_wte_render_setting_field(
	[
		'label'       => __( 'Icon URL', 'knit-pay-lang' ),
		'description' => __( 'This controls the icon which the user sees during checkout.', 'knit-pay-lang' ),
		'id'          => 'icon',
		'type'        => 'text',
		'class'       => 'regular-text',
		'value'       => $icon,
	]
);

knit_pay_wte_render_setting_field(
	[
		'label'       => __( 'Payment Description', 'knit-pay-lang' ),
		'description' => sprintf( __( 'Available tags: %s', 'knit-pay-lang' ), sprintf( '<code>%s</code>', '{booking_id}' ) ),
		'id'          => 'payment_description',
		'type'        => 'text',
		'class'       => 'regular-text',
		'value'       => $payment_description,
	]
);
?>

<div class="wpte-field wpte-select wpte-floated">
	<label class="wpte-field-label"><?php esc_html_e( 'Configuration', 'knit-pay-lang' ); ?></label>
	<select name="wp_travel_engine_settings[knit_pay_settings][config_id]">
			<?php
			// TODO: remove hardcoded
			$payment_method = 'knit_pay';
			$configurations = Plugin::get_config_select_options( $payment_method );
			foreach ( $configurations as $key => $configuration ) :
				?>
				<option
					<?php selected( $config_id, $key ); ?>
					value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $configuration ); ?></option>
				<?php
			endforeach;
			?>
	</select> <span class="wpte-tooltip"><?php echo ( 'Configurations can be created in Knit Pay gateway configurations page at <a href="' . admin_url( 'edit.php?post_type=pronamic_gateway' ) . '">"Knit Pay >> Configurations"</a>.' ); ?></span>
</div>
<?php
