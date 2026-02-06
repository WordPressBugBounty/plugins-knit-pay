<?php

namespace KnitPay\Extensions\Charitable;

use Pronamic\WordPress\Pay\Extensions\Charitable\Extension as Pronamic_Charitable_Extension;

/**
 * Title: Charitable extension
 * Description:
 * Copyright: 2020-2026 Knit Pay
 * Company: Knit Pay
 *
 * @author  knitpay
 * @since   8.96.19.0
 */
class Extension extends Pronamic_Charitable_Extension {
	private $slug          = 'pronamic_pay';
	private $primary_label = '';
	private $active        = true;

	/**
	 * Setup plugin integration.
	 *
	 * @return void
	 */
	public function setup() {
		parent::setup();

		// Check if dependencies are met and integration is active.
		if ( ! $this->is_active() ) {
			return;
		}

		$this->primary_label = esc_html__( 'Knit Pay', 'knit-pay-lang' );

		// Add payment panel to Charitable builder.
		add_action( 'charitable_campaign_builder_payment_sidebar', [ $this, 'add_payment_sidebar' ], 5 );
		add_action( 'charitable_campaign_builder_payment_panels', [ $this, 'panel_content' ] );
	}

	public function add_payment_sidebar() {
		$css_class = ( true === apply_filters( 'charitable_campaign_builder_marketing_sidebar_active', $this->active, esc_attr( $this->slug ) ) ) ? 'active' : '';
		$data_name = esc_html__( 'ability to use', 'knit-pay-lang' ) . ' ' . $this->primary_label;

		echo '<a href="#" class="charitable-panel-sidebar-section charitable-panel-sidebar-section-' . esc_attr( $this->slug ) . ' ' . esc_attr( $css_class ) . '" data-name="' . esc_html( $data_name ) . '" data-section="' . esc_attr( $this->slug ) . '">'
			. '<img class="charitable-builder-sidebar-icon" src="' . esc_url( 'https://ps.w.org/knit-pay/assets/icon.svg' ) . '" style="padding: 4px;background: white;" />'
			. esc_html( $this->primary_label ) . '<span class="charitable-badge charitable-badge-sm charitable-badge-inline charitable-badge-green charitable-badge-rounded"><i class="fa fa-trophy" aria-hidden="true"></i>' . esc_html__( 'Recommended', 'knit-pay-lang' ) . '</span>'
			. ' <i class="fa fa-angle-right charitable-toggle-arrow"></i></a>';
	}

	public function panel_content() {
		$active = ( true === apply_filters( 'charitable_campaign_builder_settings_sidebar_active', $this->active, $this->slug ) ) ? 'active' : false;
		$style  = ( true === apply_filters( 'charitable_campaign_builder_settings_sidebar_active', $this->active, $this->slug ) ) ? 'display: block;' : false;

		ob_start();

		?>

		<div class="charitable-panel-content-section charitable-panel-content-section-parent-payment charitable-panel-content-section-<?php echo $this->slug; ?> <?php echo $active; ?>" style="<?php echo $style; ?>">

		<div class="charitable-panel-content-section-title"><?php echo $this->primary_label; ?> <?php echo esc_html__( 'Settings', 'knit-pay-lang' ); ?></div>

		<div class="charitable-panel-content-section-interior">

		<?php
			do_action( 'charitable_campaign_builder_before_payment_' . $this->slug );
		?>

		<?php $this->education_payment_text( $this->primary_label ); ?>

		<?php
			do_action( 'charitable_campaign_builder_after_payment_' . $this->slug );
		?>

		</div></div>

		<?php

			$html = ob_get_clean();

			echo $html;
	}

	/**
	 * Process education payment text.
	 *
	 * @param string $label Reader friendly output of object.
	 */
	public function education_payment_text( $label = false ) {
		$icon_url = 'https://ps.w.org/knit-pay/assets/icon.svg';
		echo '<img class="charitable-builder-sidebar-icon" src="' . $icon_url . '" wdith="178" height="178" />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		?>

		<section class="header-content">
			<h2><?php echo esc_html__( 'Charitable has ', 'knit-pay-lang' ); ?><span><?php echo esc_html( $label ); ?></span> <?php echo esc_html__( 'plugin installed.', 'knit-pay-lang' ); ?></h2>
			<p><?php echo esc_html( $label ); ?> <?php echo esc_html__( 'allows you to easily reach more supporters and increase donations by integrating 500+ payment gateways with Charitable.', 'knit-pay-lang' ); ?></p>
		</section>

		<div class="education-buttons">
			<?php
			$action_url = admin_url( 'admin.php?page=charitable-settings&tab=gateways&group=gateways_pronamic_pay' );
			$config_url = admin_url( 'edit.php?post_type=pronamic_gateway' );

			echo '<a class="button-link" href="' . esc_url( $config_url ) . '" target="_blank">' . esc_html__( 'Knit Pay Configurations', 'knit-pay-lang' ) . '</a>';
			echo '<a class="button-link" href="' . esc_url( $action_url ) . '" target="_blank">' . esc_html__( 'Knit Pay Charitable Settings', 'knit-pay-lang' ) . '</a>';
			?>

		</div>
		<?php
	}
}
